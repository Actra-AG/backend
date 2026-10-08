<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class ApiKeyRemoveForm extends Form
{
    private readonly BackendViewContext $backendContext;
    public function __construct(
        BackendViewContext $context,
        private readonly int $userId,
        string $cancelLink,
    ) {
        $this->backendContext = $context;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'ApiKeyRemoveForm',
            messages: $this->backendContext->messages->form,
        );
        $this->addCssClass(className: 'form');
        $this->addComponent(
            formComponent: new FormControl(
                name: 'remove',
                submitLabel: HtmlText::fromText(text: $this->backendContext->messages->common->removeApiKeyTitle),
                cancelLink: $cancelLink,
            ),
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        $this->backendContext->repositories->apiKeys()->deleteByUserId(userId: $this->userId);

        return true;
    }
}
