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
use actra\backend\libs\form\UserInviteForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class userInvite extends BackendView
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
        return HtmlText::fromText(text: $this->backendContext->messages->user->inviteTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $pathUserID = $this->getRequiredPathVarAsInt(nr: 1);
        $dbAuthUser = $this->backendContext->repositories->users()->selectByID(ID: $pathUserID);
        if ($dbAuthUser === null) {
            throw new NotFoundException();
        }
        $userInviteForm = new UserInviteForm(context: $this->backendContext, dbAuthUser: $dbAuthUser);
        if ($userInviteForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $this->backendContext->paths->user(
                    ID: $dbAuthUser->ID,
                ) . '?' . user::PARAM_INVITED,
                httpRequest: $this->context->httpRequest,
            );
        }
        $messages = $this->backendContext->messages->user;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'inviteIntro',
            htmlText: HtmlText::fromText(text: $messages->inviteIntro),
        );
        $replacements->addHtmlText(
            identifier: 'recipientLabel',
            htmlText: HtmlText::fromText(text: $messages->recipientLabel),
        );
        $replacements->addText(
            identifier: 'recipient',
            text: $dbAuthUser->email,
        );
        $replacements->addHtml(
            identifier: 'form',
            html: $userInviteForm->render(),
        );
    }
}
