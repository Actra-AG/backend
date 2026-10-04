<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\i18n\MessageTemplate;
use actra\backend\libs\db\DbAuthApiKeyRepository;
use actra\backend\libs\db\DbAuthIpWhitelistRepository;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\common\UserLanguageOptions;
use actra\backend\libs\form\component\IpWhitelistField;
use actra\backend\libs\form\component\LanguageField;
use actra\yuf\core\HttpRequest;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\CsrfTokenField;
use actra\yuf\form\component\field\PhoneNumberField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\component\FormField;
use actra\yuf\html\HtmlText;

final class ProfileForm extends Form
{
    private readonly TextField $firstNameField;
    private readonly TextField $lastNameField;
    private readonly PhoneNumberField $phoneNumberField;
    private readonly IpWhitelistField $ipWhitelistField;
    private readonly ?LanguageField $languageField;

    public function __construct(private readonly DbAuthUser $dbAuthUser)
    {
        $messages = ActraBackend::messages();
        parent::__construct(name: 'ProfileForm', messages: $messages->form);
        $this->addCssClass(className: 'form');
        $this->addField(
            formField: $this->firstNameField = new TextField(
                name: 'firstName',
                label: HtmlText::unencoded(textContent: $messages->common->firstNameLabel),
                value: $dbAuthUser->firstName,
                requiredError: HtmlText::unencoded(textContent: $messages->common->firstNameRequired)
            )
        );
        $this->addField(
            formField: $this->lastNameField = new TextField(
                name: 'lastName',
                label: HtmlText::unencoded(textContent: $messages->common->lastNameLabel),
                value: $dbAuthUser->lastName,
                requiredError: HtmlText::unencoded(textContent: $messages->common->lastNameRequired)
            )
        );
        $this->addField(
            formField: $this->phoneNumberField = new PhoneNumberField(
                name: 'phone',
                label: HtmlText::unencoded(textContent: $messages->common->phoneLabel),
                value: $dbAuthUser->phone,
                invalidErrorMessage: HtmlText::unencoded(textContent: $messages->common->phoneInvalid)
            )
        );
        $userLanguageOptions = UserLanguageOptions::forCurrentRoute();
        $languageField = null;
        if ($userLanguageOptions->isSelectable()) {
            $languageField = new LanguageField(
                userLanguageOptions: $userLanguageOptions,
                initialValue: $dbAuthUser->languageCode
            );
            $this->addField(formField: $languageField);
        }
        $this->languageField = $languageField;
        $this->addField(
            formField: $this->ipWhitelistField = new IpWhitelistField(
                name: 'ipWhitelistField',
                label: HtmlText::unencoded(textContent: $messages->common->ipWhitelistLabel),
                value: $dbAuthUser->ipWhitelist,
                invalidErrorMessage: HtmlText::unencoded(textContent: $messages->common->ipWhitelistInvalid)
            )
        );
        $this->ipWhitelistField->fieldInfo = HtmlText::unencoded(
            textContent: $messages->profile->ipWhitelistInfo
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::unencoded(textContent: $messages->common->save)
            )
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        $messages = ActraBackend::messages();
        if (!$this->hasChanges()) {
            $this->addError(
                errorMessage: HtmlText::unencoded(textContent: $messages->common->noChanges)
            );

            return false;
        }
        $currentIpAddress = HttpRequest::getRemoteAddress();
        $newIpWhitelist = $this->ipWhitelistField->getValues();
        if (
            $newIpWhitelist === []
            && DbAuthApiKeyRepository::hasByUserID(userID: $this->dbAuthUser->ID)
        ) {
            $this->addError(
                errorMessage: HtmlText::unencoded(
                    textContent: $messages->common->apiKeyBlocksEmptyIpWhitelist
                )
            );

            return false;
        }
        if (!$this->currentIpIsAllowed(
            currentIpAddress: $currentIpAddress,
            ipWhitelist: $newIpWhitelist
        )) {
            $this->addError(
                errorMessage: HtmlText::unencoded(
                    textContent: MessageTemplate::fill(
                        template: $messages->profile->currentIpMustBeAllowed,
                        values: ['ipAddress' => $currentIpAddress]
                    )
                )
            );

            return false;
        }
        $userID = $this->dbAuthUser->ID;
        DbAuthUserRepository::update(
            ID: $userID,
            email: $this->dbAuthUser->email,
            phone: $this->phoneNumberField->getValueAsString(),
            active: $this->dbAuthUser->isActive,
            firstName: $this->firstNameField->getValueAsString(),
            lastName: $this->lastNameField->getValueAsString(),
            languageCode: $this->languageField === null
                ? $this->dbAuthUser->languageCode
                : $this->languageField->getLanguageCode()
        );
        foreach ($newIpWhitelist as $ip) {
            if (!in_array(
                needle: $ip,
                haystack: $this->dbAuthUser->ipWhitelist,
                strict: true
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
                haystack: $newIpWhitelist,
                strict: true
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
            callback: function (FormField $field): bool {
                if ($field instanceof CsrfTokenField) {
                    return false;
                }
                return $field->valueHasChanged();
            }
        );
    }

    /**
     * @param list<string> $ipWhitelist
     */
    private function currentIpIsAllowed(
        string $currentIpAddress,
        array $ipWhitelist
    ): bool {
        if ($ipWhitelist === []) {
            return true;
        }
        return array_any(
            array: $ipWhitelist,
            callback: fn(string $ipAddress): bool => IpValidator::isInWhitelist(
                whiteList: [$ipAddress],
                ipAddressToCheck: $currentIpAddress
            )
        );
    }
}