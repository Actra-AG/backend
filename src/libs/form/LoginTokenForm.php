<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\libs\auth\MyAuthenticator;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\FormMessages;
use actra\yuf\html\HtmlText;

final class LoginTokenForm extends Form
{
    private readonly TextField $tokenField;

    public function __construct()
    {
        parent::__construct(name: 'LoginTokenForm', messages: FormMessages::german());
        $this->addCssClass(className: 'form');
        $this->addCssClass(className: 'form-login');
        $this->addField(
            formField: $this->tokenField = new TextField(
                name: 'token',
                label: HtmlText::encoded(textContent: 'Code'),
                value: null,
                requiredError: HtmlText::encoded(textContent: 'Geben Sie den Code ein.'),
            )
        );
        $this->tokenField->autoFocus = true;
        $this->tokenField->renderRequiredAbbr = false;
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::encoded(textContent: 'anmelden'),
            )
        );
    }

    public function process(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        if (!MyAuthenticator::get()->tokenLogin(inputToken: $this->tokenField->getValueAsString())) {
            $this->tokenField->addError(
                errorMessage: HtmlText::encoded(textContent: 'Sie haben einen ungültigen Code eingegeben.')
            );
            return false;
        }

        return true;
    }
}