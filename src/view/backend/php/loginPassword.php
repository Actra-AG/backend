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
        return HtmlText::encoded(textContent: 'Anmelden');
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        AuthSession::logOut();
        $htmlDocument->templateName = 'authentication';
        $replacements = $htmlDocument->replacements;
        $loginPasswordForm = new LoginPasswordForm();
        if ($loginPasswordForm->process()) {
            HttpResponse::redirectAndExit(relativeOrAbsoluteUri: loginPasswordToken::getPath());
        }
        $replacements->addEncodedText(
            identifier: 'form',
            content: $loginPasswordForm->render()
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::get()->path . 'loginPassword.html';
    }
}