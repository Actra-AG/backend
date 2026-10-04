<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthSession;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class passwordResetRes extends BackendView
{
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::unencoded(textContent: ActraBackend::messages()->auth->passwordResetPageTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $htmlDocument->templateName = 'authentication';
        $messages = ActraBackend::messages()->auth;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'doneText',
            htmlText: HtmlText::unencoded(textContent: $messages->passwordResetDone)
        );
        AuthSession::logOut();
        $replacements->addEncodedText(
            identifier: 'loginHref',
            content: loginPassword::getPath()
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::path() . 'passwordResetRes.html';
    }
}