<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\db\ClientData;
use actra\backend\libs\db\DbAuthToken;
use actra\backend\libs\form\PasswordResetForm;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\core\Route;
use actra\yuf\exception\NotFoundException;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * A password reset link sets a password only once and ends the sessions of the user.
 */
final class PasswordResetFormTest extends TestCase
{
    private TestUsers $testUsers;

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
    }

    private function createContext(string $newPassword): BackendViewContext
    {
        $session = new Session(storage: new ArraySessionStorage());

        return ActraBackendTestInstance::create(dbSettings: TestDatabase::settings())->createContext(
            viewContext: ViewContextFactory::create(
                route: new Route(path: '/backend/', viewDirectory: __DIR__),
                fileTitle: 'passwordReset',
                httpRequest: new HttpRequest(
                    host: 'example.com',
                    method: RequestMethodEnum::POST,
                    uri: '/backend/passwordReset.html?PasswordResetForm',
                    queryString: 'PasswordResetForm',
                    remoteAddress: '192.0.2.10',
                    queryParameters: ['PasswordResetForm' => ''],
                    postParameters: [
                        CsrfTokenSource::FIELD_NAME => ViewContextFactory::csrfToken(session: $session),
                        'newPassword' => $newPassword,
                        'newPasswordConfirm' => $newPassword,
                    ],
                ),
                session: $session,
            ),
        );
    }

    private function getClaimable(BackendViewContext $context, string $token): DbAuthToken
    {
        return $context->repositories->tokens()->getClaimable(authTokenType: AuthTokenTypeEnum::PASSWORD, token: $token)
            ?? throw new LogicException(message: 'The token is not claimable.');
    }

    public function testLinkSetsThePasswordOnlyOnce(): void
    {
        $userId = $this->testUsers->create(
            email: 'reset-' . bin2hex(string: random_bytes(length: 4)) . '@example.com',
            password: 'old password 1',
        );
        $context = $this->createContext(newPassword: 'first new password 1');
        $repositories = $context->repositories;
        $token = $repositories->tokens()->createToken(
            dbAuthUser: $repositories->users()->selectById(id: $userId)
                ?? throw new LogicException(message: 'The user was not created.'),
            authTokenTypeEnum: AuthTokenTypeEnum::PASSWORD,
            clientData: new ClientData(userAgent: '', ipAddress: '192.0.2.10', sessionId: ''),
        );
        $sessionId = $repositories->sessions()->insert(
            parentId: null,
            userId: $userId,
            clientData: new ClientData(userAgent: '', ipAddress: '192.0.2.10', sessionId: ''),
        );
        $dbAuthToken = $this->getClaimable(context: $context, token: $token);

        $this->assertTrue(new PasswordResetForm(context: $context, dbAuthToken: $dbAuthToken)->validateAndUpdatePassword());
        $this->assertNull($repositories->sessions()->selectById(id: $sessionId), 'The sessions of the user are ended.');
        $this->assertNull($repositories->tokens()->getClaimable(authTokenType: AuthTokenTypeEnum::PASSWORD, token: $token));

        // A second form with the token read before the first one was used (parallel request)
        $this->expectException(NotFoundException::class);
        new PasswordResetForm(
            context: $this->createContext(newPassword: 'second new password 2'),
            dbAuthToken: $dbAuthToken,
        )->validateAndUpdatePassword();
    }
}
