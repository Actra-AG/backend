<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
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

final class NotificationSendForm extends Form
{
    private readonly SelectOptionsField $authUserGroupField;
    private readonly TextField $subjectField;
    private readonly TextAreaField $messageField;
    public private(set) int $notificationID;

    public function __construct(BackendViewContext $context)
    {
        $messages = ActraBackend::messages();
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'NotificationSendForm',
            messages: ActraBackend::messages()->form,
        );
        $this->addCssClass(className: 'form');
        $this->addField(
            formField: $this->authUserGroupField = new SelectOptionsField(
                name: 'authUserGroupField',
                label: HtmlText::fromText(text: $messages->common->userGroupLabel),
                formOptions: DbAuthGroupRepository::listAll()->getFormOptions(),
                initialValue: null,
                requiredError: HtmlText::fromText(text: $messages->notification->userGroupRequired),
            ),
        );
        $this->addField(
            formField: $this->subjectField = new TextField(
                name: 'subjectField',
                label: HtmlText::fromText(text: $messages->common->subjectLabel),
                value: '',
                requiredError: HtmlText::fromText(text: $messages->common->subjectRequired),
            ),
        );
        $this->addField(
            formField: $this->messageField = new TextAreaField(
                name: 'messageField',
                label: HtmlText::fromText(text: $messages->common->messageBodyLabel),
                value: implode(
                    separator: PHP_EOL,
                    array: [
                        $messages->notification->defaultGreeting,
                        '',
                        $messages->notification->defaultMessage,
                        '',
                        $messages->common->closingGreeting,
                        '',
                        ActraBackend::get()->mailerSettings->signature,
                    ],
                ),
                requiredError: HtmlText::fromText(text: $messages->common->messageBodyRequired),
            ),
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::fromText(text: $messages->common->send),
                cancelLink: notifications::getPath(),
            ),
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        $authGroupID = (int) $this->authUserGroupField->getValueAsString();
        $subject = $this->subjectField->getValueAsString();
        $message = $this->messageField->getValueAsString();
        $this->notificationID = DbAuthUserNotificationRepository::insert(
            authGroupID: $authGroupID,
            subject: $subject,
            message: $message,
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
                    subject: $message,
                ),
            );
            DbAuthUserNotificationRecipientRepository::insert(
                notificationID: $this->notificationID,
                authUserID: $dbAuthUser->ID,
                email: $dbAuthUser->email,
            );
            sleep(seconds: 1);
        }

        return true;
    }
}
