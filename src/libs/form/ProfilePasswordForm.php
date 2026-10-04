<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\view\backend\php\profile;
use actra\yuf\auth\Password;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\FormMessages;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;

final class ProfilePasswordForm extends Form
{
    private readonly ?PasswordField $currentPasswordField;
    private readonly ?PasswordField $newPasswordField;
    private readonly ?PasswordField $newPasswordConfirmField;

    public function __construct(
        private readonly DbAuthUser $dbAuthUser,
        bool $removePassword
    ) {
        parent::__construct(name: 'ProfilePasswordForm', messages: FormMessages::german());
        $this->addCssClass(className: 'form');
        if ($dbAuthUser->password !== null) {
            $this->addField(
                formField: $this->currentPasswordField = new PasswordField(
                    name: 'oldPassword',
                    label: HtmlText::encoded(textContent: 'Aktuelles Passwort'),
                    requiredError: HtmlText::encoded(textContent: 'Bitte geben Sie das aktuelle Passwort ein.'),
                    purpose: PasswordPurposeEnum::CURRENT
                )
            );
        } else {
            $this->currentPasswordField = null;
        }
        if (!$removePassword) {
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
        } else {
            $this->newPasswordField = null;
            $this->newPasswordConfirmField = null;
        }
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::encoded(textContent: 'Speichern'),
                cancelLink: profile::getPath()
            )
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        $currentPassword = $this->dbAuthUser->password;
        if (
            $currentPassword !== null
            && $this->currentPasswordField !== null
            && !$currentPassword->isValid(rawPassword: $this->currentPasswordField->getValueAsString())
        ) {
            $this->currentPasswordField->addError(
                errorMessage: HtmlText::encoded(textContent: 'Das aktuelle Passwort ist nicht korrekt.')
            );
            return false;
        }
        $userID = $this->dbAuthUser->ID;
        $newPasswordField = $this->newPasswordField;
        $newPasswordConfirmField = $this->newPasswordConfirmField;
        if ($newPasswordField === null || $newPasswordConfirmField === null) {
            DbAuthUserRepository::removePassword(ID: $userID);
            return true;
        }
        if (mb_strlen(string: $newPasswordField->getValueAsString()) < 8) {
            $newPasswordField->addError(
                errorMessage: HtmlText::encoded(textContent: 'Das neue Passwort muss mindestens 8 Zeichen lang sein.')
            );
            return false;
        }
        if ($newPasswordField->getValueAsString() !== $newPasswordConfirmField->getValueAsString()) {
            $newPasswordConfirmField->addError(
                errorMessage: HtmlText::encoded(textContent: 'Die neuen Passwörter stimmen nicht überein.')
            );
            return false;
        }
        DbAuthUserRepository::setPassword(
            ID: $userID,
            newPassword: Password::generateNew(rawPassword: $newPasswordField->getValueAsString())
        );
        return true;
    }
}