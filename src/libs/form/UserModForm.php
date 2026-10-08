<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
use actra\backend\libs\common\UserLanguageOptions;
use actra\backend\libs\db\DbAuthApiKeyRepository;
use actra\backend\libs\db\DbAuthGroupRepository;
use actra\backend\libs\db\DbAuthIpWhitelistRepository;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\db\DbAuthUserGroupRepository;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\form\component\IpWhitelistField;
use actra\backend\libs\form\component\LanguageField;
use actra\backend\view\backend\php\user;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\component\field\CsrfTokenField;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\PhoneNumberField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\component\FormField;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class UserModForm extends Form
{
    private readonly TextField $firstNameField;
    private readonly TextField $lastNameField;
    private readonly EmailField $emailField;
    private readonly PhoneNumberField $phoneNumberField;
    private readonly CheckboxOptionsField $userGroupsField;
    private readonly BooleanField $activeField;
    private readonly IpWhitelistField $ipWhitelistField;
    private readonly ?LanguageField $languageField;

    public function __construct(
        BackendViewContext $context,
        private readonly DbAuthUser $dbAuthUser,
    ) {
        $dbAuthUser = $this->dbAuthUser;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'UserModForm-' . $dbAuthUser->ID,
            messages: ActraBackend::messages()->form,
        );
        $this->addCssClass(className: 'form');
        $common = ActraBackend::messages()->common;
        $userMessages = ActraBackend::messages()->user;
        $this->addField(
            formField: $this->firstNameField = new TextField(
                name: 'firstName',
                label: HtmlText::fromText(text: $common->firstNameLabel),
                value: $dbAuthUser->firstName,
                requiredError: HtmlText::fromText(text: $common->firstNameRequired),
            ),
        );
        $this->addField(
            formField: $this->lastNameField = new TextField(
                name: 'lastName',
                label: HtmlText::fromText(text: $common->lastNameLabel),
                value: $dbAuthUser->lastName,
                requiredError: HtmlText::fromText(text: $common->lastNameRequired),
            ),
        );
        $this->addField(
            formField: $this->emailField = new EmailField(
                name: 'email',
                label: HtmlText::fromText(text: $common->emailLabel),
                value: $dbAuthUser->email,
                invalidError: HtmlText::fromText(text: $common->emailInvalid),
                requiredError: HtmlText::fromText(text: $common->emailRequired),
            ),
        );
        $this->addField(
            formField: $this->phoneNumberField = new PhoneNumberField(
                name: 'phone',
                label: HtmlText::fromText(text: $common->phoneLabel),
                value: $dbAuthUser->phone,
                invalidErrorMessage: HtmlText::fromText(text: $common->phoneInvalid),
            ),
        );
        $userLanguageOptions = UserLanguageOptions::forCurrentRoute();
        $languageField = null;
        if ($userLanguageOptions->isSelectable()) {
            $languageField = new LanguageField(
                userLanguageOptions: $userLanguageOptions,
                initialValue: $dbAuthUser->languageCode,
            );
            $this->addField(formField: $languageField);
        }
        $this->languageField = $languageField;
        $this->addField(
            formField: $this->activeField = new BooleanField(
                name: 'active',
                label: HtmlText::fromText(text: $userMessages->activeAccessLabel),
                isCheckedByDefault: $dbAuthUser->isActive,
            ),
        );
        $this->addField(
            formField: $this->userGroupsField = new CheckboxOptionsField(
                name: 'userGroups',
                label: HtmlText::fromText(text: $common->userGroupsLabel),
                formOptions: DbAuthGroupRepository::listAll()->getFormOptions(),
                initialValues: DbAuthGroupRepository::listByUserID(
                    userID: $dbAuthUser->ID,
                )->listFormOptionKeys(),
                requiredError: HtmlText::fromText(text: $userMessages->userGroupsRequired),
            ),
        );
        $this->addField(
            formField: $this->ipWhitelistField = new IpWhitelistField(
                name: 'ipWhitelistField',
                label: HtmlText::fromText(text: $common->ipWhitelistLabel),
                value: $dbAuthUser->ipWhitelist,
                invalidErrorMessage: HtmlText::fromText(text: $common->ipWhitelistInvalid),
            ),
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::fromText(text: $common->save),
                cancelLink: user::getPath(ID: $dbAuthUser->ID),
            ),
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        if (!$this->hasChanges()) {
            $this->addError(
                errorMessage: HtmlText::fromText(text: ActraBackend::messages()->common->noChanges),
            );

            return false;
        }
        $newIpWhitelist = $this->ipWhitelistField->getValues();
        if (
            $newIpWhitelist === []
            && DbAuthApiKeyRepository::hasByUserID(userID: $this->dbAuthUser->ID)
        ) {
            $this->addError(
                errorMessage: HtmlText::fromText(
                    text: ActraBackend::messages()->common->apiKeyBlocksEmptyIpWhitelist,
                ),
            );

            return false;
        }
        if (
            $this->emailField->valueHasChanged()
            && DbAuthUserRepository::selectByEmail(email: $this->emailField->getValueAsString()) !== null
        ) {
            $this->addError(
                errorMessage: HtmlText::fromText(text: ActraBackend::messages()->common->emailAlreadyInUse),
            );

            return false;
        }
        $userID = $this->dbAuthUser->ID;
        DbAuthUserRepository::update(
            ID: $userID,
            email: $this->emailField->getValueAsString(),
            phone: $this->phoneNumberField->getValueAsString(),
            active: $this->activeField->isChecked(),
            firstName: $this->firstNameField->getValueAsString(),
            lastName: $this->lastNameField->getValueAsString(),
            languageCode: $this->languageField === null
                ? $this->dbAuthUser->languageCode
                : $this->languageField->getLanguageCode(),
        );
        foreach ($this->userGroupsField->getAddedValues() as $userGroupValue) {
            DbAuthUserGroupRepository::insert(
                userID: $userID,
                groupID: (int) $userGroupValue,
            );
        }
        foreach ($this->userGroupsField->getRemovedValues() as $userGroupValue) {
            DbAuthUserGroupRepository::delete(
                userID: $userID,
                groupID: (int) $userGroupValue,
            );
        }
        foreach ($newIpWhitelist as $ip) {
            if (!in_array(
                needle: $ip,
                haystack: $this->dbAuthUser->ipWhitelist,
                strict: true,
            )) {
                DbAuthIpWhitelistRepository::insert(
                    userID: $userID,
                    ipAddress: $ip,
                );
            }
        }
        foreach ($this->dbAuthUser->ipWhitelist as $ip) {
            if (!in_array(
                needle: $ip,
                haystack: $newIpWhitelist,
                strict: true,
            )) {
                DbAuthIpWhitelistRepository::delete(
                    userID: $userID,
                    ipAddress: $ip,
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
            },
        );
    }
}
