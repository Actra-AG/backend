<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\libs\form\ConfirmationForm;
use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\ResponseSentException;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\auth\AuthSession;
use actra\yuf\auth\UnauthorizedIpAddressException;
use actra\yuf\db\FrameworkDb;
use actra\yuf\exception\NotFoundException;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\TestCase;

/**
 * The IP whitelists of the user and of the backend both apply, users who manage users only manage users with no more
 * rights than themselves, and impersonation is a confirmed POST.
 */
final class AccessControlTest extends TestCase
{
    private const string PROJECT_RIGHT = 'audit_project_right';
    private FrameworkDb $db;
    private TestUsers $testUsers;

    #[\Override]
    protected function setUp(): void
    {
        $this->db = TestDatabase::connect();
        $this->testUsers = new TestUsers(db: $this->db);
    }

    private static function email(): string
    {
        return 'access-' . bin2hex(string: random_bytes(length: 4)) . '@example.com';
    }

    /**
     * @param list<string> $userIpWhitelist
     */
    private function logIn(BackendPageRenderer $renderer, Session $session, array $userIpWhitelist = []): int
    {
        $userId = $this->testUsers->create(email: AccessControlTest::email(), ipWhitelist: $userIpWhitelist);
        $renderer->logIn(session: $session, userId: $userId);

        return $userId;
    }

    /**
     * A user with the administrator group and a group with a right the administrators do not have.
     */
    private function createUserWithProjectRight(): int
    {
        $this->db->execute(
            sql: 'INSERT IGNORE INTO auth_right SET name=?, title=?',
            parameters: [AccessControlTest::PROJECT_RIGHT, 'Project'],
        );
        $this->db->execute(sql: 'INSERT INTO auth_group SET title=?', parameters: ['Project ' . bin2hex(random_bytes(3))]);
        $groupId = $this->db->getLastInsertId();
        $this->db->execute(
            sql: 'INSERT INTO auth_group_right SET group_id=?, right_name=?',
            parameters: [$groupId, AccessControlTest::PROJECT_RIGHT],
        );
        $userId = $this->testUsers->create(email: AccessControlTest::email());
        $this->db->execute(sql: 'INSERT INTO auth_user_group SET user_id=?, group_id=?', parameters: [$userId, $groupId]);

        return $userId;
    }

    public function testUserWhitelistDoesNotWidenTheGlobalWhitelist(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings(), ipWhitelist: ['198.51.100.0/24']);
        $session = new Session(storage: new ArraySessionStorage());
        $this->logIn(renderer: $renderer, session: $session, userIpWhitelist: [BackendPageRenderer::IP_ADDRESS]);

