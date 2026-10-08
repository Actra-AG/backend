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
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\form\UserModForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class userMod extends BackendView
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

    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: ActraBackend::messages()->user->editUserTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = DbAuthUserRepository::selectByID(ID: $this->getRequiredPathVarAsInt(nr: 1));
        if ($dbAuthUser === null) {
            throw new NotFoundException();
        }
        $replacements = $htmlDocument->replacements;
        $userModForm = new UserModForm(context: $this->backendContext, dbAuthUser: $dbAuthUser);
        if ($userModForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: user::getPath(
                    ID: $dbAuthUser->ID,
                ) . '?' . user::PARAM_CHANGED,
                httpRequest: $this->context->httpRequest,
            );
        }
        $replacements->addHtml(
            identifier: 'form',
            html: $userModForm->render(),
        );
    }

    public static function getPath(int $ID): string
    {
        return ActraBackend::path() . 'userMod-' . $ID . '.html';
    }
}
