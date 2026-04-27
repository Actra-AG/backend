<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form;

use actra\backend\ActraBackend;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\email\EmailAuthUser;
use actra\yuf\core\HttpRequest;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\html\HtmlText;

class UserInviteForm extends Form
{
    private readonly TextField $subjectField;
    private readonly TextAreaField $bodyField;

    public function __construct(private readonly DbAuthUser $dbAuthUser)
    {
        parent::__construct(name: 'UserInviteForm');
        $this->addCssClass(className: 'form');
        $this->addField(
            formField: $this->subjectField = new TextField(
                name: 'subjectField',
                label: HtmlText::encoded(textContent: 'Betreff'),
                value: 'Zugang zum passwortgeschützten Bereich',
                requiredError: HtmlText::encoded(textContent: 'Geben Sie bitte ein Betreff ein.')
            )
        );
        $this->addField(
            formField: $this->bodyField = new TextAreaField(
                name: 'bodyField',
                label: HtmlText::encoded(textContent: 'Textinhalt'),
                value: implode(
                    separator: PHP_EOL,
                    array: [
                        'Guten Tag ' . $this->dbAuthUser->firstName . ' ' . $this->dbAuthUser->lastName,
                        '',
                        'Wir haben Ihnen einen Zugang in unser Backend eingerichtet:',
                        HttpRequest::getProtocol() . '://' . HttpRequest::getHost() . ActraBackend::get()->path,
                        '',
                        'Geben Sie zur Anmeldung Ihre E-Mail-Adresse ' . $this->dbAuthUser->email . ' und beim nächsten Schritt den erhaltenen Bestätigungscode ein, um sich anzumelden.',
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
                name: 'submit',
                submitLabel: HtmlText::encoded(textContent: 'senden')
            )
        );
    }

    public function process(): bool
    {
        if (!parent::validate()) {
            return false;
        }
        $dbAuthUser = $this->dbAuthUser;
        EmailAuthUser::send(
            dbAuthUser: $dbAuthUser,
            subject: $this->subjectField->getRawValue(),
            message: $this->bodyField->getRawValue()
        );
        DbAuthUserRepository::sentInvitation(ID: $dbAuthUser->ID);

        return true;
    }
}