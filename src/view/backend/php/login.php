<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\BackendView;
use actra\backend\libs\auth\BackendLogin;
use actra\backend\libs\form\LoginForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class login extends BackendView
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
        $login = new BackendLogin(context: $this->backendContext);
        if ($this->backendContext->authSession->isLoggedIn()) {
            // Logged in on the login host already: a login request of a site is handed over at once
            $login->redirectAfterLogin(
                myAuthUser: $this->backendContext->getCurrentUser(),
                loginRequestId: $login->findLoginRequest()?->id,
                returnPath: $login->findReturnPath(),
            );
        }
        $htmlDocument->templateName = 'authentication';
        $messages = $this->backendContext->messages->auth;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'introText',
            htmlText: HtmlText::fromText(text: $messages->loginIntro),
        );
        $loginForm = new LoginForm(login: $login);
        if ($loginForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: BackendLogin::createNextStepUri(
                    path: $this->backendContext->paths->loginToken(),
                    returnPath: $loginForm->getReturnPath(),
                    loginRequestId: $loginForm->getLoginRequestId(),
                ),
                httpRequest: $this->context->httpRequest,
                responseSender: $this->context->responseSender,
            );
        }
        $replacements->addHtml(
            identifier: 'form',
            html: $loginForm->render(),
        );
    }
}
