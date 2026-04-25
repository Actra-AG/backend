<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\libs\form\component\SearchQueryField;
use actra\backend\libs\form\component\SearchSelectOptionsField;
use actra\yuf\auth\AuthResult;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;

class VisitSearchForm extends AbstractSearchForm
{
    public readonly int $status;
    public readonly string $searchQuery;
    private readonly SearchSelectOptionsField $statusFilterField;
    private readonly SearchQueryField $searchQueryField;

    public function __construct(string $name)
    {
        parent::__construct(name: $name);
        $this->addCssClass(className: 'form-filter');
        $this->addCssClass(className: 'form-autosubmit');
        $statusFilterOptions = new FormOptions();
        foreach (AuthResult::cases() as $authResult) {
            if ($authResult === AuthResult::UNDEFINED) {
                continue;
            }
            $statusFilterOptions->addItem(
                key: 'option_' . $authResult->value,
                htmlText: HtmlText::encoded(
                    textContent: $authResult->render()
                )
            );
        }
        $statusFilterOptions->addItem(key: 'option_6', htmlText: HtmlText::encoded(textContent: 'Kein Zugriff'));
        $statusFilterOptions->addItem(
            key: 'option_9',
            htmlText: HtmlText::encoded(textContent: 'Unbestätigter Zugang')
        );
        $this->addField(
            formField: $this->statusFilterField = new SearchSelectOptionsField(
                name: 'statusFilterField',
                label: HtmlText::encoded(textContent: 'Status'),
                formOptions: $statusFilterOptions,
                initialValue: '',
                individualEmptyValueLabel: HtmlText::encoded(textContent: 'alle')
            )
        );
        $this->status = (int)$this->validateSearchField(searchField: $this->statusFilterField);

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