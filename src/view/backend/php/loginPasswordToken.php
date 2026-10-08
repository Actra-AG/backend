<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\form\LoginTokenForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class loginPasswordToken extends BackendView
{
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: ActraBackend::messages()->auth->loginPageTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $this->backendContext->actraBackend->getAuthSession()->logOut();
        $htmlDocument->templateName = 'authentication';
        $messages = ActraBackend::messages()->auth;
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
            MyAuthUser::get()->redirectToFirstAllowedPage();
        }
        $replacements->addHtml(
            identifier: 'form',
            html: $loginTokenForm->render(),
        );
        $replacements->addHtml(
            identifier: 'loginPasswordHref',
            html: loginPassword::getPath(),
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::path() . 'loginPasswordToken.html';
    }
}
