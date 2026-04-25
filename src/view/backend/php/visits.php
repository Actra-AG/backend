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
use actra\backend\libs\form\VisitSearchForm;
use actra\backend\libs\table\VisitTable;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;
use actra\yuf\layout\NavigationItem;

class visits extends BackendView
{
    public function __construct()
    {
        parent::__construct(
            activeHtmlIdList: [
                'users',
                'visits',
            ],
            useNavigator: true,
            maxAllowedPathVars: 1
        );
    }

    public static function getNavigationItem(): NavigationItem
    {
        return new NavigationItem(
            navKey: 'visits',
            href: visits::getPath(userID: null) . '?reset',
            svgPath: '',
            title: 'Anmeldeversuche',
            requiredAccessRights: visits::getRequiredAccessRights()
        );
    }

    public static function getPath(?int $userID): string
    {
        return ActraBackend::get()->path . (is_null(
                value: $userID
            ) ? 'visits.html' : 'visits-' . $userID . '.html');
    }

    public static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::encoded(textContent: 'Besuche');
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        if (!is_null(value: $this->getPathVar(nr: 1))) {
            $dbAuthUserItem = DbAuthUserRepository::selectByID(ID: (int)$this->getPathVar(nr: 1));
            if (is_null(value: $dbAuthUserItem)) {
                throw new NotFoundException();
            }
            $filterUserID = $dbAuthUserItem->ID;
        } else {
            $filterUserID = null;
        }
        $pageIdentifier = 'VisitSearch-' . (int)$filterUserID;
        $visitSearchForm = new VisitSearchForm(name: $pageIdentifier . 'Form');
        $replacements = $htmlDocument->replacements;
        $replacements->addEncodedText(
            identifier: 'searchForm',
            content: $visitSearchForm->render()
        );
        $replacements->addEncodedText(
            identifier: 'table',
            content: new VisitTable(
                identifier: $pageIdentifier . 'Table',
                filterUserID: $filterUserID,
                tokenSearchForm: $visitSearchForm
            )->render()
        );
    }
}