<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\email\EmailAuthUser;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class NotificationSendForm extends Form
{
    private readonly BackendViewContext $backendContext;
    private readonly SelectOptionsField $authUserGroupField;
    private readonly TextField $subjectField;
    private readonly TextAreaField $messageField;

    public function __construct(BackendViewContext $context)
    {
        $this->backendContext = $context;
        $messages = $this->backendContext->messages;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'NotificationSendForm',
            messages: $this->backendContext->messages->form,
        );
        $this->addCssClass(className: 'form');
        $this->addField(
            formField: $this->authUserGroupField = new SelectOptionsField(
                name: 'authUserGroupField',
                label: HtmlText::fromText(text: $messages->common->userGroupLabel),
                formOptions: $this->backendContext->repositories->groups()->listAll()->getFormOptions(),
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
                        $this->backendContext->actraBackend->mailerSettings->signature,
                    ],
                ),
                requiredError: HtmlText::fromText(text: $messages->common->messageBodyRequired),
            ),
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'save',
                submitLabel: HtmlText::fromText(text: $messages->common->send),
                cancelLink: $this->backendContext->paths->notifications(),
            ),
        );
    }

    /**
     * @return ?int The ID of the sent notification, `null` if the form was not sent or is invalid
     */
    public function process(): ?int
    {
        if (!parent::validate()) {
            return null;
        }
        $authGroupID = (int) $this->authUserGroupField->getValueAsString();
        $subject = $this->subjectField->getValueAsString();
        $message = $this->messageField->getValueAsString();
        $notificationID = $this->backendContext->repositories->notifications()->insert(
            authGroupID: $authGroupID,
            sentByUserID: $this->backendContext->getCurrentUser()->id,
            subject: $subject,
            message: $message,
        );
        $recipients = $this->backendContext->repositories->users()->selectByUserGroup(groupID: $authGroupID);
        foreach ($recipients->items as $dbAuthUser) {
            EmailAuthUser::send(
                mailer: $this->backendContext->mailer,
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
            $this->backendContext->repositories->notificationRecipients()->insert(
                notificationID: $notificationID,
                authUserID: $dbAuthUser->ID,
                email: $dbAuthUser->email,
            );
            sleep(seconds: 1);
        }

        return $notificationID;
    }
}
