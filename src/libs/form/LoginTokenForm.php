<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\auth\MyAuthenticator;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class LoginTokenForm extends Form
{
    private readonly BackendViewContext $backendContext;
    private readonly TextField $tokenField;

    public function __construct(BackendViewContext $context)
    {
        $this->backendContext = $context;
        $messages = $this->backendContext->messages;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'LoginTokenForm',
            messages: $messages->form,
        );
        $this->addCssClass(className: 'form');
        $this->addCssClass(className: 'form-login');
        $this->addField(
            formField: $this->tokenField = new TextField(
                name: 'token',
                label: HtmlText::fromText(text: $messages->auth->tokenLabel),
                value: null,
                requiredError: HtmlText::fromText(text: $messages->auth->tokenRequired),
            ),
        );
        $this->tokenField->autoFocus = true;
        $this->tokenField->renderRequiredAbbr = false;
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::fromText(text: $messages->auth->tokenSubmitLabel),
            ),
        );
    }

    public function process(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $myAuthenticator = new MyAuthenticator(context: $this->backendContext);
        if (!$myAuthenticator->tokenLogin(inputToken: $this->tokenField->getValueAsString())) {
            $this->tokenField->addError(
                errorMessage: HtmlText::fromText(text: $this->backendContext->messages->auth->tokenInvalid),
            );
            return false;
        }

        return true;
    }
}
