<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\backend\libs\table\NotificationRecipientTable;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class notification extends BackendView
{
    public const string PARAM_SENT = 'sent';

    private ?HtmlText $pageTitle = null;

    public function __construct(BackendViewContext $context)
    {
        $inputParameterCollection = new InputParameterCollection();
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: notification::PARAM_SENT,
                source: InputSourceEnum::QUERY,
                isRequired: false,
            ),
        );
        parent::__construct(
            context: $context,
            inputParameterCollection: $inputParameterCollection,
            maxAllowedPathVars: 1,
            activeHtmlIdList: [
                'users',
                'notifications',
            ],
            useNavigator: true,
        );
    }

    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return $this->pageTitle ?? HtmlText::fromText(text: '');
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUserNotification = $this->backendContext->repositories->notifications()->selectByID(
            ID: $this->getRequiredPathVarAsInt(nr: 1),
        );
        if ($dbAuthUserNotification === null) {
            throw new NotFoundException();
        }
        $this->pageTitle = HtmlText::fromText(
            text: $dbAuthUserNotification->subject,
        );
        $replacements = $htmlDocument->replacements;
        $messages = $this->backendContext->messages->notification;
        $replacements->addHtmlText(
            identifier: 'successLabel',
            htmlText: HtmlText::fromText(text: $this->backendContext->messages->common->successLabel),
        );
        $replacements->addHtmlText(
            identifier: 'sentSuccess',
            htmlText: HtmlText::fromText(text: $messages->sentSuccess),
        );
        $replacements->addHtmlText(
            identifier: 'detailsHeading',
            htmlText: HtmlText::fromText(text: $messages->detailsHeading),
        );
        $replacements->addHtmlText(
            identifier: 'recipientsLabel',
            htmlText: HtmlText::fromText(text: $messages->recipientsLabel),
        );
        $replacements->addBool(
            identifier: 'sent',
            booleanValue: $this->getInputString(keyName: notification::PARAM_SENT) !== null,
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'detailFields',
            htmlDataObjectCollection: $dbAuthUserNotification->render(messages: $this->backendContext->messages),
        );
        $replacements->addHtml(
            identifier: 'recipients',
            html: new NotificationRecipientTable(
                context: $this->backendContext,
                notificationID: $dbAuthUserNotification->ID,
            )->render(),
        );
    }
}
