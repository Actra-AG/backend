<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\auth\AuthTokens;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class PasswordForgottenForm extends Form
{
    private readonly BackendViewContext $backendContext;
    private readonly EmailField $emailField;

    public function __construct(BackendViewContext $context)
    {
        $this->backendContext = $context;
        $messages = $this->backendContext->messages;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'PasswordForgottenForm',
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
                mailDomainResolver: $context->viewContext->formContext->mailDomainResolver,
                requiredError: HtmlText::fromText(text: $messages->auth->emailRequired),
            ),
        );
        $this->emailField->autoFocus = true;
        $this->emailField->renderRequiredAbbr = false;
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::fromText(text: $messages->common->send),
                cancelLink: $this->backendContext->paths->loginPassword(),
            ),
        );
    }

    public function validateAndSendTokenEmail(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $inputEmail = $this->emailField->getValueAsString();
        // A reset link is a token request: counted with the login rate limit, not with precheck(), which would refuse
        // a locked-out user (the reset is the way out of the lock-out)
        $loginRateLimit = $this->backendContext->actraBackend->actraBackendSettings->loginRateLimit;
        if (
            $loginRateLimit !== null
            && !$loginRateLimit->consume(
                userName: $inputEmail,
                ipAddress: $this->context->httpRequest->getRemoteAddress(),
            )->isAllowed
        ) {
            $this->addError(
                errorMessage: HtmlText::fromText(text: $this->backendContext->messages->auth->tooManyAttempts),
            );

            return false;
        }
        $dbAuthUser = $this->backendContext->repositories->users()->selectByEmail(email: $inputEmail);
        if (
            $dbAuthUser === null
            || $dbAuthUser->isActive === false
            || $dbAuthUser->accessRightCollection->isEmpty()
            || $dbAuthUser->password === null
            || (
                $dbAuthUser->ipWhitelist !== []
                && !IpValidator::isInWhitelist(
                    whiteList: $dbAuthUser->ipWhitelist,
                    ipAddressToCheck: $this->context->httpRequest->getRemoteAddress(),
                )
            )
        ) {
            return true;
        }
        new AuthTokens(context: $this->backendContext)->createAndSend(
            type: AuthTokenTypeEnum::PASSWORD,
            dbAuthUser: $dbAuthUser,
            usedPasswordLogin: false,
        );

        return true;
    }
}
