<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\libs\db\DbAuthGroupRepository;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\db\DbAuthUserGroupRepository;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\view\backend\php\user;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

class UserModForm extends Form
{
    private readonly TextField $firstNameField;
    private readonly TextField $lastNameField;
    private readonly EmailField $emailField;
    private readonly CheckboxOptionsField $userGroupsField;
    private readonly BooleanField $activeField;

    public function __construct(private readonly DbAuthUser $dbAuthUser)
    {
        parent::__construct(name: 'UserModForm-' . $this->dbAuthUser->ID);
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
            formField: $this->emailField = new EmailField(
                name: 'email',
                label: HtmlText::encoded(textContent: 'E-Mail'),
                value: $dbAuthUser->email,
                invalidError: HtmlText::encoded(textContent: 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'),
                requiredError: HtmlText::encoded(textContent: 'Bitte geben Sie die E-Mail-Adresse ein.')
            )
        );
        $this->addField(
            formField: $this->activeField = new BooleanField(
                name: 'active',
                label: HtmlText::encoded(textContent: 'aktiver Zugang'),
                isCheckedByDefault: $dbAuthUser->isActive
            )
        );
        $this->addField(
            formField: $this->userGroupsField = new CheckboxOptionsField(
                name: 'userGroups',
                label: HtmlText::encoded(textContent: 'Benutzergruppen'),
                formOptions: DbAuthGroupRepository::listAll()->getFormOptions(),
                initialValues: DbAuthGroupRepository::listByUserID(userID: $this->dbAuthUser->ID)->listIDs(),
                requiredError: HtmlText::encoded(textContent: 'Bitte wählen Sie mindestens eine Benutzergruppe aus.')
            )
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::encoded(textContent: 'Speichern'),
                cancelLink: user::getPath(ID: $this->dbAuthUser->ID)
            )
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        if (
            $this->emailField->valueHasChanged()
            && !is_null(value: DbAuthUserRepository::selectByEmail(email: $this->emailField->getRawValue()))
        ) {
            $this->addError(
                errorMessage: 'Die eingegebene E-Mail-Adresse wird bereits verwendet.',
                isEncodedForRendering: true
            );

            return false;
        }
        $userID = $this->dbAuthUser->ID;
        DbAuthUserRepository::update(
            ID: $userID,
            email: $this->emailField->getRawValue(),
            active: $this->activeField->isChecked(),
            firstName: $this->firstNameField->getRawValue(),
            lastName: $this->lastNameField->getRawValue()
        );
        foreach ($this->userGroupsField->getAddedValues() as $userGroupValue) {
            DbAuthUserGroupRepository::insert(
                userID: $userID,
                groupID: (int)$userGroupValue
            );
        }
        foreach ($this->userGroupsField->getRemovedValues() as $userGroupValue) {
            DbAuthUserGroupRepository::delete(
                userID: $userID,
                groupID: (int)$userGroupValue
            );
        }

        return true;
    }
}