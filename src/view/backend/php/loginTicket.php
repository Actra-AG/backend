<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\BackendView;
use actra\backend\libs\auth\BackendLogin;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * The hand-over page of a site that logs in on another host (`ActraBackend::createRemoteLoginTarget()`): redeems the
 * ticket of the login host, logs the user in and redirects to the requested page. Without a valid ticket it shows a
 * link to the login page (no redirect, so no loop).
 *
 * @internal
 */
final class loginTicket extends BackendView
{
    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: $this->backendContext->messages->auth->loginPageTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        if ($this->backendContext->actraBackend->actraBackendSettings->loginHandOver === null) {
            throw new NotFoundException();
        }
        try {
            $returnPath = new BackendLogin(context: $this->backendContext)->logInWithTicket();
        } catch (UnauthorizedException) {
            $returnPath = null;
        }
        if ($returnPath !== null) {
            // The visitor comes from the login host: a `Strict` session cookie would not be sent on the redirect
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $returnPath,
                httpRequest: $this->context->httpRequest,
                sameSiteLaxSessionHandler: $this->context->sessionHandler,
                responseSender: $this->context->responseSender,
            );
        }
        $htmlDocument->templateName = 'authentication';
        // The markup of the logout page: heading, status and the link to the login page
        $htmlDocument->useContentFile(filePath: __DIR__ . '/../html/logout.html');
        $messages = $this->backendContext->messages->auth;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'heading',
            htmlText: HtmlText::fromText(text: $messages->loginTicketHeading),
        );
        $replacements->addHtmlText(
            identifier: 'statusText',
            htmlText: HtmlText::fromText(text: $messages->loginTicketInvalid),
        );
        $replacements->addHtmlText(
            identifier: 'loginText',
            htmlText: HtmlText::fromText(text: $messages->loginLink),
        );
        $replacements->addHtml(
            identifier: 'loginHref',
            html: $this->backendContext->paths->login(),
        );
    }
}
