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
use actra\backend\libs\form\UserDeleteForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * Server-side confirmation page for the deletion of a user (without JavaScript the link opens it; with JavaScript
 * the dialog fetches it and submits its form).
 *
 * @internal
 */
final class userDelete extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(
            context: $context,
            maxAllowedPathVars: 1,
            activeHtmlIdList: [
                'users',
                'userList',
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
        return HtmlText::fromText(text: $this->backendContext->messages->user->deleteButton);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $pathUserID = $this->getRequiredPathVarAsInt(nr: 1);
        $dbAuthUser = $this->backendContext->repositories->users()->selectByID(ID: $pathUserID);
        if ($dbAuthUser === null) {
            throw new NotFoundException();
        }
        $userDeleteForm = new UserDeleteForm(context: $this->backendContext, dbAuthUser: $dbAuthUser);
        if ($userDeleteForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $this->backendContext->paths->users() . '?' . users::PARAM_REMOVED,
                httpRequest: $this->context->httpRequest,
            );
        }
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'deleteConfirm',
            htmlText: HtmlText::fromText(text: MessageTemplate::fill(
                template: $this->backendContext->messages->user->deleteConfirm,
                values: ['name' => $dbAuthUser->renderFullName(messages: $this->backendContext->messages->common)],
            )),
        );
        $replacements->addHtml(
            identifier: 'form',
            html: $userDeleteForm->render(),
        );
    }
}
