<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\libs\auth\BackendLogin;
use actra\backend\libs\auth\PasswordLoginResultEnum;
use actra\backend\libs\form\component\LoginRequestField;
use actra\backend\libs\form\component\ReturnPathField;
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
    private readonly HiddenField $returnPathField;
    private readonly ?HiddenField $loginRequestField;
    private readonly EmailField $emailField;
    private readonly PasswordField $passwordField;

    public function __construct(private readonly BackendLogin $login)
    {
        $context = $login->context;
        $messages = $context->messages;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'LoginPasswordForm',
            messages: $messages->form,
        );
        $this->addCssClass(className: 'form');
        $this->addCssClass(className: 'form-login');
        // Hidden fields first (after yuf's CSRF field): the button row follows the last visible field directly
        $this->addField(
            formField: $this->returnPathField = ReturnPathField::create(httpRequest: $context->viewContext->httpRequest),
        );
        $this->loginRequestField = LoginRequestField::create(login: $login);
        if ($this->loginRequestField !== null) {
            $this->addField(formField: $this->loginRequestField);
        }
        $this->addField(
            formField: $this->emailField = new EmailField(
                name: 'email',
                label: HtmlText::fromText(text: $messages->common->emailLabel),
                value: null,
                invalidError: HtmlText::fromText(text: $messages->auth->emailInvalid),
                mailDomainResolver: $context->viewContext->formContext->mailDomainResolver,
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
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::fromText(text: $messages->auth->loginPasswordSubmitLabel),
                cancelLink: $context->paths->passwordForgotten(),
                cancelLabel: HtmlText::fromText(text: $messages->auth->passwordForgottenLinkLabel),
            ),
        );
    }

    public function process(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $result = $this->login->requestCodeWithPassword(
            email: $this->emailField->getValueAsString(),
            password: $this->passwordField->getValueAsString(),
        );
        if ($result === PasswordLoginResultEnum::CODE_SENT) {
            return true;
        }
        $messages = $this->login->context->messages->auth;
        // The rate limit counts unknown addresses the same, so its message does not tell whether one exists
        $this->addError(errorMessage: HtmlText::fromText(
            text: $result === PasswordLoginResultEnum::RATE_LIMITED
                ? $messages->tooManyAttempts
                : $messages->credentialsInvalid,
        ));

        return false;
    }

    /**
     * The page requested before the login (validated local path), `null` without one.
     */
    public function getReturnPath(): ?string
    {
        return ReturnPathField::getReturnPath(hiddenField: $this->returnPathField);
    }

    /**
     * The ID of the login request of a site (login host, `BackendLogin::findLoginRequest()`), `null` without one; it is
     * checked again by `BackendLogin::findHandOverUrl()`.
     */
    public function getLoginRequestId(): ?string
    {
        return LoginRequestField::getLoginRequestId(hiddenField: $this->loginRequestField);
    }
}
