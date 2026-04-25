<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;

class DbAuthGroupCollection
{
    /** @var DbAuthGroup[] $items */
    private(set) array $items = [];

    public function __construct()
    {
    }

    public function add(DbAuthGroup $dbAuthGroup): void
    {
        $this->items[$dbAuthGroup->ID] = $dbAuthGroup;
    }

    public function getFormOptions(): FormOptions
    {
        $formOptions = new FormOptions();
        foreach ($this->items as $dbAuthGroup) {
            $formOptions->addItem(
                key: (string)$dbAuthGroup->ID,
                htmlText: HtmlText::encoded(textContent: $dbAuthGroup->title)
            );
        }

        return $formOptions;
    }

    public function listIDs(): array
    {
        return array_keys(array: $this->items);
    }
}