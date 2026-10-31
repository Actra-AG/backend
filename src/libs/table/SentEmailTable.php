<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\BackendViewContext;
use actra\backend\libs\form\SentEmailSearchForm;
use actra\yuf\common\SearchQueryBuilder;
use actra\yuf\html\HtmlText;
use actra\yuf\table\column\DefaultColumn;

/**
 * The log of the sent emails, newest first.
 *
 * @internal
 */
final class SentEmailTable extends AbstractTable
{
    public function __construct(
        BackendViewContext $context,
        string $identifier,
        SentEmailSearchForm $sentEmailSearchForm,
    ) {
        $dbQuery = $context->repositories->sentEmails()->getDbQuery();
        $searchQuery = $sentEmailSearchForm->searchQuery;
        if ($searchQuery !== '') {
            $booleanQuery = SearchQueryBuilder::createBooleanQuery(
                spaceSeparatedFieldNames: 'sent_emails.to_emails sent_emails.cc_emails sent_emails.bcc_emails '
                    . 'sent_emails.from_email sent_emails.subject',
                queryText: $searchQuery,
            );
            $dbQuery->addWherePart(
                wherePart: $booleanQuery->query,
                parameters: $booleanQuery->params,
            );
        }
        parent::__construct(
            context: $context,
            identifier: $identifier,
            dbQuery: $dbQuery,
            itemsPerPage: 100,
        );
        $this->addSortTieBreaker(column: 'sent_emails.id', ascending: false);
        $messages = $context->messages->log;
        $dateColumn = $this->createDateColumn(
            identifier: 'sent_at',
            label: HtmlText::fromText(text: $messages->sentEmailDateColumn),
            withTime: true,
            isSortable: true,
            sortAscendingByDefault: false,
        );
        $this->addColumn(abstractTableColumn: $dateColumn, isDefaultSortColumn: true);
        $columns = [
            'to_emails' => $messages->sentEmailToColumn,
            'cc_emails' => $messages->sentEmailCcColumn,
            'bcc_emails' => $messages->sentEmailBccColumn,
            'subject' => $messages->sentEmailSubjectColumn,
            'from_email' => $messages->sentEmailFromColumn,
        ];
        foreach ($columns as $identifier => $label) {
            $this->addColumn(
                abstractTableColumn: new DefaultColumn(
                    identifier: $identifier,
                    label: HtmlText::fromText(text: $label),
                    isSortable: true,
                ),
            );
        }
    }
}
