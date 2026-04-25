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
use actra\yuf\auth\AuthSession;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class loginToken extends BackendView
{
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::encoded(textContent: 'Anmelden');
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        if (AuthSession::isLoggedIn()) {
            MyAuthUser::get()->redirectToFirstAllowedPage();
        }
        $htmlDocument->templateName = 'authentication';
        $replacements = $htmlDocument->replacements;
        $loginTokenForm = new LoginTokenForm();
        if ($loginTokenForm->process()) {
            MyAuthUser::get()->redirectToFirstAllowedPage();
        }
        $replacements->addEncodedText(
            identifier: 'form',
            content: $loginTokenForm->render()
        );
        $replacements->addEncodedText(
            identifier: 'loginHref',
            content: login::getPath()
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::get()->path . 'loginToken.html';
    }
}