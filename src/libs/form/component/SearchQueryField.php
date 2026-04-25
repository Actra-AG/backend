<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form\component;

use actra\yuf\form\component\field\TextField;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\html\HtmlText;

class SearchQueryField extends TextField
{
    public function __construct()
    {
        parent::__construct(
            name: 'searchQuery',
            label: HtmlText::encoded(textContent: 'Suchbegriff')
        );
    }

    public function getHtmlTag(): HtmlTag
    {
        $divTag = new HtmlTag(name: 'div', selfClosing: false);
        $labelAttributes = [new HtmlTagAttribute(name: 'for', value: $this->name, valueIsEncodedForRendering: true)];
        $labelTag = new HtmlTag(name: 'label', selfClosing: false, htmlTagAttributes: $labelAttributes);
        $labelTag->addText(htmlText: $this->label);
        $divTag->addTag(htmlTag: $labelTag);
        $defaultFormFieldRenderer = $this->getDefaultRenderer();
        $defaultFormFieldRenderer->prepare();
        $divTag->addTag(htmlTag: $defaultFormFieldRenderer->getHtmlTag());

        return $divTag;
    }
}