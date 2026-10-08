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
use actra\backend\libs\form\LoginForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthSession;
use actra\yuf\core\HttpResponse;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class login extends BackendView
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
        if (AuthSession::isLoggedIn()) {
            MyAuthUser::get()->redirectToFirstAllowedPage();
        }
        $htmlDocument->templateName = 'authentication';
        $messages = ActraBackend::messages()->auth;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'introText',
            htmlText: HtmlText::unencoded(textContent: $messages->loginIntro),
        );
        $loginForm = new LoginForm();
        if ($loginForm->process()) {
            HttpResponse::redirectAndExit(relativeOrAbsoluteUri: loginToken::getPath());
        }
        $replacements->addEncodedText(
            identifier: 'form',
            content: $loginForm->render(),
        );
    }

    public static function getPath(bool $prependPath = true): string
    {
        return ($prependPath ? ActraBackend::path() : '') . 'login.html';
    }
}
