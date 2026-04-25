<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\libs\form\component\SearchQueryField;
use actra\backend\libs\form\component\SearchSelectOptionsField;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;

class TokenSearchForm extends AbstractSearchForm
{
    public readonly ?AuthTokenTypeEnum $authTokenTypeEnum;
    public readonly string $searchQuery;
    private readonly SearchSelectOptionsField $typeFilterField;
    private readonly SearchQueryField $searchQueryField;

    public function __construct(string $name)
    {
        parent::__construct(name: $name);
        $this->addCssClass(className: 'form-filter');
        $this->addCssClass(className: 'form-autosubmit');
        $typeFilterOptions = new FormOptions();
        foreach (AuthTokenTypeEnum::cases() as $authTokenTypeEnum) {
            $typeFilterOptions->addItem(
                key: 'option_' . $authTokenTypeEnum->value,
                htmlText: HtmlText::encoded(
                    textContent: $authTokenTypeEnum->render()
                )
            );
        }
        $this->addField(
            formField: $this->typeFilterField = new SearchSelectOptionsField(
                name: 'typeFilterField',
                label: HtmlText::encoded(textContent: 'Typ'),
                formOptions: $typeFilterOptions,
                initialValue: '',
                individualEmptyValueLabel: HtmlText::encoded(textContent: 'alle')
            )
        );
        $this->authTokenTypeEnum = AuthTokenTypeEnum::tryFrom(
            value: $this->validateSearchField(
                searchField: $this->typeFilterField
            )
        );

        $this->addField(formField: $this->searchQueryField = new SearchQueryField());
        $this->searchQuery = $this->validateSearchField(searchField: $this->searchQueryField);
        $this->addComponent(
            formComponent: new FormControl(
                name: 'find',
                submitLabel: HtmlText::encoded(textContent: 'anzeigen')
            )
        );
    }
}