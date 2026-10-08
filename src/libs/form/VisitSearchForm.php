<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\form\component\SearchQueryField;
use actra\backend\libs\form\component\SearchSelectOptionsField;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class VisitSearchForm extends AbstractSearchForm
{
    public readonly int $status;
    public readonly string $searchQuery;
    private readonly SearchSelectOptionsField $statusFilterField;
    private readonly SearchQueryField $searchQueryField;

    public function __construct(BackendViewContext $context, string $name)
    {
        parent::__construct(context: $context, name: $name);
        $this->addCssClass(className: 'form-filter');
        $this->addCssClass(className: 'form-autosubmit');
        $messages = $this->backendContext->messages;
        $statusFilterOptions = new FormOptions();
        foreach (AuthResultEnum::cases() as $authResult) {
            if ($authResult === AuthResultEnum::UNDEFINED) {
                continue;
            }
            $statusFilterOptions->addItem(
                key: 'option_' . $authResult->value,
                htmlText: HtmlText::fromText(
                    text: $messages->log->authResult(authResult: $authResult),
                ),
            );
        }
        $statusFilterOptions->addItem(
            key: 'option_6',
            htmlText: HtmlText::fromText(text: $messages->log->filterNoAccess),
        );
        $statusFilterOptions->addItem(
            key: 'option_9',
            htmlText: HtmlText::fromText(text: $messages->log->filterUnconfirmedAccess),
        );
        $this->addField(
            formField: $this->statusFilterField = new SearchSelectOptionsField(
                name: 'statusFilterField',
                label: HtmlText::fromText(text: $messages->log->statusLabel),
                formOptions: $statusFilterOptions,
                initialValue: '',
                individualEmptyValueLabel: HtmlText::fromText(text: $messages->common->filterAll),
            ),
        );
        $this->status = (int) $this->validateSearchField(searchField: $this->statusFilterField);

        $this->addField(
            formField: $this->searchQueryField = new SearchQueryField(
                messages: $this->backendContext->messages->common,
            ),
        );
        $this->searchQuery = $this->validateSearchField(searchField: $this->searchQueryField);
        $this->addComponent(
            formComponent: new FormControl(
                name: 'find',
                submitLabel: HtmlText::fromText(text: $messages->common->searchButton),
            ),
        );
    }
}
