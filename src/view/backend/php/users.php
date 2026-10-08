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

    public function __construct(BackendViewContext $context)
    {
        $inputParameterCollection = new InputParameterCollection();
        $inputParameterCollection->add(
            inputParameter: new InputParameter(name: users::PARAM_REMOVED, isRequired: false),
        );
        parent::__construct(
            context: $context,
            inputParameterCollection: $inputParameterCollection,
            activeHtmlIdList: [
                'users',
                'userList',
            ],
            useNavigator: true,
        );
    }

    public static function getNavigationItem(): NavigationItem
    {
        return new NavigationItem(
            navKey: 'userList',
            href: users::getPath() . '?reset',
            svgPath: '',
            title: ActraBackend::messages()->user->navigationUserList,
            requiredAccessRights: users::getRequiredAccessRights(),
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::path() . 'users.html';
    }

    public static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::unencoded(textContent: ActraBackend::messages()->user->usersTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $userSearchForm = new UserSearchForm(context: $this->backendContext);

        $messages = ActraBackend::messages()->user;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'addUserTitle',
            htmlText: HtmlText::unencoded(textContent: $messages->addUserTitle),
        );
        $replacements->addHtmlText(
            identifier: 'successLabel',
            htmlText: HtmlText::unencoded(textContent: ActraBackend::messages()->common->successLabel),
        );
        $replacements->addHtmlText(
            identifier: 'removedMessage',
            htmlText: HtmlText::unencoded(textContent: $messages->removedMessage),
        );
        $replacements->addEncodedText(
            identifier: 'addHref',
            content: userAdd::getPath(),
        );
        $replacements->addBool(
            identifier: 'removed',
            booleanValue: $this->getInputString(keyName: users::PARAM_REMOVED) !== null,
        );
        $replacements->addEncodedText(
            identifier: 'searchForm',
            content: $userSearchForm->render(),
        );
        $replacements->addEncodedText(
            identifier: 'table',
            content: new UserTable(context: $this->backendContext, userSearchForm: $userSearchForm)->render(),
        );
    }
}
