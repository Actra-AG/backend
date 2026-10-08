<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\libs\auth\MyAuthenticator;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

final class LoginTokenForm extends Form
{
    private readonly TextField $tokenField;

    public function __construct()
    {
        $messages = ActraBackend::messages();
        parent::__construct(name: 'LoginTokenForm', messages: $messages->form);
        $this->addCssClass(className: 'form');
        $this->addCssClass(className: 'form-login');
        $this->addField(
            formField: $this->tokenField = new TextField(
                name: 'token',
                label: HtmlText::unencoded(textContent: $messages->auth->tokenLabel),
                value: null,
                requiredError: HtmlText::unencoded(textContent: $messages->auth->tokenRequired),
            ),
        );
        $this->tokenField->autoFocus = true;
        $this->tokenField->renderRequiredAbbr = false;
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::unencoded(textContent: $messages->auth->tokenSubmitLabel),
            ),
        );
    }

    public function process(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        if (!MyAuthenticator::get()->tokenLogin(inputToken: $this->tokenField->getValueAsString())) {
            $this->tokenField->addError(
                errorMessage: HtmlText::unencoded(textContent: ActraBackend::messages()->auth->tokenInvalid),
            );
            return false;
        }

        return true;
    }
}
