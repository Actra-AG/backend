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
use actra\backend\libs\form\UserModForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class userMod extends BackendView
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
        return HtmlText::encoded(textContent: 'Benutzer bearbeiten');
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = DbAuthUserRepository::selectByID(ID: (int)$this->getPathVar(nr: 1));
        if ($dbAuthUser === null) {
            throw new NotFoundException();
        }
        $replacements = $htmlDocument->replacements;
        $userModForm = new UserModForm(dbAuthUser: $dbAuthUser);
        if ($userModForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: user::getPath(
                    ID: $dbAuthUser->ID
                ) . '?' . user::PARAM_CHANGED
            );
        }
        $replacements->addEncodedText(
            identifier: 'form',
            content: $userModForm->render()
        );
    }

    public static function getPath(int $ID): string
    {
        return ActraBackend::get()->path . 'userMod-' . $ID . '.html';
    }
}