<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form\component;

use actra\backend\i18n\CommonMessages;
use actra\yuf\form\component\field\TextField;
use actra\yuf\html\HtmlText;

/**
 * The search text field of the search forms (`AbstractSearchForm`).
 */
final class SearchQueryField extends TextField
{
    public function __construct(CommonMessages $messages)
    {
        parent::__construct(
            name: 'searchQuery',
            label: HtmlText::fromText(text: $messages->searchLabel),
        );
    }
}
