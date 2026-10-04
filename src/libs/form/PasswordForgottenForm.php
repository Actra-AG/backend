<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\backend\view\backend\php\loginPassword;
use actra\yuf\core\HttpRequest;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

final class PasswordForgottenForm extends Form
{
    private readonly EmailField $emailField;

    public function __construct()
    {
        $messages = ActraBackend::messages();
        parent::__construct(name: 'PasswordForgottenForm', messages: $messages->form);
        $this->addCssClass(className: 'form');
        $this->addCssClass(className: 'form-login');
        $this->addField(
            formField: $this->emailField = new EmailField(
                name: 'email',
                label: HtmlText::unencoded(textContent: $messages->common->emailLabel),
                value: null,
                invalidError: HtmlText::unencoded(textContent: $messages->auth->emailInvalid),
                requiredError: HtmlText::unencoded(textContent: $messages->auth->emailRequired),
            )
        );
        $this->emailField->autoFocus = true;
        $this->emailField->renderRequiredAbbr = false;
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::unencoded(textContent: $messages->common->send),
                cancelLink: loginPassword::getPath()
            )
        );
    }

    public function validateAndSendTokenEmail(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $dbAuthUser = DbAuthUserRepository::selectByEmail(email: $this->emailField->getValueAsString());
        if (
            $dbAuthUser === null
            || $dbAuthUser->isActive === false
            || $dbAuthUser->accessRightCollection->isEmpty()
            || $dbAuthUser->password === null
            || (
                $dbAuthUser->ipWhitelist !== []
                && !IpValidator::isInWhitelist(
                    whiteList: $dbAuthUser->ipWhitelist,
                    ipAddressToCheck: HttpRequest::getRemoteAddress()
                )
            )
        ) {
            return true;
        }
        AuthTokenTypeEnum::PASSWORD->createAndSend(
            dbAuthUser: $dbAuthUser,
            usedPasswordLogin: false
        );

        return true;
    }
}