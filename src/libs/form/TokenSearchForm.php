<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
use actra\backend\libs\form\component\SearchQueryField;
use actra\backend\libs\form\component\SearchSelectOptionsField;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;

final class TokenSearchForm extends AbstractSearchForm
{
    public readonly ?AuthTokenTypeEnum $authTokenTypeEnum;
    public readonly string $searchQuery;
    private readonly SearchSelectOptionsField $typeFilterField;
    private readonly SearchQueryField $searchQueryField;

    public function __construct(BackendViewContext $context, string $name)
    {
        parent::__construct(context: $context, name: $name);
        $this->addCssClass(className: 'form-filter');
        $this->addCssClass(className: 'form-autosubmit');
        $messages = ActraBackend::messages();
        $typeFilterOptions = new FormOptions();
        foreach (AuthTokenTypeEnum::cases() as $authTokenTypeEnum) {
            $typeFilterOptions->addItem(
                key: 'option_' . $authTokenTypeEnum->value,
                htmlText: HtmlText::fromText(
                    text: $authTokenTypeEnum->render(messages: $messages->log),
                ),
            );
        }
        $this->addField(
            formField: $this->typeFilterField = new SearchSelectOptionsField(
                name: 'typeFilterField',
                label: HtmlText::fromText(text: $messages->log->typeLabel),
                formOptions: $typeFilterOptions,
                initialValue: '',
                individualEmptyValueLabel: HtmlText::fromText(text: $messages->common->filterAll),
            ),
        );
        $this->authTokenTypeEnum = AuthTokenTypeEnum::tryFrom(
            value: $this->validateSearchField(
                searchField: $this->typeFilterField,
            ),
        );

        $this->addField(formField: $this->searchQueryField = new SearchQueryField());
        $this->searchQuery = $this->validateSearchField(searchField: $this->searchQueryField);
        $this->addComponent(
            formComponent: new FormControl(
                name: 'find',
                submitLabel: HtmlText::fromText(text: $messages->common->searchButton),
            ),
        );
    }
}
