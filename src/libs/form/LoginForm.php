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
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class LoginForm extends Form
{
    private readonly BackendViewContext $backendContext;
    private readonly EmailField $emailField;

    public function __construct(BackendViewContext $context)
    {
        $this->backendContext = $context;
        $messages = $this->backendContext->messages;
        parent::__construct(context: $context->viewContext->formContext, name: 'LoginForm', messages: $messages->form);
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
        $this->sendTokenIfAllowed();

        return true;
    }

    /**
     * Sends a token if the user may log in with it. The form answers the same in every case, so it does not reveal
     * whether the email address exists.
     */
    private function sendTokenIfAllowed(): void
    {
        $myAuthenticator = new MyAuthenticator(context: $this->backendContext);
        $sessionId = $this->backendContext->authSession->getSessionId();
        $ipAddress = $this->context->httpRequest->getRemoteAddress();
        $inputEmail = $this->emailField->getValueAsString();
        $dbAuthUser = $this->backendContext->repositories->users()->selectByEmail(email: $inputEmail);
        if ($dbAuthUser === null) {
            $myAuthenticator->logAuthResult(
                userId: null,
                sessionId: $sessionId,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResultEnum::ERROR_UNKNOWN_USER_NAME,
            );
            return;
        }
        if (
            $dbAuthUser->ipWhitelist !== []
            && !IpValidator::isInWhitelist(
                whiteList: $dbAuthUser->ipWhitelist,
                ipAddressToCheck: $ipAddress,
            )
        ) {
            $myAuthenticator->logAuthResult(
                userId: $dbAuthUser->id,
                sessionId: $sessionId,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResultEnum::ERROR_IP_NOT_ALLOWED,
            );
            return;
        }
        if ($dbAuthUser->isActive === false
            || $dbAuthUser->accessRightCollection->isEmpty()
        ) {
            $myAuthenticator->logAuthResult(
                userId: $dbAuthUser->id,
                sessionId: $sessionId,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResultEnum::ERROR_INACTIVE,
            );
            return;
        }
        if ($dbAuthUser->password !== null) {
            $myAuthenticator->logAuthResult(
                userId: $dbAuthUser->id,
                sessionId: $sessionId,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResultEnum::ERROR_NO_PASSWORD,
            );
            return;
        }
        new AuthTokens(context: $this->backendContext)->createAndSend(
            type: AuthTokenTypeEnum::LOGIN,
            dbAuthUser: $dbAuthUser,
            usedPasswordLogin: false,
        );
    }
}
