<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class logout extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(
            context: $context,
            forceLogout: true,
        );
    }

    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::unencoded(textContent: ActraBackend::messages()->auth->logoutPageTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $htmlDocument->templateName = 'authentication';
        $messages = ActraBackend::messages()->auth;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'heading',
            htmlText: HtmlText::unencoded(textContent: $messages->logoutHeading),
        );
        $replacements->addHtmlText(
            identifier: 'statusText',
            htmlText: HtmlText::unencoded(textContent: $messages->logoutStatus),
        );
        $replacements->addHtmlText(
            identifier: 'loginText',
            htmlText: HtmlText::unencoded(textContent: $messages->loginLink),
        );
        $replacements->addEncodedText(
            identifier: 'loginHref',
            content: login::getPath(),
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::path() . 'logout.html';
    }
}
