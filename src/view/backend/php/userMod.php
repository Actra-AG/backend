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
use actra\backend\libs\form\UserModForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class userMod extends BackendView
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
        return HtmlText::fromText(text: $this->backendContext->messages->user->editUserTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $pathUserId = $this->getRequiredPathVarAsInt(nr: 1);
        $dbAuthUser = $this->backendContext->repositories->users()->selectById(id: $pathUserId);
        if ($dbAuthUser === null) {
            throw new NotFoundException();
        }
        $replacements = $htmlDocument->replacements;
        $userModForm = new UserModForm(context: $this->backendContext, dbAuthUser: $dbAuthUser);
        if ($userModForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $this->backendContext->paths->user(
                    id: $dbAuthUser->id,
                ) . '?' . user::PARAM_CHANGED,
                httpRequest: $this->context->httpRequest,
            );
        }
        $replacements->addHtml(
            identifier: 'form',
            html: $userModForm->render(),
        );
    }
}
