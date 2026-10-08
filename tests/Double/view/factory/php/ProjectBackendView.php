<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double\view\factory\php;

use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * A view of a project based on `BackendView` (`BackendViewFactoryTest`).
 */
final class ProjectBackendView extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(context: $context, requiredViewGroupName: 'factory');
    }

    public function getBackendContext(): BackendViewContext
    {
        return $this->backendContext;
    }

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
        return HtmlText::unencoded(textContent: 'Project view');
    }
}
