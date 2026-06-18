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
use actra\backend\libs\form\TokenSearchForm;
use actra\backend\libs\table\TokenTable;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;
use actra\yuf\layout\NavigationItem;

class tokens extends BackendView
{
    public function __construct()
    {
        parent::__construct(
            maxAllowedPathVars: 1,
            activeHtmlIdList: [
                'users',
                'tokens',
            ],
            useNavigator: true
        );
    }

    public static function getNavigationItem(): NavigationItem
    {
        return new NavigationItem(
            navKey: 'tokens',
            href: tokens::getPath(userID: null) . '?reset',
            svgPath: '',
            title: 'Codes',
            requiredAccessRights: tokens::getRequiredAccessRights()
        );
    }

    public static function getPath(?int $userID): string
    {
        return ActraBackend::get()->path . ($userID === null ? 'tokens.html' : 'tokens-' . $userID . '.html');
    }

    public static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::encoded(textContent: 'Codes');
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $inputToken = $this->getPathVar(nr: 1);
        if ($inputToken !== null) {
            $dbAuthUser = DbAuthUserRepository::selectByID(ID: (int)$inputToken);
            if ($dbAuthUser === null) {
                throw new NotFoundException();
            }
            $filterUserID = $dbAuthUser->ID;
        } else {
            $filterUserID = null;
        }
        $pageIdentifier = 'TokenSearch-' . (int)$filterUserID;
        $tokenSearchForm = new TokenSearchForm(name: $pageIdentifier . 'Form');
        $replacements = $htmlDocument->replacements;
        $replacements->addEncodedText(
            identifier: 'searchForm',
            content: $tokenSearchForm->render()
        );
        $replacements->addEncodedText(
            identifier: 'table',
            content: new TokenTable(
                identifier: $pageIdentifier . 'Table',
                filterUserID: $filterUserID,
                tokenSearchForm: $tokenSearchForm
            )->render()
        );
    }
}