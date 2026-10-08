<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\i18n\CommonMessages;
use actra\backend\i18n\MessageTemplate;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use LogicException;
use OutOfBoundsException;

final class DbAuthUserCollection
{
    /** @var DbAuthUser[] $items */
    public private(set) array $items = [];

    public function __construct() {}

    public function add(DbAuthUser $dbAuthUser): void
    {
        $this->items[$dbAuthUser->id] = $dbAuthUser;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function first(): DbAuthUser
    {
        $first = current(array: $this->items);
        if ($first === false) {
            throw new LogicException(message: 'The collection is empty.');
        }

        return $first;
    }

    public function getFormOptions(CommonMessages $messages): FormOptions
    {
        $formOptions = new FormOptions();
        foreach ($this->items as $dbAuthUser) {
            $formOptions->addItem(
                key: (string) $dbAuthUser->id,
                htmlText: HtmlText::fromText(
                    text: MessageTemplate::fill(
                        template: $messages->userOption,
                        values: [
                            'email' => $dbAuthUser->email,
                            'name' => $dbAuthUser->renderFullName(messages: $messages),
                        ],
                    ),
                ),
            );
        }
        return $formOptions;
    }

    public function has(int $userId): bool
    {
        return array_key_exists(key: $userId, array: $this->items);
    }

    public function get(int $userId): DbAuthUser
    {
        if (!array_key_exists(key: $userId, array: $this->items)) {
            throw new OutOfBoundsException(message: 'No DbAuthUser with the ID ' . $userId . ' in the collection.');
        }

        return $this->items[$userId];
    }
}
