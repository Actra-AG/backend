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
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class loginPasswordToken extends BackendView
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
        $this->backendContext->authSession->logOut();
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
        if ($loginTokenForm->process()) {
            $this->backendContext->getCurrentUser()->redirectToFirstAllowedPage(context: $this->backendContext);
        }
        $replacements->addHtml(
            identifier: 'form',
            html: $loginTokenForm->render(),
        );
        $replacements->addHtml(
            identifier: 'loginPasswordHref',
            html: $this->backendContext->paths->loginPassword(),
        );
    }
}
