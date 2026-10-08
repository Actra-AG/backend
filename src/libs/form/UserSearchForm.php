<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
use actra\backend\libs\db\DbAuthGroup;
use actra\backend\libs\db\DbAuthGroupRepository;
use actra\backend\libs\form\component\SearchQueryField;
use actra\backend\libs\form\component\SearchSelectOptionsField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

final class UserSearchForm extends AbstractSearchForm
{
    public readonly ?DbAuthGroup $dbAuthGroup;
    public readonly string $searchQuery;
    private readonly SearchSelectOptionsField $userGroupField;
    private readonly SearchQueryField $searchQueryField;

    public function __construct(BackendViewContext $context)
    {
        parent::__construct(context: $context, name: 'UserSearchForm');
        $common = ActraBackend::messages()->common;
        $this->addCssClass(className: 'form-filter');
        $this->addCssClass(className: 'form-autosubmit');
        $this->addField(
            formField: $this->userGroupField = new SearchSelectOptionsField(
                name: 'userGroup',
                label: HtmlText::unencoded(textContent: $common->userGroupLabel),
                formOptions: DbAuthGroupRepository::listAll()->getFormOptions(),
                initialValue: '',
                individualEmptyValueLabel: HtmlText::unencoded(textContent: $common->filterAll),
            ),
        );
        $this->dbAuthGroup = DbAuthGroupRepository::selectByID(
            ID: (int) $this->validateSearchField(
                searchField: $this->userGroupField,
            ),
        );
        $this->addField(formField: $this->searchQueryField = new SearchQueryField());
        $this->searchQuery = $this->validateSearchField(searchField: $this->searchQueryField);
        $this->addComponent(
            formComponent: new FormControl(
                name: 'find',
                submitLabel: HtmlText::unencoded(textContent: $common->searchButton),
            ),
        );
    }
}
