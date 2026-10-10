<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\db\DbAuthUser;
use actra\yuf\auth\Password;
use actra\yuf\core\HttpResponse;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class ProfilePasswordForm extends Form
{
    private readonly BackendViewContext $backendContext;
    private readonly ?PasswordField $currentPasswordField;
    private readonly ?PasswordField $newPasswordField;
    private readonly ?PasswordField $newPasswordConfirmField;

    public function __construct(
        BackendViewContext $context,
        private readonly DbAuthUser $dbAuthUser,
        bool $removePassword,
    ) {
        $this->backendContext = $context;
        $messages = $this->backendContext->messages;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'ProfilePasswordForm',
            messages: $messages->form,
        );
        $this->addCssClass(className: 'form');
        if ($dbAuthUser->password !== null) {
            $this->addField(
                formField: $this->currentPasswordField = new PasswordField(
                    name: 'oldPassword',
                    label: HtmlText::fromText(text: $messages->profile->currentPasswordLabel),
                    requiredError: HtmlText::fromText(text: $messages->profile->currentPasswordRequired),
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
            NewPasswordRules::apply(
                newPasswordField: $this->newPasswordField,
                newPasswordConfirmField: $this->newPasswordConfirmField,
                messages: $messages->common,
            );
        } else {
            $this->newPasswordField = null;
            $this->newPasswordConfirmField = null;
        }
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::fromText(text: $messages->common->save),
                cancelLink: $this->backendContext->paths->profile(),
            ),
        );
    }

    /**
     * Checks the current password (counted like a login attempt: at the limit the user is locked and logged out),
     * then sets or removes the password and ends the other sessions and the open tokens of the user.
     */
    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        $context = $this->backendContext;
        $messages = $context->messages;
        $users = $context->repositories->users();
        $userId = $this->dbAuthUser->id;
        $currentPassword = $this->dbAuthUser->password;
        if ($currentPassword !== null && $this->currentPasswordField !== null) {
            $rawPassword = $this->currentPasswordField->getValueAsString();
            if (
                !$users->registerWrongPasswordAttempt(
                    id: $userId,
                    maxAllowedWrongPasswordAttempts: $context->actraBackend->actraBackendSettings
                        ->maxAllowedLoginAttempts,
                )
            ) {
                $context->authSession->logOut();
                HttpResponse::redirectAndExit(
                    relativeOrAbsoluteUri: $context->paths->login(),
                    httpRequest: $context->viewContext->httpRequest,
                    responseSender: $context->viewContext->responseSender,
                );
            }
            if (!$currentPassword->isValid(rawPassword: $rawPassword)) {
                $this->currentPasswordField->addError(
                    errorMessage: HtmlText::fromText(text: $messages->profile->currentPasswordIncorrect),
                );
                return false;
            }
            $users->releaseWrongPasswordAttempt(id: $userId);
        }
        $newPasswordField = $this->newPasswordField;
        $newPasswordConfirmField = $this->newPasswordConfirmField;
        if ($newPasswordField === null || $newPasswordConfirmField === null) {
            $users->removePassword(id: $userId);
        } else {
            $users->setPassword(
                id: $userId,
                newPassword: Password::generateNew(rawPassword: $newPasswordField->getValueAsString()),
            );
        }
        $authSession = $context->authSession;
        $context->repositories->sessions()->deleteOthersByUserId(
            userId: $userId,
            keepSessionId: $authSession->getAuthSessionId(),
        );
        $context->repositories->tokens()->deleteUnclaimedByUserId(userId: $userId);
        // A new session ID (and CSRF token) for the session that stays logged in
        $authSession->logIn(authSessionId: $authSession->getAuthSessionId());

        return true;
    }
}
