<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendPaths;
use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\backend\i18n\BackendMessages;
use actra\backend\libs\form\UserSearchForm;
use actra\backend\libs\table\UserTable;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;
use actra\yuf\layout\NavigationItem;

/**
 * @internal
 */
final class users extends BackendView
{
    public const string PARAM_REMOVED = 'removed';

    public function __construct(BackendViewContext $context)
    {
        $inputParameterCollection = new InputParameterCollection();
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: users::PARAM_REMOVED,
                source: InputSourceEnum::QUERY,
                isRequired: false,
            ),
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

    public static function getNavigationItem(BackendPaths $paths, BackendMessages $messages): NavigationItem
    {
        return new NavigationItem(
            navKey: 'userList',
            href: $paths->users() . '?reset',
            svgPath: '',
            title: $messages->user->navigationUserList,
            requiredAccessRights: users::getRequiredAccessRights(),
        );
    }

    #[\Override]
    public static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: $this->backendContext->messages->user->usersTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $userSearchForm = new UserSearchForm(context: $this->backendContext);

        $messages = $this->backendContext->messages->user;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'addUserTitle',
            htmlText: HtmlText::fromText(text: $messages->addUserTitle),
        );
        $replacements->addHtmlText(
            identifier: 'successLabel',
            htmlText: HtmlText::fromText(text: $this->backendContext->messages->common->successLabel),
        );
        $replacements->addHtmlText(
            identifier: 'removedMessage',
            htmlText: HtmlText::fromText(text: $messages->removedMessage),
        );
        $replacements->addHtml(
            identifier: 'addHref',
            html: $this->backendContext->paths->userAdd(),
        );
        $replacements->addBool(
            identifier: 'removed',
            booleanValue: $this->getInputString(keyName: users::PARAM_REMOVED) !== null,
        );
        $replacements->addHtml(
            identifier: 'searchForm',
            html: $userSearchForm->render(),
        );
        $replacements->addHtml(
            identifier: 'table',
            html: new UserTable(context: $this->backendContext, userSearchForm: $userSearchForm)->render(),
        );
    }
}
