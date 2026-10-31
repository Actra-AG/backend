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
use actra\backend\libs\form\component\ReturnPathField;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\HiddenField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class LoginForm extends Form
{
    private readonly BackendViewContext $backendContext;
    private readonly HiddenField $returnPathField;
    private readonly EmailField $emailField;

    public function __construct(BackendViewContext $context)
    {
        $this->backendContext = $context;
        $messages = $this->backendContext->messages;
        parent::__construct(context: $context->viewContext->formContext, name: 'LoginForm', messages: $messages->form);
        $this->addCssClass(className: 'form');
        $this->addCssClass(className: 'form-login');
        // Hidden fields first (after yuf's CSRF field): the button row follows the last visible field directly
        $this->addField(
            formField: $this->returnPathField = ReturnPathField::create(httpRequest: $context->viewContext->httpRequest),
        );
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

    public function process(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        return $this->sendTokenIfAllowed();
    }

    /**
     * Sends a token if the user may log in with it. The form answers the same in every case, so it does not reveal
     * whether the email address exists; only the rate limit (which counts unknown addresses the same) is answered
     * with an error.
     *
     * @return bool Whether the request was not blocked by the rate limit
     */
    private function sendTokenIfAllowed(): bool
    {
        $myAuthenticator = new MyAuthenticator(context: $this->backendContext);
        $myAuthUser = $myAuthenticator->findTokenLoginUser(email: $this->emailField->getValueAsString());
        if ($myAuthenticator->authResult === AuthResultEnum::ERROR_RATE_LIMITED) {
            $this->addError(
                errorMessage: HtmlText::fromText(text: $this->backendContext->messages->auth->tooManyAttempts),
            );

            return false;
        }
        if ($myAuthUser === null) {
            return true;
        }
        new AuthTokens(context: $this->backendContext)->createAndSend(
            type: AuthTokenTypeEnum::LOGIN,
            dbAuthUser: $myAuthUser->dbAuthUser,
            usedPasswordLogin: false,
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
