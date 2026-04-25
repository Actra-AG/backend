<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;

class DbAuthUserCollection
{
    /** @var DbAuthUser[] $items */
    private(set) array $items = [];

    public function __construct()
    {
    }

    public function add(DbAuthUser $dbAuthUser): void
    {
        $this->items[$dbAuthUser->ID] = $dbAuthUser;
    }

    public function isEmpty(): bool
    {
        return count(value: $this->items) === 0;
    }

    public function getFormOptions(): FormOptions
    {
        $formOptions = new FormOptions();
        foreach ($this->items as $dbAuthUser) {
            $formOptions->addItem(
                key: (string)$dbAuthUser->ID,
                htmlText: HtmlText::encoded(
                    textContent: $dbAuthUser->email . ' (' . $dbAuthUser->firstName . ' ' . $dbAuthUser->lastName . ')'
                )
            );
        }
        return $formOptions;
    }
}