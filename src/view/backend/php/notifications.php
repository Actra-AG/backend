<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\table\NotificationTable;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;
use actra\yuf\layout\NavigationItem;

class notifications extends BackendView
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

    public static function getNavigationItem(): NavigationItem
    {
        return new NavigationItem(
            navKey: 'notifications',
            href: notifications::getPath() . '?reset',
            svgPath: '',
            title: 'Benachrichtigungen',
            requiredAccessRights: notifications::getRequiredAccessRights()
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::get()->path . 'notifications.html';
    }

    public static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::encoded(textContent: 'Benachrichtigungen');
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $replacements = $htmlDocument->replacements;
        $replacements->addEncodedText(
            identifier: 'sendHref',
            content: notificationSend::getPath()
        );
        $replacements->addEncodedText(
            identifier: 'table',
            content: new NotificationTable()->render()
        );
    }
}