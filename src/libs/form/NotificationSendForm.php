<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\libs\db\DbAuthGroupRepository;
use actra\backend\libs\db\DbAuthUserNotificationRecipientRepository;
use actra\backend\libs\db\DbAuthUserNotificationRepository;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\email\EmailAuthUser;
use actra\backend\view\backend\php\notifications;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

class NotificationSendForm extends Form
{
    private readonly SelectOptionsField $authUserGroupField;
    private readonly TextField $subjectField;
    private readonly TextAreaField $messageField;
    public readonly int $notificationID;

    public function __construct()
    {
        parent::__construct(name: 'NotificationSendForm');
        $this->addCssClass(className: 'form');
        $this->addField(
            formField: $this->authUserGroupField = new SelectOptionsField(
                name: 'authUserGroupField',
                label: HtmlText::encoded(textContent: 'Benutzergruppe'),
                formOptions: DbAuthGroupRepository::listAll()->getFormOptions(),
                initialValue: null,
                requiredError: HtmlText::encoded(textContent: 'Bitte wählen Sie eine Benutzergruppe aus.')
            )
        );
        $this->addField(
            formField: $this->subjectField = new TextField(
                name: 'subjectField',
                label: HtmlText::encoded(textContent: 'Betreff'),
                value: '',
                requiredError: HtmlText::encoded(textContent: 'Geben Sie bitte ein Betreff ein.')
            )
        );
        $this->addField(
            formField: $this->messageField = new TextAreaField(
                name: 'messageField',
                label: HtmlText::encoded(textContent: 'Textinhalt'),
                value: implode(
                    separator: PHP_EOL,
                    array: [
                        'Guten Tag [firstName] [lastName]',
                        '',
                        'Nachricht...',
                        '',
                        'Freundliche Grüsse',
                        '',
                        ActraBackend::get()->mailerSettings->signature,
                    ]
                ),
                requiredError: HtmlText::encoded(textContent: 'Geben Sie bitte den gewünschten Text ein.')
            )
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::encoded(textContent: 'Senden'),
                cancelLink: notifications::getPath()
            )
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        $authGroupID = (int)$this->authUserGroupField->getRawValue();
        $subject = $this->subjectField->getRawValue();
        $message = $this->messageField->getRawValue();
        $this->notificationID = DbAuthUserNotificationRepository::insert(
            authGroupID: $authGroupID,
            subject: $subject,
            message: $message
        );
        foreach (DbAuthUserRepository::selectByUserGroup(groupID: $authGroupID)->items as $dbAuthUser) {
            EmailAuthUser::send(
                dbAuthUser: $dbAuthUser,
                subject: $subject,
                message: str_replace(
                    search: [
                        '[firstName]',
                        '[lastName]',
                    ],
                    replace: [
                        $dbAuthUser->firstName,
                        $dbAuthUser->lastName,
                    ],
                    subject: $message
                ),
            );
            DbAuthUserNotificationRecipientRepository::insert(
                notificationID: $this->notificationID,
                authUserID: $dbAuthUser->ID,
                email: $dbAuthUser->email
            );
            sleep(seconds: 1);
        }

        return true;
    }
}