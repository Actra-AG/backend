<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
use actra\yuf\common\SearchState;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\NullField;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\field\TextField;
use LogicException;

/**
 * Extension point: the base of the search forms of the backend tables and of the project tables.
 */
abstract class AbstractSearchForm extends Form
{
    public readonly SearchState $searchState;
    protected readonly BackendViewContext $backendContext;

    public function __construct(BackendViewContext $context, string $name)
    {
        $this->backendContext = $context;
        $this->searchState = SearchState::create(
            instanceName: $name,
            httpRequest: $context->viewContext->httpRequest,
            valueSource: InputSourceEnum::POST,
            session: $context->viewContext->session
                ?? throw new LogicException(message: 'Search forms need a session.'),
        );
        parent::__construct(
            context: $context->viewContext->formContext,
            name: $name,
            messages: ActraBackend::messages()->form,
        );
    }

    /**
     * Reads the search value from the request or the session (SearchState) and shows it in the field.
     */
    protected function validateSearchField(NullField|SelectOptionsField|TextField $searchField): string
    {
        if ($searchField instanceof NullField) {
            return '';
        }
        $searchState = $this->searchState;
        if ($searchField instanceof TextField) {
            $value = $searchState->checkString(
                fieldName: $searchField->name,
                default: $searchField->getValueAsString(),
            );
        } else {
            $value = $searchState->checkFilter(
                array: ['' => 'all'] + $searchField->formOptions->data,
                fieldName: $searchField->name,
                default: $searchField->getValueAsString(),
            );
        }
        $searchField->setValue(value: $value);

        return str_replace(search: 'option_', replace: '', subject: $value);
    }
}
