<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double\view\factory\php;

use actra\backend\BackendView;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * A project view with the name of a page of the backend: it replaces that page on the backend route
 * (`BackendViewFactoryTest`).
 */
final class logout extends BackendView
{
    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void {}

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: 'Project logout');
    }
}
