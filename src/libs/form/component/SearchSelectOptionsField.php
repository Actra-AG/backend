<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form\component;

use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;

class SearchSelectOptionsField extends SelectOptionsField
{
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