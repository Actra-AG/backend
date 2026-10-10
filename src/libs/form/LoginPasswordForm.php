<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\auth\AuthTokens;
use actra\backend\libs\auth\MyAuthenticator;
use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\form\component\ReturnPathField;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\HiddenField;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class LoginPasswordForm extends Form
{
    private readonly BackendViewContext $backendContext;
    private readonly HiddenField $returnPathField;
    private readonly EmailField $emailField;
    private readonly PasswordField $passwordField;

    public function __construct(BackendViewContext $context)
    {
        $this->backendContext = $context;
        $messages = $this->backendContext->messages;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'LoginPasswordForm',
            messages: $messages->form,
        );
        $this->addCssClass(className: 'form');
        $this->addCssClass(className: 'form-login');
        $this->addField(
            formField: $this->emailField = new EmailField(
                name: 'email',
                label: HtmlText::fromText(text: $messages->common->emailLabel),
                value: null,
                invalidError: HtmlText::fromText(text: $messages->auth->emailInvalid),
                requiredError: HtmlText::fromText(text: $messages->auth->emailRequired),
            ),
        );
        $this->emailField->autoFocus = true;
        $this->emailField->renderRequiredAbbr = false;
        $this->addField(
            formField: $this->passwordField = new PasswordField(
                name: 'password',
                label: HtmlText::fromText(text: $messages->auth->passwordLabel),
                requiredError: HtmlText::fromText(text: $messages->auth->passwordRequired),
                purpose: PasswordPurposeEnum::CURRENT,
            ),
        );
        $this->passwordField->renderRequiredAbbr = false;
        $this->addField(
            formField: $this->returnPathField = ReturnPathField::create(httpRequest: $context->viewContext->httpRequest),
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::fromText(text: $messages->auth->loginPasswordSubmitLabel),
                cancelLink: $this->backendContext->paths->passwordForgotten(),
                cancelLabel: HtmlText::fromText(text: $messages->auth->passwordForgottenLinkLabel),
            ),
        );
    }

    public function process(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        if (!$this->checkCredentials()) {
            $this->addError(
                errorMessage: HtmlText::fromText(text: $this->backendContext->messages->auth->credentialsInvalid),
            );
            return false;
        }

        return true;
    }

    private function checkCredentials(): bool
    {
        $result = new MyAuthenticator(context: $this->backendContext)->verifyPassword(
            userName: $this->emailField->getValueAsString(),
            inputPassword: $this->passwordField->getValueAsString(),
        );
        if (!$result instanceof MyAuthUser) {
            return false;
        }
        new AuthTokens(context: $this->backendContext)->createAndSend(
            type: AuthTokenTypeEnum::LOGIN,
            dbAuthUser: $result->dbAuthUser,
            usedPasswordLogin: true,
        );

        return true;
    }

    /**
     * The page requested before the login (validated local path), `null` without one.
     */
    public function getReturnPath(): ?string
    {
        return ReturnPathField::getReturnPath(hiddenField: $this->returnPathField);
    }
}
