<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\auth;

use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\Route;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The page after the login: the validated `returnTo` target of yuf's login redirect, otherwise the first page of the
 * navigation.
 */
final class LoginReturnPathTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function returnTargets(): iterable
    {
        yield 'no target' => [null, '/backend/users.html?reset'];
        yield 'local path' => ['/backend/user-5.html', '/backend/user-5.html'];
        yield 'other host' => ['https://example.org/', '/backend/users.html?reset'];
        yield 'protocol-relative URL' => ['//example.org/', '/backend/users.html?reset'];
    }

    #[DataProvider('returnTargets')]
    public function testFirstAllowedPage(?string $returnTo, string $expected): void
    {
        $actraBackend = ActraBackendTestInstance::create();
        $context = $actraBackend->createContext(
            viewContext: ViewContextFactory::create(
                route: new Route(path: '/backend/', viewDirectory: __DIR__),
                fileTitle: 'loginToken',
                httpRequest: new HttpRequest(
                    host: 'example.com',
                    uri: '/backend/loginToken.html',
                    queryParameters: $returnTo === null ? [] : ['returnTo' => $returnTo],
                ),
                navigationProvider: $actraBackend->createNavigation(...),
            ),
        );
        $myAuthUser = new MyAuthUser(
            dbAuthUser: new DbAuthUser(
                id: 1,
                registered: new DateTimeImmutable(),
                invitedDate: null,
                lastLogin: null,
                email: 'user@example.com',
                phone: '',
                isActive: true,
                accessRightCollection: AccessRightCollection::createFromStringArray(input: ['backend_access', 'manage_users']),
                firstName: 'Test',
                lastName: 'User',
                languageCode: null,
                password: null,
                wrongLoginAttempts: 0,
                ipWhitelist: [],
            ),
            parentSessionId: null,
            repositories: $context->repositories,
            clientData: $context->clientData,
        );

        $this->assertSame($expected, $myAuthUser->getFirstAllowedPage(context: $context));
    }
}
