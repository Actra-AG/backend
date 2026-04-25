<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\yuf\common\SearchHelper;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\NullField;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\field\TextField;

abstract class AbstractSearchForm extends Form
{
    public readonly SearchHelper $searchHelper;

    public function __construct(string $name)
    {
        $this->searchHelper = SearchHelper::getInstance(instanceName: $name);
        parent::__construct(name: $name);
    }

    protected function validateSearchField(NullField|SelectOptionsField|TextField $searchField): string
    {
        if ($searchField instanceof NullField) {
            return '';
        }
        $searchHelper = $this->searchHelper;
        $value = '';
        if ($searchField instanceof TextField) {
            $value = $searchHelper->checkString(
                fieldName: $searchField->name,
                default: (string)$searchField->getRawValue()
            );
        }
        if ($searchField instanceof SelectOptionsField) {
            $value = $searchHelper->checkFilter(
                array: ['' => 'all'] + $searchField->formOptions->data,
                fieldName: $searchField->name,
                default: $searchField->getRawValue()
            );
        }
        $searchField->setValue(value: $value);

        return str_replace(search: 'option_', replace: '', subject: $value);
    }
}