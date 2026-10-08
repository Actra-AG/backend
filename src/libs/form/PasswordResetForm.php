<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\db\DbAuthToken;
use actra\yuf\auth\Password;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class PasswordResetForm extends Form
{
    private readonly BackendViewContext $backendContext;
    private readonly PasswordField $newPasswordField;
    private readonly PasswordField $newPasswordConfirmField;

    public function __construct(
        BackendViewContext $context,
        private readonly DbAuthToken $dbAuthToken,
    ) {
        $this->backendContext = $context;
        $messages = $this->backendContext->messages;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'PasswordResetForm',
            messages: $messages->form,
        );
        $this->addCssClass(className: 'form');
        $this->addCssClass(className: 'form-login');
        $this->addField(
            formField: $this->newPasswordField = new PasswordField(
                name: 'newPassword',
                label: HtmlText::fromText(text: $messages->common->newPasswordLabel),
                requiredError: HtmlText::fromText(text: $messages->common->newPasswordRequired),
                purpose: PasswordPurposeEnum::NEW,
            ),
        );
        $this->addField(
            formField: $this->newPasswordConfirmField = new PasswordField(
                name: 'newPasswordConfirm',
                label: HtmlText::fromText(text: $messages->common->newPasswordConfirmLabel),
                requiredError: HtmlText::fromText(text: $messages->common->newPasswordConfirmRequired),
                purpose: PasswordPurposeEnum::NEW,
            ),
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::fromText(text: $messages->common->save),
            ),
        );
    }

    public function validateAndUpdatePassword(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $newPasswordField = $this->newPasswordField;
        $newPasswordCheck = new NewPasswordCheck(messages: $this->backendContext->messages->common);
        if (!$newPasswordCheck->isValid(
            newPasswordField: $newPasswordField,
            newPasswordConfirmField: $this->newPasswordConfirmField,
        )) {
            return false;
        }
        $this->backendContext->repositories->users()->setPassword(
            ID: $this->dbAuthToken->userID,
            newPassword: Password::generateNew(rawPassword: $newPasswordField->getValueAsString()),
        );

        return true;
    }
}
