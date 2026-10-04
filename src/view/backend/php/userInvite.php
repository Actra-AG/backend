<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\form\UserInviteForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class userInvite extends BackendView
{
    public function __construct()
    {
        parent::__construct(
            maxAllowedPathVars: 1,
            activeHtmlIdList: [
                'users',
                'userList',
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
        return HtmlText::unencoded(textContent: ActraBackend::messages()->user->inviteTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = DbAuthUserRepository::selectByID(ID: $this->getRequiredPathVarAsInt(nr: 1));
        if ($dbAuthUser === null) {
            throw new NotFoundException();
        }
        $userInviteForm = new UserInviteForm(dbAuthUser: $dbAuthUser);
        if ($userInviteForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: user::getPath(
                    ID: $dbAuthUser->ID
                ) . '?' . user::PARAM_INVITED
            );
        }
        $messages = ActraBackend::messages()->user;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'inviteIntro',
            htmlText: HtmlText::unencoded(textContent: $messages->inviteIntro)
        );
        $replacements->addHtmlText(
            identifier: 'recipientLabel',
            htmlText: HtmlText::unencoded(textContent: $messages->recipientLabel)
        );
        $replacements->addEncodedText(
            identifier: 'recipient',
            content: $dbAuthUser->email
        );
        $replacements->addEncodedText(
            identifier: 'form',
            content: $userInviteForm->render()
        );
    }

    public static function getPath(int $ID): string
    {
        return ActraBackend::path() . 'userInvite-' . $ID . '.html';
    }
}