<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\auth\UserController;
use actra\backend\libs\db\DbAuthGroupRepository;
use actra\backend\libs\db\DbAuthSessionRepository;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthSession;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class user extends BackendView
{
    public const string PARAM_IMPERSONATE = 'impersonate';
    public const string PARAM_REMOVE = 'remove';
    public const string PARAM_ADDED = 'add';
    public const string PARAM_CHANGED = 'mod';
    public const string PARAM_INVITED = 'invited';

    private readonly HtmlText $pageTitle;

    public function __construct()
    {
        $inputParameterCollection = new InputParameterCollection();
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: user::PARAM_IMPERSONATE,
                isRequired: false
            )
        );
        $inputParameterCollection->add(inputParameter: new InputParameter(name: user::PARAM_REMOVE, isRequired: false));
        $inputParameterCollection->add(inputParameter: new InputParameter(name: user::PARAM_ADDED, isRequired: false));
        $inputParameterCollection->add(
            inputParameter: new InputParameter(name: user::PARAM_CHANGED, isRequired: false)
        );
        $inputParameterCollection->add(
            inputParameter: new InputParameter(name: user::PARAM_INVITED, isRequired: false)
        );
        parent::__construct(
            inputParameterCollection: $inputParameterCollection,
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
        return $this->pageTitle;
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUserItem = DbAuthUserRepository::selectByID(ID: (int)$this->getPathVar(nr: 1));
        if (is_null(value: $dbAuthUserItem)) {
            throw new NotFoundException();
        }
        $this->pageTitle = HtmlText::unencoded(
            textContent: $dbAuthUserItem->firstName . ' ' . $dbAuthUserItem->lastName
        );
        $authUser = MyAuthUser::get();
        $canImpersonate = $authUser->canImpersonateUser(dbAuthUser: $dbAuthUserItem);
        if (!is_null(value: $this->getInputString(keyName: user::PARAM_REMOVE))) {
            UserController::deleteUser(userID: $dbAuthUserItem->ID);
            HttpResponse::redirectAndExit(relativeOrAbsoluteUri: users::getPath() . '?' . users::PARAM_REMOVED);
        }
        if (
            $canImpersonate
            && !is_null(value: $this->getInputString(keyName: user::PARAM_IMPERSONATE))
        ) {
            AuthSession::logIn(
                authSessionID: DbAuthSessionRepository::insert(
                    parentID: AuthSession::getAuthSessionID(),
                    userID: $dbAuthUserItem->ID
                )
            );
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: ActraBackend::get()->navigationItemCollection->getFirst(
                    authUser: MyAuthUser::get()
                )->href
            );
        }
        $replacements = $htmlDocument->replacements;
        $replacements->addEncodedText(
            identifier: 'userModHref',
            content: userMod::getPath(ID: $dbAuthUserItem->ID)
        );
        $replacements->addEncodedText(
            identifier: 'impersonateHref',
            content: $canImpersonate ? '?' . user::PARAM_IMPERSONATE : ''
        );
        $replacements->addEncodedText(
            identifier: 'removeHref',
            content: '?' . user::PARAM_REMOVE
        );
        $replacements->addBool(
            identifier: 'added',
            booleanValue: !is_null(value: $this->getInputString(keyName: user::PARAM_ADDED))
        );
        $replacements->addBool(
            identifier: 'changed',
            booleanValue: !is_null(value: $this->getInputString(keyName: user::PARAM_CHANGED))
        );
        $replacements->addBool(
            identifier: 'invited',
            booleanValue: !is_null(value: $this->getInputString(keyName: user::PARAM_INVITED))
        );
        $replacements->addBool(
            identifier: 'isInvited',
            booleanValue: $dbAuthUserItem->isInvited()
        );
        $replacements->addEncodedText(
            identifier: 'inviteHref',
            content: userInvite::getPath(ID: $dbAuthUserItem->ID)
        );
        $replacements->addUnencodedText(
            identifier: 'firstName',
            content: $dbAuthUserItem->firstName
        );
        $replacements->addUnencodedText(
            identifier: 'lastName',
            content: $dbAuthUserItem->lastName
        );
        $replacements->addEncodedText(
            identifier: 'email',
            content: $dbAuthUserItem->email
        );
        $replacements->addEncodedText(
            identifier: 'registered',
            content: $dbAuthUserItem->registered->format(format: 'd.m.Y H:i:s')
        );
        $replacements->addEncodedText(
            identifier: 'invitedDate',
            content: $dbAuthUserItem->isInvited() ? $dbAuthUserItem->invitedDate->format(format: 'd.m.Y H:i:s') : ''
        );
        $replacements->addEncodedText(
            identifier: 'lastLogin',
            content: $dbAuthUserItem->renderLastLogin()
        );
        $replacements->addEncodedText(
            identifier: 'visitsHref',
            content: visits::getPath(userID: $dbAuthUserItem->ID)
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'userGroups',
            htmlDataObjectCollection: $this->renderUserGroups(dbAuthUser: $dbAuthUserItem)
        );
        $replacements->addEncodedText(
            identifier: 'active',
            content: $dbAuthUserItem->isActive ? 'ja' : 'nein'
        );
    }

    public static function getPath(int|string $ID): string
    {
        return ActraBackend::get()->path . 'user-' . $ID . '.html';
    }

    public function renderUserGroups(DbAuthUser $dbAuthUser): ?HtmlDataObjectCollection
    {
        $userGroups = new HtmlDataObjectCollection();
        foreach (DbAuthGroupRepository::listByUserID(userID: $dbAuthUser->ID)->items as $dbAuthGroupItem) {
            $userGroup = new HtmlDataObject();
            $userGroup->addTextElement(
                propertyName: 'name',
                content: $dbAuthGroupItem->title,
                isEncodedForRendering: true
            );
            $userGroups->add(htmlDataObject: $userGroup);
        }

        return $userGroups;
    }
}