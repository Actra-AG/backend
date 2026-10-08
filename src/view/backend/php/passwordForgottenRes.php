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
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class passwordForgottenRes extends BackendView
{
    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: ActraBackend::messages()->auth->passwordForgottenPageTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $htmlDocument->templateName = 'authentication';
        $messages = ActraBackend::messages()->auth;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'resultText',
            htmlText: HtmlText::fromText(text: $messages->passwordForgottenResult),
        );
        $this->backendContext->actraBackend->getAuthSession()->logOut();
    }

    public static function getPath(): string
    {
        return ActraBackend::path() . 'passwordForgottenRes.html';
    }
}
