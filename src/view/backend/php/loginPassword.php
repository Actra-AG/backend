<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\form\LoginPasswordForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthSession;
use actra\yuf\core\HttpResponse;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class loginPassword extends BackendView
{
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::unencoded(textContent: ActraBackend::messages()->auth->loginPageTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        AuthSession::logOut();
        $htmlDocument->templateName = 'authentication';
        $messages = ActraBackend::messages()->auth;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'introText',
            htmlText: HtmlText::unencoded(textContent: $messages->loginPasswordIntro),
        );
        $loginPasswordForm = new LoginPasswordForm();
        if ($loginPasswordForm->process()) {
            HttpResponse::redirectAndExit(relativeOrAbsoluteUri: loginPasswordToken::getPath());
        }
        $replacements->addEncodedText(
            identifier: 'form',
            content: $loginPasswordForm->render(),
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::path() . 'loginPassword.html';
    }
}
