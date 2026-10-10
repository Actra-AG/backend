<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\yuf\common\SearchState;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\form\component\collection\Form;
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
            messages: $this->backendContext->messages->form,
        );
    }

    /**
     * Reads the search text from the request or the session (SearchState) and shows it in the field.
     */
    protected function validateTextSearchField(TextField $searchField): string
    {
        $value = $this->searchState->checkString(
            fieldName: $searchField->name,
            default: $searchField->getValueAsString(),
        );
        $searchField->setValue(value: $value);

        return $value;
    }

    /**
     * Reads the selected option (a key of the field's options, `''` for all) from the request or the session.
     */
    protected function validateOptionsSearchField(SelectOptionsField $searchField): string
    {
        $value = $this->searchState->checkOptionsFilter(
            formOptions: $searchField->formOptions,
            fieldName: $searchField->name,
            default: $searchField->getValueAsString(),
        );
        $searchField->setValue(value: $value);

        return $value;
    }

    /**
     * Like `validateOptionsSearchField()` for options with integer keys (`FormOptions::addIntItem()`), `null` for all.
     */
    protected function validateIntOptionsSearchField(SelectOptionsField $searchField): ?int
    {
        $value = $this->searchState->checkIntOptionsFilter(
            formOptions: $searchField->formOptions,
            fieldName: $searchField->name,
            default: $searchField->getValueAsInt(),
        );
        $searchField->setValue(value: $value === null ? null : (string) $value);

        return $value;
    }
}
