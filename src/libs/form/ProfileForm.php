<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\libs\db\DbAuthApiKeyRepository;
use actra\backend\libs\db\DbAuthIpWhitelistRepository;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\form\component\IpWhitelistField;
use actra\yuf\core\HttpRequest;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\CsrfTokenField;
use actra\yuf\form\component\field\PhoneNumberField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\component\FormField;
use actra\yuf\html\HtmlText;

class ProfileForm extends Form
{
    private readonly TextField $firstNameField;
    private readonly TextField $lastNameField;
    private readonly PhoneNumberField $phoneNumberField;
    private readonly IpWhitelistField $ipWhitelistField;

    public function __construct(private readonly DbAuthUser $dbAuthUser)
    {
        parent::__construct(name: 'ProfileForm');
        $this->addCssClass(className: 'form');
        $this->addField(
            formField: $this->firstNameField = new TextField(
                name: 'firstName',
                label: HtmlText::encoded(textContent: 'Vorname'),
                value: $dbAuthUser->firstName,
                requiredError: HtmlText::encoded(textContent: 'Bitte geben Sie den Vornamen ein.')
            )
        );
        $this->addField(
            formField: $this->lastNameField = new TextField(
                name: 'lastName',
                label: HtmlText::encoded(textContent: 'Nachname'),
                value: $dbAuthUser->lastName,
                requiredError: HtmlText::encoded(textContent: 'Bitte geben Sie den Nachnamen ein.')
            )
        );
        $this->addField(
            formField: $this->phoneNumberField = new PhoneNumberField(
                name: 'phone',
                label: HtmlText::encoded(textContent: 'Telefon'),
                value: $dbAuthUser->phone,
                invalidErrorMessage: HtmlText::encoded(textContent: 'Bitte geben Sie eine gültige Telefonnummer ein.')
            )
        );
        $this->addField(
            formField: $this->ipWhitelistField = new IpWhitelistField(
                name: 'ipWhitelistField',
                label: HtmlText::encoded(textContent: 'IP-Whitelist'),
                value: $dbAuthUser->ipWhitelist,
                invalidErrorMessage: HtmlText::encoded(textContent: 'Ungültige IP-Adresse [ipAddress]')
            )
        );
        $this->ipWhitelistField->fieldInfo = HtmlText::encoded(
            textContent: 'Eine IP-Adresse pro Zeile. Achten Sie darauf, Ihre aktuelle IP-Adresse nicht auszuschliessen.'
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::encoded(textContent: 'Speichern')
            )
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        if (!$this->hasChanges()) {
            $this->addError(
                errorMessage: 'Es wurden keine Änderungen vorgenommen.',
                isEncodedForRendering: true
            );

            return false;
        }
        $currentIpAddress = HttpRequest::getRemoteAddress();
        $newIpWhitelist = $this->ipWhitelistField->getRawValue();
        if (
            $newIpWhitelist === []
            && DbAuthApiKeyRepository::hasByUserID(userID: $this->dbAuthUser->ID)
        ) {
            $this->addError(
                errorMessage: 'Der API-Key muss entfernt werden, bevor die IP-Whitelist geleert werden kann.',
                isEncodedForRendering: true
            );

            return false;
        }
        if (!$this->currentIpIsAllowed(
            currentIpAddress: $currentIpAddress,
            ipWhitelist: $newIpWhitelist
        )) {
            $this->addError(
                errorMessage: 'Die IP-Whitelist muss Ihre aktuelle IP-Adresse ' . $currentIpAddress . ' erlauben.',
                isEncodedForRendering: true
            );

            return false;
        }
        $userID = $this->dbAuthUser->ID;
        DbAuthUserRepository::update(
            ID: $userID,
            email: $this->dbAuthUser->email,
            phone: $this->phoneNumberField->getRawValue(),
            active: $this->dbAuthUser->isActive,
            firstName: $this->firstNameField->getRawValue(),
            lastName: $this->lastNameField->getRawValue()
        );
        foreach ($newIpWhitelist as $ip) {
            if (!in_array(
                needle: $ip,
                haystack: $this->dbAuthUser->ipWhitelist
            )) {
                DbAuthIpWhitelistRepository::insert(
                    userID: $userID,
                    ipAddress: $ip
                );
            }
        }
        foreach ($this->dbAuthUser->ipWhitelist as $ip) {
            if (!in_array(
                needle: $ip,
                haystack: $newIpWhitelist
            )) {
                DbAuthIpWhitelistRepository::delete(
                    userID: $userID,
                    ipAddress: $ip
                );
            }
        }

        return true;
    }

    private function hasChanges(): bool
    {
        return array_any(
            array: $this->getAllFields(),
            callback: function (FormField $field) {
                if ($field instanceof CsrfTokenField) {
                    return false;
                }
                return $field->valueHasChanged();
            }
        );
    }

    private function currentIpIsAllowed(
        string $currentIpAddress,
        array $ipWhitelist
    ): bool {
        if ($ipWhitelist === []) {
            return true;
        }
        return array_any(
            array: $ipWhitelist,
            callback: fn(string $ipAddress) => IpValidator::isInWhitelist(
                whiteList: [$ipAddress],
                ipAddressToCheck: $currentIpAddress
            )
        );
    }
}