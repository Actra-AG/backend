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
use actra\backend\libs\db\DbAuthUser;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\Password;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;
use LogicException;

/**
 * @internal
 */
final class LoginPasswordForm extends Form
{
    private readonly BackendViewContext $backendContext;
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
        $inputEmail = $this->emailField->getValueAsString();
        $inputPassword = $this->passwordField->getValueAsString();
        $dbAuthUser = $this->backendContext->repositories->users()->selectByEmail(email: $inputEmail);
        $rejection = $this->findRejection(dbAuthUser: $dbAuthUser);
        if ($rejection !== null) {
            // Same time as a password check, so the response time does not tell whether the email address exists
            Password::spendVerificationTime(rawPassword: $inputPassword);
            $this->logAuthResult(dbAuthUser: $dbAuthUser, inputEmail: $inputEmail, authResult: $rejection);

            return false;
        }
        $password = $dbAuthUser?->password;
        if ($dbAuthUser === null || $password === null) {
            throw new LogicException(message: 'findRejection() rejects users without password.');
        }
        if (!$password->isValid(rawPassword: $inputPassword)) {
            $this->backendContext->repositories->users()->increaseWrongPasswordAttempts(id: $dbAuthUser->id);
            $this->logAuthResult(
                dbAuthUser: $dbAuthUser,
                inputEmail: $inputEmail,
                authResult: AuthResultEnum::ERROR_WRONG_PASSWORD,
            );

            return false;
        }
        if ($password->needsRehash()) {
            // Legacy or outdated hash: store the current algorithm (yuf's Authenticator does not see the password here)
            $this->backendContext->repositories->users()->updatePasswordHash(
                id: $dbAuthUser->id,
                password: Password::generateNew(rawPassword: $inputPassword),
            );
        }
        new AuthTokens(context: $this->backendContext)->createAndSend(
            type: AuthTokenTypeEnum::LOGIN,
            dbAuthUser: $dbAuthUser,
            usedPasswordLogin: true,
        );

        return true;
    }

    private function findRejection(?DbAuthUser $dbAuthUser): ?AuthResultEnum
    {
        if ($dbAuthUser === null) {
            return AuthResultEnum::ERROR_UNKNOWN_USER_NAME;
        }
        if (
            $dbAuthUser->ipWhitelist !== []
            && !IpValidator::isInWhitelist(
                whiteList: $dbAuthUser->ipWhitelist,
                ipAddressToCheck: $this->context->httpRequest->getRemoteAddress(),
            )
        ) {
            return AuthResultEnum::ERROR_IP_NOT_ALLOWED;
        }
        if ($dbAuthUser->isActive === false || $dbAuthUser->accessRightCollection->isEmpty()) {
            return AuthResultEnum::ERROR_INACTIVE;
        }
        if ($dbAuthUser->password === null) {
            return AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE;
        }
        $settings = $this->backendContext->actraBackend->actraBackendSettings;
        if ($dbAuthUser->wrongLoginAttempts >= $settings->maxAllowedLoginAttempts) {
            return AuthResultEnum::ERROR_OUT_TRIED;
        }

        return null;
    }

    private function logAuthResult(?DbAuthUser $dbAuthUser, string $inputEmail, AuthResultEnum $authResult): void
    {
        new MyAuthenticator(context: $this->backendContext)->logAuthResult(
            userId: $dbAuthUser?->id,
            sessionId: $this->backendContext->authSession->getSessionId(),
            ip: $this->context->httpRequest->getRemoteAddress(),
            userName: $inputEmail,
            authResult: $authResult,
        );
    }
}
