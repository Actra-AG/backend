<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\backend\libs\form\ConfirmationForm;
use actra\backend\tests\Double\AbstractDeleteThing;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\ConfirmationCalls;
use actra\backend\tests\Double\RecordingResponseSender;
use actra\backend\tests\Double\ResponseSentException;
use actra\yuf\clock\SystemClock;
use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\core\ResolvedRoute;
use actra\yuf\core\Route;
use actra\yuf\form\FormContext;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\security\SessionCsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\template\cache\DirectoryTemplateCache;
use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\TemplateEngine;
use Dom\Element;
use Dom\HTMLDocument;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * A confirmation page of a project (`AbstractDeleteThing`) on a project route, with and without file group: GET shows
 * it, only a valid POST runs the action.
 */
final class ConfirmationViewTest extends TestCase
{
    private const string FILE_TITLE = 'DeleteThing';
    private ActraBackend $actraBackend;
    private Route $route;
    private Session $session;
    private SessionCsrfTokenSource $csrfTokenSource;
    private ConfirmationCalls $confirmationCalls;
    private RecordingResponseSender $responseSender;

    #[\Override]
    protected function setUp(): void
    {
        $this->actraBackend = ActraBackendTestInstance::create();
        $this->confirmationCalls = new ConfirmationCalls();
        $confirmationCalls = $this->confirmationCalls;
        $this->route = new Route(
            path: '/project/',
            viewDirectory: __DIR__ . '/../Double/view/',
            viewClassPrefix: 'actra\\backend\\tests\\Double',
            viewGroup: 'confirmation',
            defaultContentType: ContentType::createHtml(),
            viewFactory: $this->actraBackend->createViewFactory(
                create: static fn(string $className, BackendViewContext $context): BackendView => new $className(
                    context: $context,
                    confirmationCalls: $confirmationCalls,
                ),
            ),
        );
        $this->session = new Session(storage: new ArraySessionStorage());
        $this->csrfTokenSource = new SessionCsrfTokenSource(session: $this->session);
        $this->responseSender = new RecordingResponseSender();
    }

