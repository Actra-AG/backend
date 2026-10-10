<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\yuf\auth\AuthSession;
use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\PathVars;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\Route;
use actra\yuf\core\ViewContext;
use actra\yuf\form\FormContext;
use actra\yuf\layout\NavigationItemCollection;
use actra\yuf\security\CspNonce;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\template\cache\DirectoryTemplateCache;
use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\TemplateEngine;
use Closure;

/**
 * A `ViewContext` for the tests: an HTML request of a file title on the given route, with a session in memory and a
 * `RecordingResponseSender`.
 */
final class ViewContextFactory
{
    /**
     * @param ?Closure(ViewContext): NavigationItemCollection $navigationProvider
     */
    public static function create(
        Route $route,
        string $fileTitle,
        HttpRequest $httpRequest = new HttpRequest(host: 'example.com'),
        ResponseSender $responseSender = new RecordingResponseSender(),
        Session $session = new Session(storage: new ArraySessionStorage()),
        ?Closure $navigationProvider = null,
    ): ViewContext {
        return new ViewContext(
            httpRequest: $httpRequest,
            session: $session,
            sessionHandler: null,
            authSession: new AuthSession(session: $session),
            formContext: new FormContext(httpRequest: $httpRequest, csrfTokenSource: null),
            route: $route,
            fileGroup: null,
            fileTitle: $fileTitle,
            pathVars: new PathVars(values: [0 => $fileTitle]),
            content: new ContentHandler(contentType: ContentType::createHtml(), cspNonce: new CspNonce(value: 'nonce')),
            locale: new LocaleHandler(language: null, availableLanguages: new LanguageCollection()),
            templateEngine: new TemplateEngine(
                cache: new DirectoryTemplateCache(
                    cacheDirectory: sys_get_temp_dir(),
                    templateBaseDirectory: __DIR__,
                ),
                tags: new TemplateTagCollection(),
            ),
            responseSender: $responseSender,
            navigationProvider: $navigationProvider,
        );
    }
}
