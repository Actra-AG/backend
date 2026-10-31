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
use actra\backend\libs\form\SentEmailSearchForm;
use actra\backend\libs\table\SentEmailTable;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;
use actra\yuf\layout\NavigationItem;

/**
 * @internal
 */
final class sentEmails extends BackendView
{
    private const string PAGE_IDENTIFIER = 'SentEmailSearch';

    public function __construct(BackendViewContext $context)
    {
        parent::__construct(
            context: $context,
            activeHtmlIdList: [
                'users',
                'sentEmails',
            ],
            useNavigator: true,
        );
    }

    public static function getNavigationItem(BackendPaths $paths, BackendMessages $messages): NavigationItem
    {
        return new NavigationItem(
            navKey: 'sentEmails',
            href: $paths->sentEmails() . '?reset',
            svgPath: '',
            title: HtmlText::fromText(text: $messages->log->sentEmailsNavigationTitle),
            requiredAccessRights: sentEmails::getRequiredAccessRights(),
        );
    }

    #[\Override]
    public static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_VIEW_SENT_EMAILS,
        ]);
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: $this->backendContext->messages->log->sentEmailsPageTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $sentEmailSearchForm = new SentEmailSearchForm(
            context: $this->backendContext,
            name: sentEmails::PAGE_IDENTIFIER . 'Form',
        );
        $replacements = $htmlDocument->replacements;
        $replacements->addHtml(
            identifier: 'searchForm',
            html: $sentEmailSearchForm->render(),
        );
        $replacements->addHtml(
            identifier: 'table',
            html: new SentEmailTable(
                context: $this->backendContext,
                identifier: sentEmails::PAGE_IDENTIFIER . 'Table',
                sentEmailSearchForm: $sentEmailSearchForm,
            )->render(),
        );
    }
}
