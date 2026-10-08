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
use actra\backend\i18n\MessageTemplate;
use actra\backend\libs\form\NotificationSendForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class notificationSend extends BackendView
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
        return HtmlText::fromText(text: $this->backendContext->messages->notification->sendTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $replacements = $htmlDocument->replacements;
        $notificationSendForm = new NotificationSendForm(context: $this->backendContext);
        $notificationID = $notificationSendForm->process();
        if ($notificationID !== null) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $this->backendContext->paths->notification(
                    ID: $notificationID,
                ) . '?' . notification::PARAM_SENT,
                httpRequest: $this->context->httpRequest,
            );
        }
        $replacements->addHtmlText(
            identifier: 'sendInfo',
            htmlText: HtmlText::fromText(
                text: MessageTemplate::fill(
                    template: $this->backendContext->messages->notification->sendInfo,
                    values: ['send' => $this->backendContext->messages->common->send],
                ),
            ),
        );
        $replacements->addHtml(
            identifier: 'form',
            html: $notificationSendForm->render(),
        );
    }
}
