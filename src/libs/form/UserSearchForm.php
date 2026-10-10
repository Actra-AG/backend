<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\db\DbAuthGroup;
use actra\backend\libs\form\component\SearchQueryField;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class UserSearchForm extends AbstractSearchForm
{
    public readonly ?DbAuthGroup $dbAuthGroup;
    public readonly string $searchQuery;
    private readonly SelectOptionsField $userGroupField;
    private readonly SearchQueryField $searchQueryField;

    public function __construct(BackendViewContext $context)
    {
        parent::__construct(context: $context, name: 'UserSearchForm');
        $common = $this->backendContext->messages->common;
        $this->addCssClass(className: 'form-filter');
        $this->addCssClass(className: 'form-autosubmit');
        $this->addField(
            formField: $this->userGroupField = new SelectOptionsField(
                name: 'userGroup',
                label: HtmlText::fromText(text: $common->userGroupLabel),
                formOptions: $this->backendContext->repositories->groups()->listAll()->getFormOptions(),
                initialValue: '',
                individualEmptyValueLabel: HtmlText::fromText(text: $common->filterAll),
            ),
        );
        $groupId = $this->validateIntOptionsSearchField(searchField: $this->userGroupField);
        $this->dbAuthGroup = $groupId === null
            ? null
            : $this->backendContext->repositories->groups()->selectById(id: $groupId);
        $this->addField(
            formField: $this->searchQueryField = new SearchQueryField(
                messages: $this->backendContext->messages->common,
            ),
        );
        $this->searchQuery = $this->validateTextSearchField(searchField: $this->searchQueryField);
        $this->addComponent(
            formComponent: new FormControl(
                name: 'find',
                submitLabel: HtmlText::fromText(text: $common->searchButton),
            ),
        );
    }
}
