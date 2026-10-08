<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\i18n\MessageTemplate;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\email\EmailAuthUser;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class UserInviteForm extends Form
{
    private readonly BackendViewContext $backendContext;
    private readonly TextField $subjectField;
    private readonly TextAreaField $bodyField;

    public function __construct(
        BackendViewContext $context,
        private readonly DbAuthUser $dbAuthUser,
    ) {
        $this->backendContext = $context;
        parent::__construct(
            context: $context->viewContext->formContext,
            name: 'UserInviteForm',
            messages: $this->backendContext->messages->form,
        );
        $this->addCssClass(className: 'form');
        $common = $this->backendContext->messages->common;
        $messages = $this->backendContext->messages->user;
        $recipientRoute = $this->backendContext->actraBackend->getRouteForLanguage(
            languageCode: $dbAuthUser->languageCode,
        );
        $recipientMessages = $recipientRoute->messages;
        $this->addField(
            formField: $this->subjectField = new TextField(
                name: 'subjectField',
                label: HtmlText::fromText(text: $common->subjectLabel),
                value: $recipientMessages->user->inviteDefaultSubject,
                requiredError: HtmlText::fromText(text: $common->subjectRequired),
            ),
        );
        $this->addField(
            formField: $this->bodyField = new TextAreaField(
                name: 'bodyField',
                label: HtmlText::fromText(text: $common->messageBodyLabel),
                value: implode(
                    separator: PHP_EOL,
                    array: [
                        MessageTemplate::fill(
                            template: $recipientMessages->user->inviteGreeting,
                            values: [
                                'firstName' => $dbAuthUser->firstName,
                                'lastName' => $dbAuthUser->lastName,
                            ],
                        ),
                        '',
                        $recipientMessages->user->inviteAccessCreated,
                        $this->context->httpRequest->getProtocol()->value . '://'
                        . $this->context->httpRequest->getHost() . $recipientRoute->path,
                        '',
                        MessageTemplate::fill(
                            template: $recipientMessages->user->inviteLoginInstructions,
                            values: ['email' => $dbAuthUser->email],
                        ),
                        '',
                        $recipientMessages->common->closingGreeting,
                        '',
                        $this->backendContext->actraBackend->mailerSettings->signature,
                    ],
                ),
                requiredError: HtmlText::fromText(text: $common->messageBodyRequired),
            ),
        );
        $this->addComponent(
            formComponent: new FormControl(
                name: 'submit',
                submitLabel: HtmlText::fromText(text: $messages->inviteSubmitButton),
            ),
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        $dbAuthUser = $this->dbAuthUser;
        EmailAuthUser::send(
            mailer: $this->backendContext->mailer,
            dbAuthUser: $dbAuthUser,
            subject: $this->subjectField->getValueAsString(),
            message: $this->bodyField->getValueAsString(),
        );
        $this->backendContext->repositories->users()->sentInvitation(ID: $dbAuthUser->ID);

        return true;
    }
}
