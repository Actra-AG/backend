<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\libs\auth\MyAuthenticator;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\auth\AuthResult;
use actra\yuf\core\HttpRequest;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;
use actra\yuf\session\AbstractSessionHandler;

final class LoginForm extends Form
{
    private readonly EmailField $emailField;

    public function __construct()
    {
        $messages = ActraBackend::messages();
        parent::__construct(name: 'LoginForm', messages: $messages->form);
        $this->addCssClass(className: 'form');
        $this->addCssClass(className: 'form-login');
        $this->addField(
            formField: $this->emailField = new EmailField(
                name: 'email',
                label: HtmlText::unencoded(textContent: $messages->common->emailLabel),
                value: null,
                invalidError: HtmlText::unencoded(textContent: $messages->auth->emailInvalid),
                requiredError: HtmlText::unencoded(textContent: $messages->auth->emailRequired),
            ),
        );
        $this->emailField->autoFocus = true;
        $this->emailField->renderRequiredAbbr = false;
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::unencoded(textContent: $messages->auth->loginSubmitLabel),
            ),
        );
    }

    public function process(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        return $this->checkCredentials();
    }

    private function checkCredentials(): bool
    {
        $myAuthenticator = MyAuthenticator::get();
        $sessionID = AbstractSessionHandler::getSessionHandler()->getID();
        $ipAddress = HttpRequest::getRemoteAddress();
        $inputEmail = $this->emailField->getValueAsString();
        $dbAuthUser = DbAuthUserRepository::selectByEmail(email: $inputEmail);
        if ($dbAuthUser === null) {
            $myAuthenticator->logAuthResult(
                userID: null,
                sessionID: $sessionID,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResult::ERROR_UNKNOWN_USER_NAME,
            );
            return true;
        }
        if (
            $dbAuthUser->ipWhitelist !== []
            && !IpValidator::isInWhitelist(
                whiteList: $dbAuthUser->ipWhitelist,
                ipAddressToCheck: $ipAddress,
            )
        ) {
            $myAuthenticator->logAuthResult(
                userID: $dbAuthUser->ID,
                sessionID: $sessionID,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResult::ERROR_IP_NOT_ALLOWED,
            );
            return true;
        }
        if ($dbAuthUser->isActive === false
            || $dbAuthUser->accessRightCollection->isEmpty()
        ) {
            $myAuthenticator->logAuthResult(
                userID: $dbAuthUser->ID,
                sessionID: $sessionID,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResult::ERROR_INACTIVE,
            );
            return true;
        }
        if ($dbAuthUser->password !== null) {
            $myAuthenticator->logAuthResult(
                userID: $dbAuthUser->ID,
                sessionID: $sessionID,
                ip: $ipAddress,
                userName: $inputEmail,
                authResult: AuthResult::ERROR_NO_PASSWORD,
            );
            return true;
        }
        AuthTokenTypeEnum::LOGIN->createAndSend(
            dbAuthUser: $dbAuthUser,
            usedPasswordLogin: false,
        );
        return true;
    }
}
