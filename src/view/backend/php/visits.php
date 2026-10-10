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
use actra\backend\libs\form\VisitSearchForm;
use actra\backend\libs\table\VisitTable;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;
use actra\yuf\layout\NavigationItem;

/**
 * @internal
 */
final class visits extends BackendView
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

    public static function getNavigationItem(BackendPaths $paths, BackendMessages $messages): NavigationItem
    {
        return new NavigationItem(
            navKey: 'visits',
            href: $paths->visits(userId: null) . '?reset',
            svgPath: '',
            title: HtmlText::fromText(text: $messages->log->visitsNavigationTitle),
            requiredAccessRights: visits::getRequiredAccessRights(),
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
        return HtmlText::fromText(text: $this->backendContext->messages->log->visitsPageTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        if ($this->getPathVar(nr: 1) !== null) {
            $pathUserId = $this->getRequiredPathVarAsInt(nr: 1);
            $dbAuthUser = $this->backendContext->repositories->users()->selectById(id: $pathUserId);
            if ($dbAuthUser === null) {
                throw new NotFoundException();
            }
            $filterUserId = $dbAuthUser->id;
        } else {
            $filterUserId = null;
        }
        $pageIdentifier = 'VisitSearch-' . (int) $filterUserId;
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
                filterUserId: $filterUserId,
                tokenSearchForm: $visitSearchForm,
            )->render(),
        );
    }
}
