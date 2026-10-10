<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\form\component\SearchQueryField;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class VisitSearchForm extends AbstractSearchForm
{
    public readonly ?AuthResultEnum $status;
    public readonly string $searchQuery;
    private readonly SelectOptionsField $statusFilterField;
    private readonly SearchQueryField $searchQueryField;

    public function __construct(BackendViewContext $context, string $name)
    {
        parent::__construct(context: $context, name: $name);
        // Label and control in a <div>, the label points to the id of the control
        $this->useCompactFieldRenderer();
        $this->addCssClass(className: 'form-filter');
        $this->addCssClass(className: 'form-autosubmit');
        $messages = $this->backendContext->messages;
        $statusFilterOptions = new FormOptions();
        foreach (AuthResultEnum::cases() as $authResult) {
            if ($authResult === AuthResultEnum::UNDEFINED) {
                continue;
            }
            $statusFilterOptions->addIntItem(
                key: $authResult->value,
                htmlText: $authResult->label(messages: $messages->authResult),
            );
        }
        $this->addField(
            formField: $this->statusFilterField = new SelectOptionsField(
                name: 'statusFilterField',
                label: HtmlText::fromText(text: $messages->log->statusLabel),
                formOptions: $statusFilterOptions,
                initialValue: '',
                individualEmptyValueLabel: HtmlText::fromText(text: $messages->common->filterAll),
            ),
        );
        $status = $this->validateIntOptionsSearchField(searchField: $this->statusFilterField);
        $this->status = $status === null ? null : AuthResultEnum::from($status);

        $this->addField(
            formField: $this->searchQueryField = new SearchQueryField(
                messages: $this->backendContext->messages->common,
            ),
        );
        $this->searchQuery = $this->validateTextSearchField(searchField: $this->searchQueryField);
        $this->addComponent(
            formComponent: new FormControl(
                name: 'find',
                submitLabel: HtmlText::fromText(text: $messages->common->searchButton),
            ),
        );
    }
}
