<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double\view\backend\php;

use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\backend\settings\PageNotice;
use actra\backend\settings\PageNoticeLevelEnum;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * A project page on the backend route that adds its own notice (`PageNoticesTest`).
 */
final class noticePage extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(context: $context, requiredViewGroupName: 'backend');
    }

    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $htmlDocument->useContentFile(filePath: __DIR__ . '/../html/noticePage.html');
        $this->addPageNotice(pageNotice: new PageNotice(
            level: PageNoticeLevelEnum::NOTE,
            content: HtmlText::fromText(text: 'Notice of the view'),
        ));
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: 'Notice page');
    }
}