        $this->expectException(UnauthorizedIpAddressException::class);
        $renderer->render(languageCode: 'de', fileTitle: 'profile', pathVars: [], session: $session);
    }

    public function testUserWhitelistAppliesWithinTheGlobalWhitelist(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings(), ipWhitelist: ['192.0.2.0/24']);
        $session = new Session(storage: new ArraySessionStorage());
        $this->logIn(renderer: $renderer, session: $session, userIpWhitelist: ['198.51.100.7']);

        $this->expectException(UnauthorizedIpAddressException::class);
        $renderer->render(languageCode: 'de', fileTitle: 'profile', pathVars: [], session: $session);
    }

    public function testBothWhitelistsMatching(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings(), ipWhitelist: ['192.0.2.0/24']);
        $session = new Session(storage: new ArraySessionStorage());
        $this->logIn(renderer: $renderer, session: $session, userIpWhitelist: [BackendPageRenderer::IP_ADDRESS]);

        $this->assertStringContainsString(
            '</html>',
            $renderer->render(languageCode: 'de', fileTitle: 'profile', pathVars: [], session: $session),
        );
    }

    public function testUserWithMoreRightsCannotBeEdited(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
        $session = new Session(storage: new ArraySessionStorage());
        $this->logIn(renderer: $renderer, session: $session);
        $userId = $this->createUserWithProjectRight();

        $refused = [];
        foreach (['userMod', 'userDelete', 'userImpersonate', 'userInvite'] as $fileTitle) {
            try {
                $renderer->render(languageCode: 'de', fileTitle: $fileTitle, pathVars: [(string) $userId], session: $session);
            } catch (NotFoundException) {
                $refused[] = $fileTitle;
            }
        }

        $this->assertSame(['userMod', 'userDelete', 'userImpersonate', 'userInvite'], $refused);
    }

    public function testGroupsWithMoreRightsAreNotOffered(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
        $session = new Session(storage: new ArraySessionStorage());
        $this->createUserWithProjectRight();
        $this->logIn(renderer: $renderer, session: $session);

        $html = $renderer->render(languageCode: 'de', fileTitle: 'userAdd', pathVars: [], session: $session);

        $this->assertStringContainsString('Administrator', $html);
        $this->assertStringNotContainsString('Project ', $html);
    }

    public function testNobodyDeletesHimself(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
        $session = new Session(storage: new ArraySessionStorage());
        $userId = $this->logIn(renderer: $renderer, session: $session);

        $this->expectException(NotFoundException::class);
        $renderer->render(languageCode: 'de', fileTitle: 'userDelete', pathVars: [(string) $userId], session: $session);
    }

    public function testImpersonationNeedsAConfirmedPost(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
        $session = new Session(storage: new ArraySessionStorage());
        $this->logIn(renderer: $renderer, session: $session);
        $authSession = new AuthSession(session: $session);
        $ownSessionId = $authSession->getAuthSessionId();
        $otherUserId = $this->testUsers->create(email: AccessControlTest::email());

        $userPage = $renderer->render(languageCode: 'de', fileTitle: 'user', pathVars: [(string) $otherUserId], session: $session);
        $this->assertStringContainsString('href="/backend/userImpersonate-' . $otherUserId . '.html"', $userPage);
        $renderer->render(languageCode: 'de', fileTitle: 'userImpersonate', pathVars: [(string) $otherUserId], session: $session);
        $this->assertSame($ownSessionId, $authSession->getAuthSessionId(), 'GET does not switch the session.');

        try {
            $renderer->render(
                languageCode: 'de',
                fileTitle: 'userImpersonate',
                pathVars: [(string) $otherUserId],
                session: $session,
                postParameters: [CsrfTokenSource::FIELD_NAME => ViewContextFactory::csrfToken(session: $session)],
                formName: ConfirmationForm::NAME,
            );
            AccessControlTest::fail('The confirmed impersonation redirects.');
        } catch (ResponseSentException) {
        }
        $impersonationSessionId = $authSession->getAuthSessionId();
        $this->assertNotSame($ownSessionId, $impersonationSessionId);
        $this->assertSame(
            $ownSessionId,
            $renderer->actraBackend->getRepositories()->sessions()->selectById(id: $impersonationSessionId)?->parentId,
        );
    }

    public function testCancelSessionChangeNeedsAConfirmedPost(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
        $session = new Session(storage: new ArraySessionStorage());
        $this->logIn(renderer: $renderer, session: $session);
        $authSession = new AuthSession(session: $session);
        $ownSessionId = $authSession->getAuthSessionId();
        $authSession->logIn(
            authSessionId: $renderer->actraBackend->getRepositories()->sessions()->insert(
                parentId: $ownSessionId,
                userId: $this->testUsers->create(email: AccessControlTest::email()),
                clientData: BackendPageRenderer::createClientData(),
            ),
        );

        foreach (['de', 'en'] as $languageCode) {
            $html = $renderer->render(languageCode: $languageCode, fileTitle: 'userImpersonateEnd', pathVars: [], session: $session);
            $this->assertStringContainsString('name="confirm"', $html);
        }
        $this->assertNotSame($ownSessionId, $authSession->getAuthSessionId(), 'GET does not end the impersonation.');
        try {
            $renderer->render(
                languageCode: 'de',
                fileTitle: 'userImpersonateEnd',
                pathVars: [],
                session: $session,
                postParameters: [CsrfTokenSource::FIELD_NAME => ViewContextFactory::csrfToken(session: $session)],
                formName: ConfirmationForm::NAME,
            );
            AccessControlTest::fail('The confirmed end of the impersonation redirects.');
        } catch (ResponseSentException) {
        }
        $this->assertSame($ownSessionId, $authSession->getAuthSessionId());
    }

    public function testCancelSessionChangeWithoutImpersonationIsNotFound(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
        $session = new Session(storage: new ArraySessionStorage());
        $this->logIn(renderer: $renderer, session: $session);

        $this->expectException(NotFoundException::class);
        $renderer->render(languageCode: 'de', fileTitle: 'userImpersonateEnd', pathVars: [], session: $session);
    }

    public function testImpersonationEndsWhenTheImpersonatorLosesTheRight(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
        $session = new Session(storage: new ArraySessionStorage());
        $adminId = $this->logIn(renderer: $renderer, session: $session);
        $authSession = new AuthSession(session: $session);
        $otherUserId = $this->testUsers->create(email: AccessControlTest::email());
        $authSession->logIn(
            authSessionId: $renderer->actraBackend->getRepositories()->sessions()->insert(
                parentId: $authSession->getAuthSessionId(),
                userId: $otherUserId,
                clientData: BackendPageRenderer::createClientData(),
            ),
        );
        $this->db->execute(sql: 'UPDATE auth_user SET active=0 WHERE id=?', parameters: [$adminId]);

        try {
            $renderer->render(languageCode: 'de', fileTitle: 'profile', pathVars: [], session: $session);
            AccessControlTest::fail('The impersonation goes on.');
        } catch (UnauthorizedException) {
        }
        $this->assertFalse($authSession->isLoggedIn());
    }
}
