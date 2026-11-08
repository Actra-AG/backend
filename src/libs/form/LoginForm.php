<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\libs\auth\BackendLogin;
use actra\backend\libs\auth\LoginCodeRequestResultEnum;
use actra\backend\libs\form\component\LoginRequestField;
use actra\backend\libs\form\component\ReturnPathField;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\HiddenField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * The first step of the login: the email address, to which the login code is sent (`BackendLogin::requestCode()`).
 * Texts in the language of the request; the page requested before the login is kept in a hidden field.
 */
final class LoginForm extends Form
{
    private readonly HiddenField $returnPathField;
    private readonly ?HiddenField $loginRequestField;
    private readonly EmailField $emailField;

    public function __construct(private readonly BackendLogin $login)
    {
        $context = $login->context;
        $messages = $context->messages;
        parent::__construct(context: $context->viewContext->formContext, name: 'LoginForm', messages: $messages->form);
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
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::fromText(text: $messages->auth->loginSubmitLabel),
            ),
        );
    }

    /**
     * Whether the form was sent and the code requested: continue with the page of the code then
     * (`BackendLogin::createNextStepUri()` with `getReturnPath()` and `getLoginRequestId()`). The answer is the same for
     * unknown addresses; only the rate limit is answered with an error.
     */
    public function process(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $result = $this->login->requestCode(email: $this->emailField->getValueAsString());
        if ($result === LoginCodeRequestResultEnum::RATE_LIMITED) {
            $this->addError(
                errorMessage: HtmlText::fromText(text: $this->login->context->messages->auth->tooManyAttempts),
            );

            return false;
        }

        return true;
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
