<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\db\DbAuthUserNotificationRepository;
use actra\backend\libs\table\NotificationRecipientTable;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class notification extends BackendView
{
    public const string PARAM_SENT = 'sent';

    private readonly HtmlText $pageTitle;

    public function __construct()
    {
        $inputParameterCollection = new InputParameterCollection();
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: notification::PARAM_SENT,
                isRequired: false
            )
        );
        parent::__construct(
            inputParameterCollection: $inputParameterCollection,
            maxAllowedPathVars: 1,
            activeHtmlIdList: [
                'users',
                'notifications',
            ],
            useNavigator: true
        );
    }

    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    protected function getPageTitle(): HtmlText
    {
        return $this->pageTitle;
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUserNotification = DbAuthUserNotificationRepository::selectByID(ID: (int)$this->getPathVar(nr: 1));
        if ($dbAuthUserNotification === null) {
            throw new NotFoundException();
        }
        $this->pageTitle = HtmlText::unencoded(
            textContent: $dbAuthUserNotification->subject
        );
        $replacements = $htmlDocument->replacements;
        $replacements->addBool(
            identifier: 'sent',
            booleanValue: $this->getInputString(keyName: notification::PARAM_SENT) !== null
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'detailFields',
            htmlDataObjectCollection: $dbAuthUserNotification->render()
        );
        $replacements->addEncodedText(
            identifier: 'recipients',
            content: new NotificationRecipientTable(
                notificationID: $dbAuthUserNotification->ID
            )->render()
        );
    }

    public static function getPath(int $ID): string
    {
        return ActraBackend::get()->path . 'notification-' . $ID . '.html';
    }
}