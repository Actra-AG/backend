<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendPaths;
use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\backend\i18n\BackendMessages;
use actra\backend\libs\table\NotificationTable;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;
use actra\yuf\layout\NavigationItem;

/**
 * @internal
 */
final class notifications extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(
            context: $context,
            activeHtmlIdList: [
                'users',
                'notifications',
            ],
            useNavigator: true,
        );
    }

    public static function getNavigationItem(BackendPaths $paths, BackendMessages $messages): NavigationItem
    {
        return new NavigationItem(
            navKey: 'notifications',
            href: $paths->notifications() . '?reset',
            svgPath: '',
            title: $messages->notification->title,
            requiredAccessRights: notifications::getRequiredAccessRights(),
        );
    }

    #[\Override]
    public static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: $this->backendContext->messages->notification->title);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'sendTitle',
            htmlText: HtmlText::fromText(text: $this->backendContext->messages->notification->sendTitle),
        );
        $replacements->addHtml(
            identifier: 'sendHref',
            html: $this->backendContext->paths->notificationSend(),
        );
        $replacements->addHtml(
            identifier: 'table',
            html: new NotificationTable(context: $this->backendContext)->render(),
        );
    }
}
