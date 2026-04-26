<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\html\HtmlDataObject;

readonly class DbAuthGroup
{
    public function __construct(
        public int $ID,
        public string $title
    ) {
    }

    public function render(): HtmlDataObject {
        $htmlDataObject = new HtmlDataObject();
        $htmlDataObject->addTextElement(
            propertyName: 'name',
            content: $this->title,
            isEncodedForRendering: true
        );
        return $htmlDataObject;
    }
}