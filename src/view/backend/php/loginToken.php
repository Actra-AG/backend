<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\BackendView;
use actra\backend\libs\form\LoginTokenForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\LoginRedirect;
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
        if ($this->backendContext->authSession->isLoggedIn()) {
            $this->backendContext->getCurrentUser()->redirectToFirstAllowedPage(
                context: $this->backendContext,
                returnPath: LoginRedirect::findReturnPath(httpRequest: $this->context->httpRequest),
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
        $loginTokenForm = new LoginTokenForm(context: $this->backendContext);
        // The user logged in by the form: the context still holds the state before the login
        $myAuthUser = $loginTokenForm->process();
        if ($myAuthUser !== null) {
            $myAuthUser->redirectToFirstAllowedPage(
                context: $this->backendContext,
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
