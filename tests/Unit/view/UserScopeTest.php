<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\ActraBackend;
use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\auth\UserController;
use actra\backend\libs\auth\UserScopeGuard;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\ListUserScope;
use actra\backend\tests\Double\ResponseSentException;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\auth\AuthSession;
use actra\yuf\clock\SystemClock;
use actra\yuf\db\DbRow;
use actra\yuf\db\FrameworkDb;
use actra\yuf\exception\NotFoundException;
use actra\yuf\mailer\RecordingMailer;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The built-in user management restricted by a `UserScope` (`ActraBackendSettings::$userScope`): an actor sees and
 * changes only the users and groups of the scope.
 */
final class UserScopeTest extends TestCase
{
    private FrameworkDb $db;
    private TestUsers $testUsers;
    /** The actor: logged in, may manage users (group 1, hidden from the scope) */
    private int $actorId;
    /** A group with backend access, visible in the scope */
    private int $staffGroupId;

    #[\Override]
    protected function setUp(): void
    {
        $this->db = TestDatabase::connect();
        $this->testUsers = new TestUsers(db: $this->db);
        $this->actorId = $this->createUser(name: 'actor');
        $this->db->execute(sql: 'INSERT INTO auth_groups SET title=?', parameters: ['Staff ' . UserScopeTest::suffix()]);
        $this->staffGroupId = $this->db->getLastInsertId();
        $this->db->execute(
            sql: 'INSERT INTO auth_group_right SET group_id=?, right_name=?',
            parameters: [$this->staffGroupId, ActraBackend::RIGHT_BACKEND_ACCESS],
        );
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function userViews(): iterable
    {
        $views = ['user', 'userMod', 'userDelete', 'userInvite', 'userImpersonate', 'userGenerateApiKey',
            'userRemoveApiKey', 'tokens', 'visits'];
        foreach ($views as $fileTitle) {
            yield $fileTitle . ' GET' => [$fileTitle, false];
            yield $fileTitle . ' POST' => [$fileTitle, true];
        }
    }

    #[DataProvider('userViews')]
    public function testUserOutsideTheScopeIsAnsweredLikeAnUnknownId(string $fileTitle, bool $isPost): void
    {
        // Not a user manager, with IP whitelist and API key: only the scope refuses
        $outsideUserId = $this->createUser(name: 'outside', ipWhitelist: ['192.0.2.1'], groupId: $this->staffGroupId);
        $renderer = $this->createRenderer(scope: new ListUserScope(userIds: [], groupIds: [$this->staffGroupId]));
        $renderer->actraBackend->getRepositories()->apiKeys()->createForUserId(userId: $outsideUserId);
        $unknownId = $outsideUserId + 1000000;

        $notFoundIds = [];
        foreach ([$outsideUserId, $unknownId] as $userId) {
            try {
                $this->renderAsActor(renderer: $renderer, fileTitle: $fileTitle, userId: $userId, isPost: $isPost);
            } catch (NotFoundException) {
                $notFoundIds[] = $userId;
            }
        }
        $this->assertSame([$outsideUserId, $unknownId], $notFoundIds);
        $this->assertNotNull($renderer->actraBackend->getRepositories()->users()->selectById(id: $outsideUserId));
    }

    public function testUserInTheScopeIsShown(): void
    {
        $insideUserId = $this->createUser(name: 'inside');
        $renderer = $this->createRenderer(
            scope: new ListUserScope(userIds: [$insideUserId], groupIds: [$this->staffGroupId]),
        );

        $html = $this->renderAsActor(renderer: $renderer, fileTitle: 'user', userId: $insideUserId, isPost: false);

        $this->assertStringContainsString($this->email(userId: $insideUserId), $html);
    }

    public function testListsShowOnlyTheUsersOfTheScope(): void
    {
        $insideUserId = $this->createUser(name: 'inside');
        $outsideUserId = $this->createUser(name: 'outside');
        $renderer = $this->createRenderer(
            scope: new ListUserScope(userIds: [$insideUserId], groupIds: [$this->staffGroupId]),
        );

        $html = $this->renderAsActor(renderer: $renderer, fileTitle: 'users', userId: null, isPost: false);

        $this->assertStringContainsString($this->email(userId: $insideUserId), $html);
        $this->assertStringNotContainsString($this->email(userId: $outsideUserId), $html);
        // The group filter offers only the groups of the scope
        $this->assertStringNotContainsString('<option value="1"', $html);
        $this->assertStringContainsString('<option value="' . $this->staffGroupId . '"', $html);
    }

    public function testTheVisitsShowOnlyTheLoginsOfTheScope(): void
    {
        $insideUserId = $this->createUser(name: 'inside');
        $outsideUserId = $this->createUser(name: 'outside');
        $unknownEmail = 'scope-unknown-' . UserScopeTest::suffix() . '@example.com';
        foreach ([
            [$insideUserId, $this->email(userId: $insideUserId)],
            [$outsideUserId, $this->email(userId: $outsideUserId)],
            [null, $unknownEmail],
        ] as [$userId, $email]) {
            $this->db->execute(
                sql: 'INSERT INTO auth_logins SET user_id=?, php_session_id=\'\', ip_address=\'192.0.2.1\', email=?, result=1',
                parameters: [$userId, $email],
            );
        }
        $renderer = $this->createRenderer(
            scope: new ListUserScope(userIds: [$insideUserId], groupIds: [$this->staffGroupId]),
        );

        $html = $this->renderAsActor(renderer: $renderer, fileTitle: 'visits', userId: null, isPost: false);

        $this->assertStringContainsString($this->email(userId: $insideUserId), $html);
        $this->assertStringNotContainsString($this->email(userId: $outsideUserId), $html);
        $this->assertStringNotContainsString($unknownEmail, $html);
    }

    public function testANotificationReachesOnlyTheUsersOfTheScope(): void
    {
        $insideUserId = $this->createUser(name: 'inside', groupId: $this->staffGroupId);
        $outsideUserId = $this->createUser(name: 'outside', groupId: $this->staffGroupId);
        $mailer = new RecordingMailer(clock: new SystemClock());
        $renderer = new BackendPageRenderer(
            dbSettings: TestDatabase::settings(),
            mailer: $mailer,
            userScope: new ListUserScope(userIds: [$insideUserId], groupIds: [$this->staffGroupId]),
        );

        $this->renderAsActor(
            renderer: $renderer,
            fileTitle: 'notificationSend',
            userId: null,
            isPost: true,
            formName: 'NotificationSendForm',
            postParameters: [
                'authUserGroupField' => (string) $this->staffGroupId,
                'subjectField' => 'Subject',
                'messageField' => 'Message',
                'save' => '',
            ],
        );

        $recipientIds = array_map(
            static fn(DbRow $row): int => $row->getInt(column: 'user_id'),
            $this->db->selectRows(
                sql: 'SELECT user_id FROM auth_user_notification_recipients WHERE user_id IN (?, ?)',
                parameters: [$insideUserId, $outsideUserId],
            ),
        );
        $this->assertSame([$insideUserId], $recipientIds);
        $this->assertCount(1, $mailer->sentMails);
    }

    public function testAnUnrestrictedActorManagesAllUsers(): void
    {
        $outsideUserId = $this->createUser(name: 'outside');
        $renderer = $this->createRenderer(scope: new ListUserScope(userIds: [], groupIds: [], restricts: false));

        $html = $this->renderAsActor(renderer: $renderer, fileTitle: 'user', userId: $outsideUserId, isPost: false);

        $this->assertStringContainsString($this->email(userId: $outsideUserId), $html);
        $this->assertStringContainsString('sentEmails.html', $html);
    }

    public function testSentEmailsAreHiddenFromARestrictedActor(): void
    {
        $renderer = $this->createRenderer(scope: new ListUserScope(userIds: [], groupIds: []));

        $html = $this->renderAsActor(renderer: $renderer, fileTitle: 'profile', userId: null, isPost: false);
        $this->assertStringNotContainsString('sentEmails.html', $html);
        $this->expectException(NotFoundException::class);
        $this->renderAsActor(renderer: $renderer, fileTitle: 'sentEmails', userId: null, isPost: false);
    }

    public function testANewUserIsLinkedToTheScope(): void
    {
        $scope = new ListUserScope(userIds: [], groupIds: [$this->staffGroupId]);
        $email = 'scope-new-' . UserScopeTest::suffix() . '@example.com';

        $this->postUserAdd(scope: $scope, email: $email, groupId: $this->staffGroupId);

        $newUser = $this->testUsersRepository()->selectByEmail(email: $email);
        $this->assertNotNull($newUser);
        $this->assertSame([$newUser->id], $scope->userIds);
    }

    public function testAFailingLinkRollsTheNewUserBack(): void
    {
        $scope = new ListUserScope(userIds: [], groupIds: [$this->staffGroupId], failsOnCreate: true);
        $email = 'scope-failed-' . UserScopeTest::suffix() . '@example.com';

        try {
            $this->postUserAdd(scope: $scope, email: $email, groupId: $this->staffGroupId);
            self::fail('The link did not fail');
        } catch (RuntimeException) {
            $this->assertNull($this->testUsersRepository()->selectByEmail(email: $email));
        }
    }

    public function testAGroupOutsideTheScopeCannotBeAssigned(): void
    {
        $scope = new ListUserScope(userIds: [], groupIds: [$this->staffGroupId]);
        $email = 'scope-group-' . UserScopeTest::suffix() . '@example.com';

        $this->postUserAdd(scope: $scope, email: $email, groupId: 1);

        $this->assertNull($this->testUsersRepository()->selectByEmail(email: $email));
    }

    public function testASaveKeepsTheGroupsOutsideTheScope(): void
    {
        // In group 1, which the scope does not show
        $insideUserId = $this->createUser(name: 'inside');
        $renderer = $this->createRenderer(
            scope: new ListUserScope(userIds: [$insideUserId], groupIds: [$this->staffGroupId]),
        );

        $this->renderAsActor(
            renderer: $renderer,
            fileTitle: 'userMod',
            userId: $insideUserId,
            isPost: true,
            formName: 'UserModForm-' . $insideUserId,
            postParameters: [
                'firstName' => 'Test',
                'lastName' => 'User',
                'email' => $this->email(userId: $insideUserId),
                'phone' => '',
                'active' => ['checked'],
                'userGroups' => [(string) $this->staffGroupId],
                'ipWhitelistField' => '',
                'save' => '',
            ],
        );

        $groupIds = $renderer->actraBackend->getRepositories()->groups()->listByUserId(userId: $insideUserId)->listIds();
        sort(array: $groupIds);
        $this->assertSame([1, $this->staffGroupId], $groupIds);
    }

    public function testTheLastUserManagerIsCountedWithinTheScope(): void
    {
        // Group 1 manages users; the actor is outside the scope, so the manager inside is the last one there
        $managerId = $this->createUser(name: 'manager');
        $scope = new ListUserScope(userIds: [$managerId], groupIds: [$this->staffGroupId]);

        $isRefused = false;
        try {
            $this->renderAsActor(
                renderer: $this->createRenderer(scope: $scope),
                fileTitle: 'userDelete',
                userId: $managerId,
                isPost: false,
            );
        } catch (NotFoundException) {
            $isRefused = true;
        }
        $this->assertTrue($isRefused, 'The last user manager of the scope can be deleted');
        $html = $this->renderAsActor(
            renderer: $this->createRenderer(scope: null),
            fileTitle: 'userDelete',
            userId: $managerId,
            isPost: false,
        );
        $this->assertStringContainsString('<title>Benutzer löschen', $html);
    }

    public function testTheControllerDeletesNoUserOutsideTheScope(): void
    {
        $outsideUserId = $this->createUser(name: 'outside');
        $repositories = $this->createRenderer(scope: null)->actraBackend->getRepositories();
        $actor = new MyAuthUser(
            dbAuthUser: $repositories->users()->selectById(id: $this->actorId) ?? self::fail('No actor'),
            parentSessionId: null,
            repositories: $repositories,
            clientData: BackendPageRenderer::createClientData(),
        );
        $userController = new UserController(
            repositories: $repositories,
            userDeleteHandler: null,
            authSession: new AuthSession(session: new Session(storage: new ArraySessionStorage())),
            currentUser: $actor,
            userScopeGuard: new UserScopeGuard(
                userScope: new ListUserScope(userIds: [], groupIds: []),
                actor: $actor,
                repositories: $repositories,
            ),
        );

        try {
            $userController->deleteUser(userId: $outsideUserId);
            self::fail('A user outside the scope was deleted');
        } catch (NotFoundException) {
            $this->assertNotNull($repositories->users()->selectById(id: $outsideUserId));
        }
    }

    private static function suffix(): string
    {
        return bin2hex(string: random_bytes(length: 4));
    }

    private function createRenderer(?ListUserScope $scope): BackendPageRenderer
    {
        return new BackendPageRenderer(dbSettings: TestDatabase::settings(), userScope: $scope);
    }

    /**
     * @param list<string> $ipWhitelist
     * @param int $groupId Group 1 manages users
     */
    private function createUser(
        string $name,
        array $ipWhitelist = [BackendPageRenderer::IP_ADDRESS],
        int $groupId = 1,
    ): int {
        $userId = $this->testUsers->create(
            email: 'scope-' . $name . '-' . UserScopeTest::suffix() . '@example.com',
            withRights: $groupId === 1,
            ipWhitelist: $ipWhitelist,
        );
        if ($groupId !== 1) {
            $this->db->execute(
                sql: 'INSERT INTO auth_group_user SET user_id=?, group_id=?',
                parameters: [$userId, $groupId],
            );
        }

        return $userId;
    }

    private function email(int $userId): string
    {
        return ($this->testUsersRepository()->selectById(id: $userId) ?? self::fail('No user ' . $userId))->email;
    }

    private function testUsersRepository(): DbAuthUserRepository
    {
        return $this->createRenderer(scope: null)->actraBackend->getRepositories()->users();
    }

    private function postUserAdd(ListUserScope $scope, string $email, int $groupId): void
    {
        $this->renderAsActor(
            renderer: $this->createRenderer(scope: $scope),
            fileTitle: 'userAdd',
            userId: null,
            isPost: true,
            formName: 'UserAddForm',
            postParameters: [
                'firstName' => 'New',
                'lastName' => 'User',
                'email' => $email,
                'phone' => '',
                'active' => ['checked'],
                'userGroups' => [(string) $groupId],
                'ipWhitelistField' => '',
                'save' => '',
            ],
        );
    }

    /**
     * @param array<string, string|list<string>> $postParameters
     */
    private function renderAsActor(
        BackendPageRenderer $renderer,
        string $fileTitle,
        ?int $userId,
        bool $isPost,
        ?string $formName = null,
        array $postParameters = ['confirm' => ''],
    ): string {
        $session = new Session(storage: new ArraySessionStorage());
        $renderer->logIn(session: $session, userId: $this->actorId);
        $csrfToken = ViewContextFactory::csrfToken(session: $session);

        try {
            return $renderer->render(
                languageCode: 'de',
                fileTitle: $fileTitle,
                pathVars: $userId === null ? [] : [(string) $userId],
                session: $session,
                postParameters: $isPost ? [CsrfTokenSource::FIELD_NAME => $csrfToken, ...$postParameters] : [],
                formName: $formName,
                queryParameters: [CsrfTokenSource::FIELD_NAME => $csrfToken],
            );
        } catch (ResponseSentException) {
            // A saved form redirects
            return '';
        }
    }
}
