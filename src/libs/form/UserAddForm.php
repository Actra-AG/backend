<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\common\UserLanguageOptions;
use actra\backend\libs\form\component\IpWhitelistField;
use actra\backend\libs\form\component\LanguageField;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\PhoneNumberField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class UserAddForm extends Form
{
    private readonly BackendViewContext $backendContext;
    private readonly TextField $firstNameField;
    private readonly TextField $lastNameField;
    private readonly EmailField $emailField;
    private readonly PhoneNumberField $phoneNumberField;
    private readonly CheckboxOptionsField $userGroupsField;
    private readonly BooleanField $activeField;
    private readonly IpWhitelistField $ipWhitelistField;
    private readonly ?LanguageField $languageField;

    public function __construct(BackendViewContext $context)
    {
        $this->backendContext = $context;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'UserAddForm',
            messages: $this->backendContext->messages->form,
        );
        $this->addCssClass(className: 'form');
        $common = $this->backendContext->messages->common;
        $userMessages = $this->backendContext->messages->user;
        $this->addField(
            formField: $this->firstNameField = new TextField(
                name: 'firstName',
                label: HtmlText::fromText(text: $common->firstNameLabel),
                requiredError: HtmlText::fromText(text: $common->firstNameRequired),
            ),
        );
        $this->addField(
            formField: $this->lastNameField = new TextField(
                name: 'lastName',
                label: HtmlText::fromText(text: $common->lastNameLabel),
                requiredError: HtmlText::fromText(text: $common->lastNameRequired),
            ),
        );
        $this->addField(
            formField: $this->emailField = new EmailField(
                name: 'email',
                label: HtmlText::fromText(text: $common->emailLabel),
                value: null,
                invalidError: HtmlText::fromText(text: $common->emailInvalid),
                requiredError: HtmlText::fromText(text: $common->emailRequired),
            ),
        );
        $this->addField(
            formField: $this->phoneNumberField = new PhoneNumberField(
                name: 'phone',
                label: HtmlText::fromText(text: $common->phoneLabel),
                value: null,
                invalidErrorMessage: HtmlText::fromText(text: $common->phoneInvalid),
            ),
        );
        $userLanguageOptions = UserLanguageOptions::forContext(context: $this->backendContext);
        $languageField = null;
        if ($userLanguageOptions->isSelectable()) {
            $languageField = new LanguageField(
                messages: $this->backendContext->messages->common,
                userLanguageOptions: $userLanguageOptions,
                initialValue: null,
            );
            $this->addField(formField: $languageField);
        }
        $this->languageField = $languageField;
        $this->addField(
            formField: $this->activeField = new BooleanField(
                name: 'active',
                label: HtmlText::fromText(text: $userMessages->activeAccessLabel),
                isCheckedByDefault: false,
            ),
        );
        $this->addField(
            formField: $this->userGroupsField = new CheckboxOptionsField(
                name: 'userGroups',
                label: HtmlText::fromText(text: $common->userGroupsLabel),
                formOptions: $this->backendContext->repositories->groups()->listAll()->getFormOptions(),
                initialValues: [],
                requiredError: HtmlText::fromText(text: $userMessages->userGroupsRequired),
            ),
        );
        $this->addField(
            formField: $this->ipWhitelistField = new IpWhitelistField(
                messages: $this->backendContext->messages->common,
                name: 'ipWhitelistField',
                label: HtmlText::fromText(text: $common->ipWhitelistLabel),
                value: [],
                invalidErrorMessage: HtmlText::fromText(text: $common->ipWhitelistInvalid),
            ),
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::fromText(text: $common->save),
                cancelLink: $this->backendContext->paths->users(),
            ),
        );
    }

    /**
     * @return ?int The ID of the new user, `null` if the form was not sent or is invalid
     */
    public function process(): ?int
    {
        if (!parent::validate()) {
            return null;
        }
        $inputEmail = $this->emailField->getValueAsString();
        if ($this->backendContext->repositories->users()->selectByEmail(email: $inputEmail) !== null) {
            $this->addError(
                errorMessage: HtmlText::fromText(text: $this->backendContext->messages->common->emailAlreadyInUse),
            );

            return null;
        }
        $newUserID = $this->backendContext->repositories->users()->insert(
            registeredById: $this->backendContext->getCurrentUser()->id,
            email: $this->emailField->getValueAsString(),
            phone: $this->phoneNumberField->getValueAsString(),
            active: $this->activeField->isChecked(),
            firstName: $this->firstNameField->getValueAsString(),
            lastName: $this->lastNameField->getValueAsString(),
            languageCode: $this->languageField?->getLanguageCode(),
        );
        foreach ($this->userGroupsField->getValues() as $userGroupValue) {
            $this->backendContext->repositories->userGroups()->insert(
                userID: $newUserID,
                groupID: (int) $userGroupValue,
            );
        }
        foreach ($this->ipWhitelistField->getValues() as $ip) {
            $this->backendContext->repositories->ipWhitelists()->insert(
                userID: $newUserID,
                ipAddress: $ip,
            );
        }

        return $newUserID;
    }
}
