<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

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
 * The IP whitelists of the user and of the backend both apply, users who manage users manage all users (but not their
 * own deletion), and impersonation needs the CSRF token of its link.
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
        $this->db->execute(
            sql: 'INSERT INTO auth_group SET title=?',
            parameters: ['Project ' . bin2hex(string: random_bytes(length: 3))],
        );
        $groupId = $this->db->getLastInsertId();
        $this->db->execute(
            sql: 'INSERT INTO auth_group_right SET group_id=?, right_name=?',
            parameters: [$groupId, AccessControlTest::PROJECT_RIGHT],
        );
        $userId = $this->testUsers->create(email: AccessControlTest::email());
        $this->db->execute(
            sql: 'INSERT INTO auth_user_group SET user_id=?, group_id=?',
            parameters: [$userId, $groupId],
        );

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

    public function testNobodyDeletesTheirOwnAccount(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
        $session = new Session(storage: new ArraySessionStorage());
        $userId = $this->logIn(renderer: $renderer, session: $session);

        $this->expectException(NotFoundException::class);
        $renderer->render(languageCode: 'de', fileTitle: 'userDelete', pathVars: [(string) $userId], session: $session);
    }

    public function testUserManagerEditsUsersWithMoreRights(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
        $session = new Session(storage: new ArraySessionStorage());
        $this->logIn(renderer: $renderer, session: $session);
        $userId = $this->createUserWithProjectRight();

        $html = $renderer->render(
            languageCode: 'de',
            fileTitle: 'userMod',
            pathVars: [(string) $userId],
            session: $session,
        );

        // The group with the right the editor lacks is offered and checked, so saving keeps it
        $this->assertMatchesRegularExpression(
            '/<input type="checkbox"[^>]* value="\d+" checked[^>]*>\s*<label[^>]*>Project /',
            $html,
        );
    }

    /**
     * The CSRF token in the query of the impersonation link of the user page.
     */
    private static function findImpersonateToken(string $userPage, int $userId): string
    {
        $pattern = '/href="\/backend\/userImpersonate-' . $userId . '\.html\?'
            . CsrfTokenSource::FIELD_NAME . '=([^"&]+)"/';
        if (
            preg_match(pattern: $pattern, subject: $userPage, matches: $matches) !== 1
            || !array_key_exists(key: 1, array: $matches)
        ) {
            AccessControlTest::fail('The user page has no impersonation link with token.');
        }

        return rawurldecode(string: html_entity_decode(string: $matches[1]));
    }

    public function testImpersonationNeedsTheTokenOfTheLink(): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
        $session = new Session(storage: new ArraySessionStorage());
        $this->logIn(renderer: $renderer, session: $session);
        $authSession = new AuthSession(session: $session);
        $ownSessionId = $authSession->getAuthSessionId();
        $otherUserId = $this->testUsers->create(email: AccessControlTest::email());
        $token = AccessControlTest::findImpersonateToken(
            userPage: $renderer->render(
                languageCode: 'de',
                fileTitle: 'user',
                pathVars: [(string) $otherUserId],
                session: $session,
            ),
            userId: $otherUserId,
        );

        try {
            $renderer->render(
                languageCode: 'de',
                fileTitle: 'userImpersonate',
                pathVars: [(string) $otherUserId],
                session: $session,
            );
            AccessControlTest::fail('Impersonation without token.');
        } catch (NotFoundException) {
        }
        $this->assertSame($ownSessionId, $authSession->getAuthSessionId());

        try {
            $renderer->render(
                languageCode: 'de',
                fileTitle: 'userImpersonate',
                pathVars: [(string) $otherUserId],
                session: $session,
                queryParameters: [CsrfTokenSource::FIELD_NAME => $token],
            );
            AccessControlTest::fail('The impersonation redirects.');
        } catch (ResponseSentException) {
        }
        $impersonationSessionId = $authSession->getAuthSessionId();
        $this->assertNotSame($ownSessionId, $impersonationSessionId);
        $this->assertSame(
            $ownSessionId,
            $renderer->actraBackend->getRepositories()->sessions()->selectById(id: $impersonationSessionId)?->parentId,
        );
    }

    public function testCancelSessionChangeNeedsTheTokenOfTheLink(): void
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

        try {
            $renderer->render(languageCode: 'de', fileTitle: 'userImpersonateEnd', pathVars: [], session: $session);
            AccessControlTest::fail('End of the impersonation without token.');
        } catch (NotFoundException) {
        }
        try {
            $renderer->render(
                languageCode: 'de',
                fileTitle: 'userImpersonateEnd',
                pathVars: [],
                session: $session,
                queryParameters: [CsrfTokenSource::FIELD_NAME => ViewContextFactory::csrfToken(session: $session)],
            );
            AccessControlTest::fail('The end of the impersonation redirects.');
        } catch (ResponseSentException) {
        }
        $this->assertSame($ownSessionId, $authSession->getAuthSessionId());
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
