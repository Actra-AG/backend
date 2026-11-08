<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\BackendView;
use actra\backend\libs\auth\BackendLogin;
use actra\backend\libs\form\LoginTokenForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class loginToken extends BackendView
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
            // Logged in already: a login request of a site is handed over at once
            $login->redirectLoggedInUser(
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
            htmlText: HtmlText::fromText(text: $messages->loginTokenIntro),
        );
        $replacements->addHtmlText(
            identifier: 'tipLabel',
            htmlText: HtmlText::fromText(text: $messages->loginTokenTipLabel),
        );
        $replacements->addHtmlText(
            identifier: 'tipText',
            htmlText: HtmlText::fromText(text: $messages->loginTokenTipText),
        );
        $replacements->addHtmlText(
            identifier: 'backToLoginText',
            htmlText: HtmlText::fromText(text: $messages->backToLogin),
        );
        $loginTokenForm = new LoginTokenForm(login: $login);
        // The user logged in by the form: the context still holds the state before the login
        $myAuthUser = $loginTokenForm->process();
        if ($myAuthUser !== null) {
            $login->redirectAfterLogin(
                myAuthUser: $myAuthUser,
                loginRequestId: $loginTokenForm->getLoginRequestId(),
                returnPath: $loginTokenForm->getReturnPath(),
            );
        }
        $replacements->addHtml(
            identifier: 'form',
            html: $loginTokenForm->render(),
        );
        $replacements->addHtml(
            identifier: 'loginHref',
            html: $this->backendContext->paths->login(),
        );
    }
}
