<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
use actra\backend\libs\auth\MyAuthenticator;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\backend\view\backend\php\passwordForgotten;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;

final class LoginPasswordForm extends Form
{
    private readonly EmailField $emailField;
    private readonly PasswordField $passwordField;

    public function __construct(BackendViewContext $context)
    {
        $messages = ActraBackend::messages();
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
                cancelLink: passwordForgotten::getPath(),
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
                errorMessage: HtmlText::fromText(text: ActraBackend::messages()->auth->credentialsInvalid),
            );
            return false;
        }

        return true;
    }

    private function checkCredentials(): bool
    {
        $myAuthenticator = MyAuthenticator::get();
        $sessionID = ActraBackend::get()->getAuthSession()->getSessionId();
        $ipAddress = $this->context->httpRequest->getRemoteAddress();
        $inputEmail = $this->emailField->getValueAsString();
        $dbAuthUser = DbAuthUserRepository::selectByEmail(email: $inputEmail);
        if ($dbAuthUser === null) {
            $myAuthenticator->logAuthResult(
                userId: null,
                sessionId: $sessionID,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResultEnum::ERROR_UNKNOWN_USER_NAME,
            );
            return false;
        }
        if (
            $dbAuthUser->ipWhitelist !== []
            && !IpValidator::isInWhitelist(
                whiteList: $dbAuthUser->ipWhitelist,
                ipAddressToCheck: $ipAddress,
            )
        ) {
            $myAuthenticator->logAuthResult(
                userId: $dbAuthUser->ID,
                sessionId: $sessionID,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResultEnum::ERROR_IP_NOT_ALLOWED,
            );
            return false;
        }
        if ($dbAuthUser->isActive === false
            || $dbAuthUser->accessRightCollection->isEmpty()
        ) {
            $myAuthenticator->logAuthResult(
                userId: $dbAuthUser->ID,
                sessionId: $sessionID,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResultEnum::ERROR_INACTIVE,
            );
            return false;
        }
        if ($dbAuthUser->password === null) {
            $myAuthenticator->logAuthResult(
                userId: $dbAuthUser->ID,
                sessionId: $sessionID,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE,
            );
            return false;
        }
        if ($dbAuthUser->wrongLoginAttempts >= ActraBackend::get()->actraBackendSettings->maxAllowedLoginAttempts) {
            $myAuthenticator->logAuthResult(
                userId: $dbAuthUser->ID,
                sessionId: $sessionID,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResultEnum::ERROR_OUT_TRIED,
            );
            return false;
        }
        if (!$dbAuthUser->password->isValid(rawPassword: $this->passwordField->getValueAsString())) {
            DbAuthUserRepository::increaseWrongPasswordAttempts(ID: $dbAuthUser->ID);
            $myAuthenticator->logAuthResult(
                userId: $dbAuthUser->ID,
                sessionId: $sessionID,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResultEnum::ERROR_WRONG_PASSWORD,
            );
            return false;
        }
        AuthTokenTypeEnum::LOGIN->createAndSend(
            dbAuthUser: $dbAuthUser,
            usedPasswordLogin: true,
        );
        return true;
    }
}
