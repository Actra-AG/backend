<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\form\UserSearchForm;
use actra\backend\libs\table\UserTable;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;
use actra\yuf\layout\NavigationItem;

class users extends BackendView
{
    public const string PARAM_REMOVED = 'removed';

    public function __construct()
    {
        $inputParameterCollection = new InputParameterCollection();
        $inputParameterCollection->add(
            inputParameter: new InputParameter(name: users::PARAM_REMOVED, isRequired: false)
        );
        parent::__construct(
            inputParameterCollection: $inputParameterCollection,
            activeHtmlIdList: [
                'users',
                'userList',
            ],
            useNavigator: true
        );
    }

    public static function getNavigationItem(): NavigationItem
    {
        return new NavigationItem(
            navKey: 'userList',
            href: users::getPath() . '?reset',
            svgPath: '',
            title: 'Benutzerliste',
            requiredAccessRights: users::getRequiredAccessRights()
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::get()->path . 'users.html';
    }

    public static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::encoded(textContent: 'Benutzer');
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $userSearchForm = new UserSearchForm();

        $replacements = $htmlDocument->replacements;
        $replacements->addEncodedText(
            identifier: 'addHref',
            content: userAdd::getPath()
        );
        $replacements->addBool(
            identifier: 'removed',
            booleanValue: $this->getInputString(keyName: users::PARAM_REMOVED) !== null
        );
        $replacements->addEncodedText(
            identifier: 'searchForm',
            content: $userSearchForm->render()
        );
        $replacements->addEncodedText(
            identifier: 'table',
            content: new UserTable(userSearchForm: $userSearchForm)->render()
        );
    }
}