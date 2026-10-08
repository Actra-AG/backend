<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\html\HtmlText;
use OutOfBoundsException;

final class DbAuthGroupCollection
{
    /** @var array<int, DbAuthGroup> */
    public private(set) array $items = [];

    public function __construct() {}

    public function add(DbAuthGroup $dbAuthGroup): void
    {
        $this->items[$dbAuthGroup->id] = $dbAuthGroup;
    }

    public function getFormOptions(): FormOptions
    {
        $formOptions = new FormOptions();
        foreach ($this->items as $dbAuthGroup) {
            $formOptions->addItem(
                key: (string) $dbAuthGroup->id,
                htmlText: HtmlText::fromText(text: $dbAuthGroup->title),
            );
        }

        return $formOptions;
    }

    /**
     * @return list<int>
     */
    public function listIds(): array
    {
        return array_keys(array: $this->items);
    }

    /**
     * The keys of `getFormOptions()`, e.g. as initial values of an options field.
     *
     * @return list<string>
     */
    public function listFormOptionKeys(): array
    {
        return array_map(callback: static fn(int $id): string => (string) $id, array: $this->listIds());
    }

    /**
     * @param list<int> $authGroupIdList
     */
    public function hasOneOfIds(array $authGroupIdList): bool
    {
        return array_intersect($authGroupIdList, $this->listIds()) !== [];
    }

    public function get(int $id): DbAuthGroup
    {
        if (!array_key_exists(key: $id, array: $this->items)) {
            throw new OutOfBoundsException(message: 'No DbAuthGroup with the ID ' . $id . ' in the collection.');
        }

        return $this->items[$id];
    }

    public function render(): HtmlDataObjectCollection
    {
        $userGroups = new HtmlDataObjectCollection();
        foreach ($this->items as $dbAuthGroup) {
            $userGroups->add(htmlDataObject: $dbAuthGroup->render());
        }

        return $userGroups;
    }
}
