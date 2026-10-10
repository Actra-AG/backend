<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\ActraBackend;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\RecordingMailer;
use actra\backend\tests\Double\RecordingResponseSender;
use actra\backend\tests\Double\ResponseSentException;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\core\ResolvedRoute;
use actra\yuf\core\RouteCollection;
use actra\yuf\form\FormContext;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\security\SessionCsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\template\cache\DirectoryTemplateCache;
use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\TemplateEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Both login flows through the views: every step answers 303 and carries the page requested before the login.
 */
final class LoginRedirectTest extends TestCase
{
    private const string PASSWORD = 'correct horse battery staple';
    private const string ORIGIN = 'https://example.com';
    private ActraBackend $actraBackend;
    private RouteCollection $routeCollection;
    private TestUsers $testUsers;
    private Session $session;
    private RecordingMailer $mailer;
    private RecordingResponseSender $lastResponseSender;

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
        $this->routeCollection = new RouteCollection(loginPath: '/backend/login.html');
        $this->mailer = new RecordingMailer();
        $this->lastResponseSender = new RecordingResponseSender();
        $this->actraBackend = ActraBackendTestInstance::create(
            dbSettings: TestDatabase::settings(),
            routeCollection: $this->routeCollection,
            mailer: $this->mailer,
        );
        $this->session = new Session(storage: new ArraySessionStorage());
    }

    /**
     * Processes a request with a posted form like yuf's `Core` and returns the target of its 303 redirect.
     *
     * @param array<string, string> $postParameters
     */
    private function post(string $fileTitle, string $formName, array $postParameters): string
    {
        $responseSender = new RecordingResponseSender();
        $this->lastResponseSender = $responseSender;
        $httpRequest = new HttpRequest(
            host: 'example.com',
            method: RequestMethodEnum::POST,
            uri: '/backend/' . $fileTitle . '.html?' . $formName,
            queryString: $formName,
            remoteAddress: '192.0.2.10',
            queryParameters: [$formName => ''],
            postParameters: [CsrfTokenSource::FIELD_NAME => ViewContextFactory::csrfToken(session: $this->session)]
                + $postParameters,
        );
        $route = $this->routeCollection->getFirstRoute();
        try {
            new ContentHandler(contentType: ContentType::createHtml(), cspNonce: new CspNonce(value: 'nonce'))
                ->processRequest(
                    resolvedRoute: new ResolvedRoute(
                        route: $route,
                        language: $route->language,
                        fileName: $fileTitle . '.html',
                        fileGroup: null,
                        fileTitle: $fileTitle,
                        fileExtension: 'html',
                        routeVariables: [],
                        pathVars: [$fileTitle],
                    ),
                    localeHandler: new LocaleHandler(language: null, availableLanguages: new LanguageCollection()),
                    templateEngine: new TemplateEngine(
                        cache: new DirectoryTemplateCache(
                            cacheDirectory: sys_get_temp_dir(),
                            templateBaseDirectory: __DIR__,
                        ),
                        tags: new TemplateTagCollection(),
                    ),
                    httpRequest: $httpRequest,
                    session: $this->session,
                    sessionHandler: null,
                    formContext: new FormContext(
                        httpRequest: $httpRequest,
                        csrfTokenSource: new SessionCsrfTokenSource(session: $this->session),
                    ),
                    documentRoot: '/tmp/public/',
                    copyright: '',
                    robots: '',
                    responseSender: $responseSender,
                    navigationProvider: $this->actraBackend->createNavigation(...),
                );
        } catch (ResponseSentException) {
        }
        $response = $responseSender->sentResponse ?? LoginRedirectTest::fail('No response of ' . $fileTitle);
        $this->assertSame(HttpStatusCodeEnum::HTTP_SEE_OTHER, $response->httpStatusCode);

        return $response->getHeader(key: 'Location') ?? '';
    }

    private static function email(): string
    {
        return 'redirect-' . bin2hex(string: random_bytes(length: 4)) . '@example.com';
    }

    private function token(): string
    {
        return $this->mailer->sendAndGetLoginCode(responseSender: $this->lastResponseSender);
    }

    /**
     * @return iterable<string, array{string, string, string}> returnTo, query of the token step, final target
     */
    public static function returnTargets(): iterable
    {
        yield 'requested page' => [
            '/backend/user-5.html',
            '?returnTo=%2Fbackend%2Fuser-5.html',
            '/backend/user-5.html',
        ];
        yield 'external target is dropped' => ['https://example.org/', '', '/backend/users.html?reset'];
        yield 'no target' => ['', '', '/backend/users.html?reset'];
    }

    #[DataProvider('returnTargets')]
    public function testTokenLogin(string $returnTo, string $expectedQuery, string $expectedTarget): void
    {
        $email = LoginRedirectTest::email();
        $this->testUsers->create(email: $email);

        $this->assertSame(
            LoginRedirectTest::ORIGIN . '/backend/loginToken.html' . $expectedQuery,
            $this->post(fileTitle: 'login', formName: 'LoginForm', postParameters: [
                'email' => $email,
                'returnTo' => $returnTo,
            ]),
        );
        $this->assertSame(
            LoginRedirectTest::ORIGIN . $expectedTarget,
            $this->post(fileTitle: 'loginToken', formName: 'LoginTokenForm', postParameters: [
                'token' => $this->token(),
                'returnTo' => $expectedQuery === '' ? '' : $returnTo,
            ]),
        );
    }

    #[DataProvider('returnTargets')]
    public function testPasswordAndTokenLogin(string $returnTo, string $expectedQuery, string $expectedTarget): void
    {
        $email = LoginRedirectTest::email();
        $this->testUsers->create(email: $email, password: LoginRedirectTest::PASSWORD);

        $this->assertSame(
            LoginRedirectTest::ORIGIN . '/backend/loginPasswordToken.html' . $expectedQuery,
            $this->post(fileTitle: 'loginPassword', formName: 'LoginPasswordForm', postParameters: [
                'email' => $email,
                'password' => LoginRedirectTest::PASSWORD,
                'returnTo' => $returnTo,
            ]),
        );
        $this->assertSame(
            LoginRedirectTest::ORIGIN . $expectedTarget,
            $this->post(fileTitle: 'loginPasswordToken', formName: 'LoginTokenForm', postParameters: [
                'token' => $this->token(),
                'returnTo' => $expectedQuery === '' ? '' : $returnTo,
            ]),
        );
    }
}
