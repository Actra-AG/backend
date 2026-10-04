<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\libs\db\DbAuthToken;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\yuf\auth\Password;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\FormMessages;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;

final class PasswordResetForm extends Form
{
    private readonly PasswordField $newPasswordField;
    private readonly PasswordField $newPasswordConfirmField;

    public function __construct(private readonly DbAuthToken $dbAuthToken)
    {
        parent::__construct(name: 'PasswordResetForm', messages: FormMessages::german());
        $this->addCssClass(className: 'form');
        $this->addCssClass(className: 'form-login');
        $this->addField(
            formField: $this->newPasswordField = new PasswordField(
                name: 'newPassword',
                label: HtmlText::encoded(textContent: 'Neues Passwort'),
                requiredError: HtmlText::encoded(textContent: 'Bitte geben Sie das neue Passwort ein.'),
                purpose: PasswordPurposeEnum::NEW
            )
        );
        $this->addField(
            formField: $this->newPasswordConfirmField = new PasswordField(
                name: 'newPasswordConfirm',
                label: HtmlText::encoded(textContent: 'Neues Passwort bestätigen'),
                requiredError: HtmlText::encoded(textContent: 'Bitte bestätigen Sie das neue Passwort.'),
                purpose: PasswordPurposeEnum::NEW
            )
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::encoded(textContent: 'Speichern')
            )
        );
    }

    public function validateAndUpdatePassword(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $newPasswordField = $this->newPasswordField;
        if (mb_strlen(string: $newPasswordField->getValueAsString()) < 8) {
            $newPasswordField->addError(
                errorMessage: HtmlText::encoded(textContent: 'Das neue Passwort muss mindestens 8 Zeichen lang sein.')
            );
            return false;
        }
        if ($newPasswordField->getValueAsString() !== $this->newPasswordConfirmField->getValueAsString()) {
            $this->newPasswordConfirmField->addError(
                errorMessage: HtmlText::encoded(textContent: 'Die neuen Passwörter stimmen nicht überein.')
            );
            return false;
        }
        DbAuthUserRepository::setPassword(
            ID: $this->dbAuthToken->userID,
            newPassword: Password::generateNew(rawPassword: $newPasswordField->getValueAsString())
        );

        return true;
    }
}