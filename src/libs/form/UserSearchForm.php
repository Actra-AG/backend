<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\libs\db\DbAuthGroup;
use actra\backend\libs\db\DbAuthGroupRepository;
use actra\backend\libs\form\component\SearchQueryField;
use actra\backend\libs\form\component\SearchSelectOptionsField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

class UserSearchForm extends AbstractSearchForm
{
    public readonly ?DbAuthGroup $dbAuthGroup;
    public readonly string $searchQuery;
    private readonly SearchSelectOptionsField $userGroupField;
    private readonly SearchQueryField $searchQueryField;

    public function __construct()
    {
        parent::__construct(name: 'UserSearchForm');
        $this->addCssClass(className: 'form-filter');
        $this->addCssClass(className: 'form-autosubmit');
        $this->addField(
            formField: $this->userGroupField = new SearchSelectOptionsField(
                name: 'userGroup',
                label: HtmlText::encoded(textContent: 'Benutzergruppe'),
                formOptions: DbAuthGroupRepository::listAll()->getFormOptions(),
                initialValue: '',
                individualEmptyValueLabel: HtmlText::encoded(textContent: 'alle')
            )
        );
        $this->dbAuthGroup = DbAuthGroupRepository::selectByID(
            ID: (int)$this->validateSearchField(
                searchField: $this->userGroupField
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