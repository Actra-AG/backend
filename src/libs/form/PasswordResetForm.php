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
use actra\yuf\exception\NotFoundException;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;
use Throwable;

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
        NewPasswordRules::apply(
            newPasswordField: $this->newPasswordField,
            newPasswordConfirmField: $this->newPasswordConfirmField,
            messages: $messages->common,
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::fromText(text: $messages->common->save),
            ),
        );
    }

    /**
     * Sets the new password and ends the reset link (claimed atomically, so a link sets a password only once), all
     * sessions and the open tokens of the user.
     *
     * @throws NotFoundException if the link was used in the meantime (e.g. by a parallel request)
     */
    public function validateAndUpdatePassword(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $context = $this->backendContext;
        $repositories = $context->repositories;
        $userId = $this->dbAuthToken->userId;
        $db = $repositories->db();
        $db->beginTransaction();
        try {
            if (!$repositories->tokens()->claim(dbAuthToken: $this->dbAuthToken, clientData: $context->clientData)) {
                throw new NotFoundException();
            }
            $repositories->users()->setPassword(
                id: $userId,
                newPassword: Password::generateNew(rawPassword: $this->newPasswordField->getValueAsString()),
            );
            $repositories->sessions()->deleteByUserId(userId: $userId);
            $repositories->tokens()->deleteUnclaimedByUserId(userId: $userId);
            $db->commit();
        } catch (Throwable $throwable) {
            $db->rollBack();
            throw $throwable;
        }

        return true;
    }
}
