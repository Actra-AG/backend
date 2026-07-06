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
use actra\yuf\html\HtmlText;

class ProfilePasswordForm extends Form
{
    private readonly ?PasswordField $currentPasswordField;
    private readonly ?PasswordField $newPasswordField;
    private readonly ?PasswordField $newPasswordConfirmField;

    public function __construct(
        private readonly DbAuthUser $dbAuthUser,
        bool $removePassword
    ) {
        parent::__construct(name: 'ProfilePasswordForm');
        $this->addCssClass(className: 'form');
        if ($dbAuthUser->password !== null) {
            $this->addField(
                formField: $this->currentPasswordField = new PasswordField(
                    name: 'oldPassword',
                    label: HtmlText::encoded(textContent: 'Aktuelles Passwort'),
                    requiredError: HtmlText::encoded(textContent: 'Bitte geben Sie das aktuelle Passwort ein.')
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
                    requiredError: HtmlText::encoded(textContent: 'Bitte geben Sie das neue Passwort ein.')
                )
            );
            $this->addField(
                formField: $this->newPasswordConfirmField = new PasswordField(
                    name: 'newPasswordConfirm',
                    label: HtmlText::encoded(textContent: 'Neues Passwort bestätigen'),
                    requiredError: HtmlText::encoded(textContent: 'Bitte bestätigen Sie das neue Passwort.')
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
        if (
            $this->currentPasswordField !== null
            && !$this->dbAuthUser->password->isValid(rawPassword: $this->currentPasswordField->getRawValue())
        ) {
            $this->currentPasswordField->addError(
                errorMessage: 'Das aktuelle Passwort ist nicht korrekt.',
                isEncodedForRendering: true
            );
            return false;
        }
        $userID = $this->dbAuthUser->ID;
        $newPasswordField = $this->newPasswordField;
        if ($newPasswordField === null) {
            DbAuthUserRepository::removePassword(ID: $userID);
            return true;
        }
        if (mb_strlen(string: $newPasswordField->getRawValue()) < 8) {
            $newPasswordField->addError(
                errorMessage: 'Das neue Passwort muss mindestens 8 Zeichen lang sein.',
                isEncodedForRendering: true
            );
            return false;
        }
        if ($newPasswordField->getRawValue() !== $this->newPasswordConfirmField->getRawValue()) {
            $this->newPasswordConfirmField->addError(
                errorMessage: 'Die neuen Passwörter stimmen nicht überein.',
                isEncodedForRendering: true
            );
            return false;
        }
        DbAuthUserRepository::setPassword(
            ID: $userID,
            newPassword: Password::generateNew(rawPassword: $newPasswordField->getRawValue())
        );
        return true;
    }
}