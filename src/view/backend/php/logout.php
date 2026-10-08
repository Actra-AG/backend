<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class logout extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(
            context: $context,
            forceLogout: true,
        );
    }

    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: $this->backendContext->messages->auth->logoutPageTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $htmlDocument->templateName = 'authentication';
        $messages = $this->backendContext->messages->auth;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'heading',
            htmlText: HtmlText::fromText(text: $messages->logoutHeading),
        );
        $replacements->addHtmlText(
            identifier: 'statusText',
            htmlText: HtmlText::fromText(text: $messages->logoutStatus),
        );
        $replacements->addHtmlText(
            identifier: 'loginText',
            htmlText: HtmlText::fromText(text: $messages->loginLink),
        );
        $replacements->addHtml(
            identifier: 'loginHref',
            html: $this->backendContext->paths->login(),
        );
    }
}
