<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form\component;

use actra\backend\i18n\CommonMessages;
use actra\yuf\form\component\field\TextField;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\html\HtmlText;

final class SearchQueryField extends TextField
{
    public function __construct(CommonMessages $messages)
    {
        parent::__construct(
            name: 'searchQuery',
            label: HtmlText::fromText(text: $messages->searchLabel),
        );
    }

    #[\Override]
    public function getHtmlTag(): HtmlTag
    {
        $divTag = new HtmlTag(name: 'div', selfClosing: false);
        $labelAttributes = [HtmlTagAttribute::fromText(name: 'for', text: $this->name)];
        $labelTag = new HtmlTag(name: 'label', selfClosing: false, htmlTagAttributes: $labelAttributes);
        $labelTag->addText(htmlText: $this->label);
        $divTag->addTag(htmlTag: $labelTag);
        $divTag->addTag(htmlTag: $this->getDefaultRenderer()->createHtmlTag());

        return $divTag;
    }
}
