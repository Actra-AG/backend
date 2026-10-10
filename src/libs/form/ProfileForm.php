<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\i18n\MessageTemplate;
use actra\backend\libs\common\UserLanguageOptions;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\form\component\IpWhitelistField;
use actra\backend\libs\form\component\LanguageField;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\PhoneNumberField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class ProfileForm extends Form
{
    private readonly BackendViewContext $backendContext;
    private readonly TextField $firstNameField;
    private readonly TextField $lastNameField;
    private readonly PhoneNumberField $phoneNumberField;
    private readonly IpWhitelistField $ipWhitelistField;
    private readonly ?LanguageField $languageField;

    public function __construct(
        BackendViewContext $context,
        private readonly DbAuthUser $dbAuthUser,
    ) {
        $this->backendContext = $context;
        $messages = $this->backendContext->messages;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'ProfileForm',
            messages: $messages->form,
        );
        $this->addCssClass(className: 'form');
        $this->addField(
            formField: $this->firstNameField = new TextField(
                name: 'firstName',
                label: HtmlText::fromText(text: $messages->common->firstNameLabel),
                value: $dbAuthUser->firstName,
                maxLength: DbAuthUser::MAX_TEXT_LENGTH,
                requiredError: HtmlText::fromText(text: $messages->common->firstNameRequired),
            ),
        );
        $this->addField(
            formField: $this->lastNameField = new TextField(
                name: 'lastName',
                label: HtmlText::fromText(text: $messages->common->lastNameLabel),
                value: $dbAuthUser->lastName,
                maxLength: DbAuthUser::MAX_TEXT_LENGTH,
                requiredError: HtmlText::fromText(text: $messages->common->lastNameRequired),
            ),
        );
        $this->addField(
            formField: $this->phoneNumberField = new PhoneNumberField(
                name: 'phone',
                label: HtmlText::fromText(text: $messages->common->phoneLabel),
                value: $dbAuthUser->phone,
                invalidErrorMessage: HtmlText::fromText(text: $messages->common->phoneInvalid),
            ),
        );
        $userLanguageOptions = UserLanguageOptions::forContext(context: $this->backendContext);
        $languageField = null;
        if ($userLanguageOptions->isSelectable()) {
            $languageField = new LanguageField(
                messages: $this->backendContext->messages->common,
                userLanguageOptions: $userLanguageOptions,
                initialValue: $dbAuthUser->languageCode,
            );
            $this->addField(formField: $languageField);
        }
        $this->languageField = $languageField;
        $this->addField(
            formField: $this->ipWhitelistField = new IpWhitelistField(
                messages: $this->backendContext->messages->common,
                name: 'ipWhitelistField',
                label: HtmlText::fromText(text: $messages->common->ipWhitelistLabel),
                value: $dbAuthUser->ipWhitelist,
                invalidErrorMessage: HtmlText::fromText(text: $messages->common->ipWhitelistInvalid),
            ),
        );
        $this->ipWhitelistField->fieldInfo = HtmlText::fromText(
            text: $messages->profile->ipWhitelistInfo,
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::fromText(text: $messages->common->save),
            ),
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        $messages = $this->backendContext->messages;
        if (!$this->hasChanges()) {
            $this->addError(
                errorMessage: HtmlText::fromText(text: $messages->common->noChanges),
            );

            return false;
        }
        $currentIpAddress = $this->context->httpRequest->getRemoteAddress();
        $newIpWhitelist = $this->ipWhitelistField->getValues();
        if (
            $newIpWhitelist === []
            && $this->backendContext->repositories->apiKeys()->hasByUserId(userId: $this->dbAuthUser->id)
        ) {
            $this->addError(
                errorMessage: HtmlText::fromText(
                    text: $messages->common->apiKeyBlocksEmptyIpWhitelist,
                ),
            );

            return false;
        }
        if (!$this->currentIpIsAllowed(
            currentIpAddress: $currentIpAddress,
            ipWhitelist: $newIpWhitelist,
        )) {
            $this->addError(
                errorMessage: HtmlText::fromText(
                    text: MessageTemplate::fill(
                        template: $messages->profile->currentIpMustBeAllowed,
                        values: ['ipAddress' => $currentIpAddress],
                    ),
                ),
            );

            return false;
        }
        $userId = $this->dbAuthUser->id;
        $this->backendContext->repositories->users()->update(
            id: $userId,
            email: $this->dbAuthUser->email,
            phone: $this->phoneNumberField->getValueAsString(),
            active: $this->dbAuthUser->isActive,
            firstName: $this->firstNameField->getValueAsString(),
            lastName: $this->lastNameField->getValueAsString(),
            languageCode: $this->languageField === null
                ? $this->dbAuthUser->languageCode
                : $this->languageField->getLanguageCode(),
        );
        foreach ($newIpWhitelist as $ip) {
            if (!in_array(
                needle: $ip,
                haystack: $this->dbAuthUser->ipWhitelist,
                strict: true,
            )) {
                $this->backendContext->repositories->ipWhitelists()->insert(
                    userId: $userId,
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
                $this->backendContext->repositories->ipWhitelists()->delete(
                    userId: $userId,
                    ipAddress: $ip,
                );
            }
        }

        return true;
    }


    /**
     * @param list<string> $ipWhitelist
     */
    private function currentIpIsAllowed(
        string $currentIpAddress,
        array $ipWhitelist,
    ): bool {
        if ($ipWhitelist === []) {
            return true;
        }
        return array_any(
            array: $ipWhitelist,
            callback: fn(string $ipAddress): bool => IpValidator::isInWhitelist(
                whiteList: [$ipAddress],
                ipAddressToCheck: $currentIpAddress,
            ),
        );
    }
}
