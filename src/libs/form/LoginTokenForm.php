<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\libs\auth\BackendLogin;
use actra\backend\libs\auth\LoginCodeResultEnum;
use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\form\component\LoginRequestField;
use actra\backend\libs\form\component\ReturnPathField;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\HiddenField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * The second step of the login: the code sent by email (`BackendLogin::logInWithCode()`). Texts in the language of the
 * request; the page requested before the login is kept in a hidden field.
 */
final class LoginTokenForm extends Form
{
    private readonly HiddenField $returnPathField;
    private readonly ?HiddenField $loginRequestField;
    private readonly TextField $tokenField;

    public function __construct(private readonly BackendLogin $login)
    {
        $context = $login->context;
        $messages = $context->messages;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'LoginTokenForm',
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
            formField: $this->tokenField = new TextField(
                name: 'token',
                label: HtmlText::fromText(text: $messages->auth->tokenLabel),
                value: null,
                requiredError: HtmlText::fromText(text: $messages->auth->tokenRequired),
            ),
        );
        $this->tokenField->autoFocus = true;
        $this->tokenField->renderRequiredAbbr = false;
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::fromText(text: $messages->auth->tokenSubmitLabel),
            ),
        );
    }

    /**
     * The logged-in user, `null` if the form was not sent or the code is invalid. Redirect to
     * `BackendLogin::findHandOverUrl()` or `getReturnPath()` then.
     */
    public function process(): ?MyAuthUser
    {
        if (!$this->validate()) {
            return null;
        }
        if ($this->login->logInWithCode(code: $this->tokenField->getValueAsString()) === LoginCodeResultEnum::INVALID) {
            $this->tokenField->addError(
                errorMessage: HtmlText::fromText(text: $this->login->context->messages->auth->tokenInvalid),
            );

            return null;
        }

        return $this->login->getCurrentUser();
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
