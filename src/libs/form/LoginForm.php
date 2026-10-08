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
    private readonly EmailField $emailField;

    public function __construct(BackendViewContext $context)
    {
        $messages = ActraBackend::messages();
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
                userId: $dbAuthUser->ID,
                sessionId: $sessionID,
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
                userId: $dbAuthUser->ID,
                sessionId: $sessionID,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResultEnum::ERROR_INACTIVE,
            );
            return;
        }
        if ($dbAuthUser->password !== null) {
            $myAuthenticator->logAuthResult(
                userId: $dbAuthUser->ID,
                sessionId: $sessionID,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResultEnum::ERROR_NO_PASSWORD,
            );
            return;
        }
        AuthTokenTypeEnum::LOGIN->createAndSend(
            session: ActraBackend::get()->getSession(),
            dbAuthUser: $dbAuthUser,
            usedPasswordLogin: false,
        );
    }
}