    /**
     * Processes a request of the confirmation page like yuf's `Core` and returns the HTML of the page; a redirect is
     * kept in `$this->responseSender`.
     *
     * @param ?array<string, string> $postParameters A POST of the form with these values, a GET without
     * @param ?string $fileGroup The file group of the request (`shop/DeleteThing.html`)
     */
    private function request(
        ?array $postParameters,
        ?CsrfTokenSource $csrfTokenSource = null,
        ?string $fileGroup = null,
    ): string {
        $isPost = $postParameters !== null;
        $fileName = ($fileGroup === null ? '' : $fileGroup . '/') . ConfirmationViewTest::FILE_TITLE . '.html';
        $httpRequest = new HttpRequest(
            host: 'example.com',
            method: $isPost ? RequestMethodEnum::POST : RequestMethodEnum::GET,
            uri: '/project/' . $fileName . ($isPost ? '?' . ConfirmationForm::NAME : ''),
            queryString: $isPost ? ConfirmationForm::NAME : '',
            remoteAddress: '192.0.2.10',
            queryParameters: $isPost ? [ConfirmationForm::NAME => ''] : [],
            postParameters: $postParameters ?? [],
        );
        $localeHandler = new LocaleHandler(language: null, availableLanguages: new LanguageCollection());
        $contentHandler = new ContentHandler(
            contentType: ContentType::createHtml(),
            cspNonce: new CspNonce(value: 'nonce'),
        );
        try {
            $contentHandler->processRequest(
                resolvedRoute: new ResolvedRoute(
                    route: $this->route,
                    language: null,
                    fileName: $fileName,
                    fileGroup: $fileGroup,
                    fileTitle: ConfirmationViewTest::FILE_TITLE,
                    fileExtension: ContentType::HTML,
                    routeVariables: [],
                    pathVars: [ConfirmationViewTest::FILE_TITLE],
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
                session: $this->session,
                sessionHandler: null,
                formContext: new FormContext(httpRequest: $httpRequest, csrfTokenSource: $csrfTokenSource),
                documentRoot: '/tmp/public/',
                copyright: '2026',
                robots: 'noindex, nofollow',
                responseSender: $this->responseSender,
                navigationProvider: null,
            );
        } catch (ResponseSentException) {
            return '';
        }

        return $contentHandler->getContent();
    }

    /**
     * @param ?array<string, string> $postParameters
     */
    private function requestWithToken(?array $postParameters, ?string $fileGroup = null): string
    {
        return $this->request(
            postParameters: $postParameters,
            csrfTokenSource: $this->csrfTokenSource,
            fileGroup: $fileGroup,
        );
    }

    /**
     * The form the dialog of the backend submits (`data-form="main form"`).
     */
    private static function findDialogForm(string $html): Element
    {
        $form = HTMLDocument::createFromString(source: $html, options: LIBXML_NOERROR)->querySelector(
            selectors: 'main form',
        );

        return $form ?? throw new LogicException(message: 'The page has no form in <main>.');
    }

    private function assertNoRedirect(): void
    {
        $this->assertNull($this->responseSender->sentResponse);
        $this->assertSame(0, $this->confirmationCalls->count);
    }

    public function testGetShowsThePageAndRunsNothing(): void
    {
        $html = $this->requestWithToken(postParameters: null);

        $this->assertNoRedirect();
        $this->assertStringContainsString('<h1>Delete thing</h1>', $html);
        $this->assertStringContainsString('<p>Really delete &lt;Thing &amp; Co&gt;?</p>', $html);
        $this->assertStringContainsString('<button type="submit" name="confirm">Delete</button>', $html);
        $this->assertStringContainsString('href="' . AbstractDeleteThing::CANCEL_LINK . '"', $html);
    }

    public function testDialogFindsThePostFormWithTheTokenFirst(): void
    {
        $form = ConfirmationViewTest::findDialogForm(html: $this->requestWithToken(postParameters: null));

        $this->assertSame('post', strtolower(string: $form->getAttribute(qualifiedName: 'method') ?? ''));
        $this->assertStringContainsString(
            '?' . ConfirmationForm::NAME,
            $form->getAttribute(qualifiedName: 'action') ?? '',
        );
        // Hidden inputs come before the visible content of the form
        $firstElement = $form->firstElementChild;
        $this->assertInstanceOf(Element::class, $firstElement);
        $this->assertSame('hidden', $firstElement->getAttribute(qualifiedName: 'type'));
        $this->assertSame(CsrfTokenSource::FIELD_NAME, $firstElement->getAttribute(qualifiedName: 'name'));
        $this->assertSame($this->csrfTokenSource->getToken(), $firstElement->getAttribute(qualifiedName: 'value'));
    }

    public function testPostWithoutTokenRunsNothing(): void
    {
        $this->requestWithToken(postParameters: [ConfirmationForm::CONTROL_NAME => '']);

        $this->assertNoRedirect();
    }

    public function testPostWithWrongTokenRunsNothing(): void
    {
        $this->csrfTokenSource->getToken();

        $this->requestWithToken(postParameters: [CsrfTokenSource::FIELD_NAME => 'wrong']);

        $this->assertNoRedirect();
    }

    public function testValidPostRunsTheActionOnceAndRedirects(): void
    {
        $this->requestWithToken(postParameters: [CsrfTokenSource::FIELD_NAME => $this->csrfTokenSource->getToken()]);

        $this->assertSame(1, $this->confirmationCalls->count);
        $response = $this->responseSender->sentResponse;
        $this->assertInstanceOf(HttpResponse::class, $response);
        $this->assertSame(HttpStatusCodeEnum::HTTP_SEE_OTHER, $response->httpStatusCode);
        $this->assertSame('https://example.com' . AbstractDeleteThing::REDIRECT_TARGET, $response->getHeader(key: 'Location'));
    }

    public function testPageInFileGroupUsesTheTemplateOfTheBackend(): void
    {
        $html = $this->requestWithToken(postParameters: null, fileGroup: 'shop');

        $this->assertNoRedirect();
        $this->assertStringContainsString('<p>Really delete &lt;Thing &amp; Co&gt;?</p>', $html);
        ConfirmationViewTest::findDialogForm(html: $html);
    }

    public function testValidPostInFileGroupRunsTheActionOnce(): void
    {
        $this->requestWithToken(
            postParameters: [CsrfTokenSource::FIELD_NAME => $this->csrfTokenSource->getToken()],
            fileGroup: 'shop',
        );

        $this->assertSame(1, $this->confirmationCalls->count);
        $this->assertSame(
            'https://example.com' . AbstractDeleteThing::REDIRECT_TARGET,
            $this->responseSender->sentResponse?->getHeader(key: 'Location'),
        );
    }

    public function testRouteWithoutCsrfTokenThrows(): void
    {
        $this->expectException(LogicException::class);

        $this->request(postParameters: null);
    }
}
