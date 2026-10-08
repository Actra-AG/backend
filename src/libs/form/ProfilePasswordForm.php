<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\view\backend\php\profile;
use actra\yuf\auth\Password;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;

final class ProfilePasswordForm extends Form
{
    private readonly ?PasswordField $currentPasswordField;
    private readonly ?PasswordField $newPasswordField;
    private readonly ?PasswordField $newPasswordConfirmField;

    public function __construct(
        private readonly DbAuthUser $dbAuthUser,
        bool $removePassword,
    ) {
        $messages = ActraBackend::messages();
        parent::__construct(name: 'ProfilePasswordForm', messages: $messages->form);
        $this->addCssClass(className: 'form');
        if ($dbAuthUser->password !== null) {
            $this->addField(
                formField: $this->currentPasswordField = new PasswordField(
                    name: 'oldPassword',
                    label: HtmlText::unencoded(textContent: $messages->profile->currentPasswordLabel),
                    requiredError: HtmlText::unencoded(textContent: $messages->profile->currentPasswordRequired),
                    purpose: PasswordPurposeEnum::CURRENT,
                ),
            );
        } else {
            $this->currentPasswordField = null;
        }
        if (!$removePassword) {
            $this->addField(
                formField: $this->newPasswordField = new PasswordField(
                    name: 'newPassword',
                    label: HtmlText::unencoded(textContent: $messages->common->newPasswordLabel),
                    requiredError: HtmlText::unencoded(textContent: $messages->common->newPasswordRequired),
                    purpose: PasswordPurposeEnum::NEW,
                ),
            );
            $this->addField(
                formField: $this->newPasswordConfirmField = new PasswordField(
                    name: 'newPasswordConfirm',
                    label: HtmlText::unencoded(textContent: $messages->common->newPasswordConfirmLabel),
                    requiredError: HtmlText::unencoded(textContent: $messages->common->newPasswordConfirmRequired),
                    purpose: PasswordPurposeEnum::NEW,
                ),
            );
        } else {
            $this->newPasswordField = null;
            $this->newPasswordConfirmField = null;
        }
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::unencoded(textContent: $messages->common->save),
                cancelLink: profile::getPath(),
            ),
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        $messages = ActraBackend::messages();
        $currentPassword = $this->dbAuthUser->password;
        if (
            $currentPassword !== null
            && $this->currentPasswordField !== null
            && !$currentPassword->isValid(rawPassword: $this->currentPasswordField->getValueAsString())
        ) {
            $this->currentPasswordField->addError(
                errorMessage: HtmlText::unencoded(textContent: $messages->profile->currentPasswordIncorrect),
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
        $newPasswordCheck = new NewPasswordCheck(messages: $messages->common);
        if (!$newPasswordCheck->isValid(
            newPasswordField: $newPasswordField,
            newPasswordConfirmField: $newPasswordConfirmField,
        )) {
            return false;
        }
        DbAuthUserRepository::setPassword(
            ID: $userID,
            newPassword: Password::generateNew(rawPassword: $newPasswordField->getValueAsString()),
        );
        return true;
    }
}
