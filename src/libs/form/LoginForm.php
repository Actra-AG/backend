<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\libs\auth\MyAuthenticator;
use actra\backend\libs\db\DbAuthIpWhitelistRepository;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\yuf\core\HttpRequest;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

class LoginForm extends Form
{
    private readonly EmailField $emailField;

    public function __construct()
    {
        parent::__construct(name: 'LoginForm');
        $this->addCssClass(className: 'form');
        $this->addCssClass(className: 'form-login');
        $this->addField(
            formField: $this->emailField = new EmailField(
                name: 'email',
                label: HtmlText::encoded(textContent: 'E-Mail'),
                value: null,
                invalidError: HtmlText::encoded(textContent: 'Sie haben eine ungültige E-Mail-Adresse eingegeben.'),
                requiredError: HtmlText::encoded(textContent: 'Geben Sie Ihre E-Mail-Adresse ein.'),
            )
        );
        $this->emailField->autoFocus = true;
        $this->emailField->renderRequiredAbbr = false;
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::encoded(textContent: 'weiter'),
            )
        );
    }

    public function process(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $dbAuthUser = DbAuthUserRepository::selectByEmail(email: $this->emailField->getRawValue());
        if (is_null(value: $dbAuthUser)) {
            return true;
        }
        if (!DbAuthIpWhitelistRepository::listForUserId(userID: $dbAuthUser->ID)->check(
            ip: HttpRequest::getRemoteAddress(),
            returnTrueIfEmpty: true
        )) {
            return true;
        }
        MyAuthenticator::get()->createAndSendAuthToken(dbAuthUser: $dbAuthUser);

        return true;
    }
}