<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\backend\ActraBackend;
use actra\backend\libs\db\ClientData;
use actra\yuf\auth\AuthSession;
use actra\yuf\clock\SystemClock;
use actra\yuf\common\AcceptingMailDomainResolver;
use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\core\ResolvedRoute;
use actra\yuf\core\Route;
use actra\yuf\db\DbSettings;
use actra\yuf\form\FormContext;
use actra\yuf\security\CspNonce;
use actra\yuf\security\SessionCsrfTokenSource;
use actra\yuf\session\Session;
use actra\yuf\template\cache\DirectoryTemplateCache;
use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\TemplateEngine;
use LogicException;

/**
 * Renders a page of the backend like yuf's `Core` (view, content template and page template) on `/backend/`, in German
 * or English as the language of the request, with the API turned on.
 */
final readonly class BackendPageRenderer
{
    public const string IP_ADDRESS = '192.0.2.20';
    public ActraBackend $actraBackend;
    private Route $route;
    private LanguageCollection $languageCollection;

    /**
     * @param list<string> $ipWhitelist The global IP whitelist of the backend
     */
    public function __construct(DbSettings $dbSettings, array $ipWhitelist = [])
    {
        $this->languageCollection = new LanguageCollection(languages: [
            new Language(code: 'de', locale: 'de_CH.UTF-8'),
            new Language(code: 'en', locale: 'en_GB.UTF-8'),
        ]);
        $this->actraBackend = ActraBackendTestInstance::create(
            dbSettings: $dbSettings,
            languages: $this->languageCollection,
            hasApi: true,
            ipWhitelist: $ipWhitelist,
        );
        $this->route = array_first(array: $this->actraBackend->createRoutes(viewDirectory: __DIR__ . '/view/'))
            ?? throw new LogicException(message: 'The backend has no route');
    }

    /**
     * @param string $languageCode The language of the request (as resolved by yuf's `RequestLanguageResolver`)
     * @param list<string> $pathVars The path variables after the file title (`user-5.html`: `['5']`)
     * @param array<string, string> $postParameters A POST request with these values, a GET request without
     * @param ?string $formName The form that is sent (the query of its action, `?FormName`)
     * @param array<string, string> $queryParameters The query of the request
     * @param RecordingResponseSender $responseSender Keeps the response of a view that ends with a redirect
     */
    public function render(
        string $languageCode,
        string $fileTitle,
        array $pathVars,
        Session $session,
        array $postParameters = [],
        ?string $formName = null,
        array $queryParameters = [],
        RecordingResponseSender $responseSender = new RecordingResponseSender(),
    ): string {
        $route = $this->route;
        $language = $this->languageCollection->getLanguageByCode(languageCode: $languageCode)
            ?? throw new LogicException(message: 'No language ' . $languageCode);
        $fileName = implode(separator: '-', array: [$fileTitle, ...$pathVars]) . '.html';
        $queryParameters = $formName === null ? $queryParameters : [$formName => '', ...$queryParameters];
        $queryString = http_build_query(data: $queryParameters, encoding_type: PHP_QUERY_RFC3986);
        $httpRequest = new HttpRequest(
            host: 'example.com',
            method: $postParameters === [] ? RequestMethodEnum::GET : RequestMethodEnum::POST,
            uri: $route->path . $fileName . ($queryString === '' ? '' : '?' . $queryString),
            queryString: $queryString,
            remoteAddress: BackendPageRenderer::IP_ADDRESS,
            queryParameters: $queryParameters,
            postParameters: $postParameters,
        );
        $localeHandler = new LocaleHandler(language: $language, availableLanguages: $this->languageCollection);
        $contentHandler = new ContentHandler(
            contentType: ContentType::createHtml(),
            cspNonce: new CspNonce(value: 'nonce'),
        );
        $contentHandler->processRequest(
            resolvedRoute: new ResolvedRoute(
                route: $route,
                language: $language,
                fileName: $fileName,
                fileGroup: null,
                fileTitle: $fileTitle,
                fileExtension: ContentType::HTML,
                routeVariables: [],
                pathVars: [$fileTitle, ...$pathVars],
            ),
            localeHandler: $localeHandler,
            templateEngine: new TemplateEngine(
                cache: new DirectoryTemplateCache(
                    cacheDirectory: sys_get_temp_dir() . '/actra-backend-tests',
                    templateBaseDirectory: __DIR__ . '/../../src',
                ),
                tags: TemplateTagCollection::createDefault(
                    localeHandler: $localeHandler,
                    snippetsDirectory: __DIR__,
                    clock: new SystemClock(),
                ),
            ),
            httpRequest: $httpRequest,
            session: $session,
            sessionHandler: null,
            // With CSRF token like yuf's Core on a route with session
            formContext: new FormContext(
                httpRequest: $httpRequest,
                csrfTokenSource: new SessionCsrfTokenSource(session: $session),
                mailDomainResolver: new AcceptingMailDomainResolver(),
            ),
            documentRoot: '/tmp/public/',
            copyright: '2026',
            robots: 'noindex, nofollow',
            responseSender: $responseSender,
            navigationProvider: $this->actraBackend->createNavigation(...),
        );

        return $contentHandler->getContent();
    }

    /**
     * Logs the user in on the session, as after a successful login.
     */
    public function logIn(Session $session, int $userId): void
    {
        new AuthSession(session: $session)->logIn(
            authSessionId: $this->actraBackend->getRepositories()->sessions()->insert(
                parentId: null,
                userId: $userId,
                clientData: BackendPageRenderer::createClientData(),
            ),
        );
    }

    public static function createClientData(): ClientData
    {
        return new ClientData(userAgent: '', ipAddress: BackendPageRenderer::IP_ADDRESS, sessionId: '');
    }
}
