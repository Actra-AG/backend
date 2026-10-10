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
use actra\backend\libs\db\DbAuthUser;
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
use Throwable;

/**
 * @internal
 */
final class UserModForm extends Form
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

    public function __construct(
        BackendViewContext $context,
        private readonly DbAuthUser $dbAuthUser,
    ) {
        $this->backendContext = $context;
        $dbAuthUser = $this->dbAuthUser;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'UserModForm-' . $dbAuthUser->id,
            messages: $this->backendContext->messages->form,
        );
        $this->addCssClass(className: 'form');
        $common = $this->backendContext->messages->common;
        $userMessages = $this->backendContext->messages->user;
        $this->addField(
            formField: $this->firstNameField = new TextField(
                name: 'firstName',
                label: HtmlText::fromText(text: $common->firstNameLabel),
                value: $dbAuthUser->firstName,
                maxLength: DbAuthUser::MAX_TEXT_LENGTH,
                requiredError: HtmlText::fromText(text: $common->firstNameRequired),
            ),
        );
        $this->addField(
            formField: $this->lastNameField = new TextField(
                name: 'lastName',
                label: HtmlText::fromText(text: $common->lastNameLabel),
                value: $dbAuthUser->lastName,
                maxLength: DbAuthUser::MAX_TEXT_LENGTH,
                requiredError: HtmlText::fromText(text: $common->lastNameRequired),
            ),
        );
        $this->addField(
            formField: $this->emailField = new EmailField(
                name: 'email',
                label: HtmlText::fromText(text: $common->emailLabel),
                value: $dbAuthUser->email,
                invalidError: HtmlText::fromText(text: $common->emailInvalid),
                maxLength: DbAuthUser::MAX_TEXT_LENGTH,
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
                formOptions: $this->backendContext->repositories->groups()->listAll()->getFormOptions(),
                initialValues: $this->backendContext->repositories->groups()->listByUserId(
                    userId: $dbAuthUser->id,
                )->getFormOptions()->getKeys(),
                requiredError: HtmlText::fromText(text: $userMessages->userGroupsRequired),
            ),
        );
        $this->addField(
            formField: $this->ipWhitelistField = new IpWhitelistField(
                messages: $this->backendContext->messages->common,
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
                cancelLink: $this->backendContext->paths->user(id: $dbAuthUser->id),
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
                errorMessage: HtmlText::fromText(text: $this->backendContext->messages->common->noChanges),
            );

            return false;
        }
        $newIpWhitelist = $this->ipWhitelistField->getValues();
        if (
            $newIpWhitelist === []
            && $this->backendContext->repositories->apiKeys()->hasByUserId(userId: $this->dbAuthUser->id)
        ) {
            $this->addError(
                errorMessage: HtmlText::fromText(
                    text: $this->backendContext->messages->common->apiKeyBlocksEmptyIpWhitelist,
                ),
            );

            return false;
        }
        if (!$this->isAllowedChangeOfAccess()) {
            return false;
        }
        if (
            $this->emailField->valueHasChanged()
            && $this->backendContext->repositories->users()->selectByEmail(
                email: $this->emailField->getValueAsString(),
            ) !== null
        ) {
            $this->addError(
                errorMessage: HtmlText::fromText(text: $this->backendContext->messages->common->emailAlreadyInUse),
            );

            return false;
        }
        $repositories = $this->backendContext->repositories;
        $userId = $this->dbAuthUser->id;
        $db = $repositories->db();
        $db->beginTransaction();
        try {
            $repositories->users()->update(
                id: $userId,
                email: $this->emailField->getValueAsString(),
                phone: $this->phoneNumberField->getValueAsString(),
                active: $this->activeField->isChecked(),
                firstName: $this->firstNameField->getValueAsString(),
                lastName: $this->lastNameField->getValueAsString(),
                languageCode: $this->languageField === null
                    ? $this->dbAuthUser->languageCode
                    : $this->languageField->getLanguageCode(),
            );
            foreach ($this->userGroupsField->getAddedIntValues() as $groupId) {
                $repositories->userGroups()->insert(userId: $userId, groupId: $groupId);
            }
            foreach ($this->userGroupsField->getRemovedIntValues() as $groupId) {
                $repositories->userGroups()->delete(userId: $userId, groupId: $groupId);
            }
            foreach ($newIpWhitelist as $ip) {
                if (!in_array(
                    needle: $ip,
                    haystack: $this->dbAuthUser->ipWhitelist,
                    strict: true,
                )) {
                    $repositories->ipWhitelists()->insert(
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
                    $repositories->ipWhitelists()->delete(
                        userId: $userId,
                        ipAddress: $ip,
                    );
                }
            }
            $this->endAccessIfChanged();
            $db->commit();
        } catch (Throwable $throwable) {
            $db->rollBack();
            throw $throwable;
        }

        return true;
    }

    /**
     * Nobody deactivates himself, and at least one active user keeps the right to manage users.
     */
    private function isAllowedChangeOfAccess(): bool
    {
        $context = $this->backendContext;
        $userMessages = $context->messages->user;
        $dbAuthUser = $this->dbAuthUser;
        $isActive = $this->activeField->isChecked();
        if (!$isActive && $dbAuthUser->id === $context->getCurrentUser()->id) {
            $this->addError(errorMessage: HtmlText::fromText(text: $userMessages->selfDeactivateError));

            return false;
        }
        $wasUserManager = $dbAuthUser->isActive
            && $dbAuthUser->accessRightCollection->hasAccessRight(accessRight: ActraBackend::RIGHT_MANAGE_USERS);
        if (!$wasUserManager || ($isActive && $this->hasUserManagerGroup())) {
            return true;
        }
        if (
            $context->repositories->users()->countActiveWithRight(
                accessRight: ActraBackend::RIGHT_MANAGE_USERS,
                exceptUserId: $dbAuthUser->id,
            ) > 0
        ) {
            return true;
        }
        $this->addError(errorMessage: HtmlText::fromText(text: $userMessages->lastUserManagerError));

        return false;
    }

    private function hasUserManagerGroup(): bool
    {
        $groups = $this->backendContext->repositories->groups()->listAll();

        return array_any(
            array: $this->userGroupsField->getIntValues(),
            callback: static fn(int $groupId): bool => $groups->get(id: $groupId)
                ->accessRightCollection
                ->hasAccessRight(accessRight: ActraBackend::RIGHT_MANAGE_USERS),
        );
    }

    /**
     * A deactivated user loses his sessions, open tokens and API key; a changed email address ends the sessions and
     * open tokens (the current session of the editing user stays).
     */
    private function endAccessIfChanged(): void
    {
        $context = $this->backendContext;
        $repositories = $context->repositories;
        $userId = $this->dbAuthUser->id;
        $isDeactivated = $this->dbAuthUser->isActive && !$this->activeField->isChecked();
        if (!$isDeactivated && !$this->emailField->valueHasChanged()) {
            return;
        }
        if ($userId === $context->getCurrentUser()->id) {
            $repositories->sessions()->deleteOthersByUserId(
                userId: $userId,
                keepSessionId: $context->authSession->getAuthSessionId(),
            );
        } else {
            $repositories->sessions()->deleteByUserId(userId: $userId);
        }
        $repositories->tokens()->deleteUnclaimedByUserId(userId: $userId);
        if ($isDeactivated) {
            $repositories->apiKeys()->deleteByUserId(userId: $userId);
        }
    }
}
