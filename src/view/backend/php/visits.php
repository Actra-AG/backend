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
use actra\backend\libs\form\VisitSearchForm;
use actra\backend\libs\table\VisitTable;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;
use actra\yuf\layout\NavigationItem;

class visits extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(
            context: $context,
            maxAllowedPathVars: 1,
            activeHtmlIdList: [
                'users',
                'visits',
            ],
            useNavigator: true,
        );
    }

    public static function getNavigationItem(): NavigationItem
    {
        return new NavigationItem(
            navKey: 'visits',
            href: visits::getPath(userID: null) . '?reset',
            svgPath: '',
            title: ActraBackend::messages()->log->visitsNavigationTitle,
            requiredAccessRights: visits::getRequiredAccessRights(),
        );
    }

    public static function getPath(?int $userID): string
    {
        return ActraBackend::path() . ($userID === null ? 'visits.html' : 'visits-' . $userID . '.html');
    }

    public static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: ActraBackend::messages()->log->visitsPageTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        if ($this->getPathVar(nr: 1) !== null) {
            $dbAuthUser = DbAuthUserRepository::selectByID(ID: $this->getRequiredPathVarAsInt(nr: 1));
            if ($dbAuthUser === null) {
                throw new NotFoundException();
            }
            $filterUserID = $dbAuthUser->ID;
        } else {
            $filterUserID = null;
        }
        $pageIdentifier = 'VisitSearch-' . (int) $filterUserID;
        $visitSearchForm = new VisitSearchForm(context: $this->backendContext, name: $pageIdentifier . 'Form');
        $replacements = $htmlDocument->replacements;
        $replacements->addHtml(
            identifier: 'searchForm',
            html: $visitSearchForm->render(),
        );
        $replacements->addHtml(
            identifier: 'table',
            html: new VisitTable(
                context: $this->backendContext,
                identifier: $pageIdentifier . 'Table',
                filterUserID: $filterUserID,
                tokenSearchForm: $visitSearchForm,
            )->render(),
        );
    }
}
