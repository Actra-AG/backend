<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\form\component\SearchQueryField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class SentEmailSearchForm extends AbstractSearchForm
{
    /** Searched in the recipients (to, cc, bcc), the sender and the subject */
    public readonly string $searchQuery;
    private readonly SearchQueryField $searchQueryField;

    public function __construct(BackendViewContext $context, string $name)
    {
        parent::__construct(context: $context, name: $name);
        // Label and control in a <div>, the label points to the id of the control
        $this->useCompactFieldRenderer();
        $this->addCssClass(className: 'form-filter');
        $this->addCssClass(className: 'form-autosubmit');
        $common = $this->backendContext->messages->common;
        $this->addField(formField: $this->searchQueryField = new SearchQueryField(messages: $common));
        $this->searchQuery = $this->validateTextSearchField(searchField: $this->searchQueryField);
        $this->addComponent(
            formComponent: new FormControl(
                name: 'find',
                submitLabel: HtmlText::fromText(text: $common->searchButton),
            ),
        );
    }
}
