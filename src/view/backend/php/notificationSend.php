<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\form\NotificationSendForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class notificationSend extends BackendView
{
    public function __construct()
    {
        parent::__construct(
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
        return HtmlText::encoded(textContent: 'Benachrichtigung senden');
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $replacements = $htmlDocument->replacements;
        $notificationSendForm = new NotificationSendForm();
        if ($notificationSendForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: notification::getPath(
                    ID: $notificationSendForm->notificationID
                ) . '?' . notification::PARAM_SENT
            );
        }
        $replacements->addEncodedText(
            identifier: 'form',
            content: $notificationSendForm->render()
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::get()->path . 'notificationSend.html';
    }
}