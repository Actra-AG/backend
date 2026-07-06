<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\form\PasswordForgottenForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthSession;
use actra\yuf\core\HttpResponse;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class passwordForgotten extends BackendView
{
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::encoded(textContent: 'Passwort vergessen?');
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $htmlDocument->templateName = 'authentication';
        AuthSession::logOut();

        $passwordForgottenForm = new PasswordForgottenForm();
        if ($passwordForgottenForm->validateAndSendTokenEmail()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: passwordForgottenRes::getPath()
            );
        }
        $replacements = $htmlDocument->replacements;
        $replacements->addEncodedText(
            identifier: 'form',
            content: $passwordForgottenForm->render()
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::get()->path . 'passwordForgotten.html';
    }
}