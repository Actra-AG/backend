<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\BackendViewContext;
use actra\backend\libs\form\TokenSearchForm;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\common\SearchQueryBuilder;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\TableItem;
use UnexpectedValueException;

/**
 * @internal
 */
final class TokenTable extends AbstractTable
{
    public function __construct(
        BackendViewContext $context,
        string $identifier,
        ?int $filterUserId,
        TokenSearchForm $tokenSearchForm,
    ) {
        $dbQuery = $context->repositories->tokens()->getDbQuery();
        if ($filterUserId !== null) {
            $dbQuery->addWherePart(
                wherePart: 'auth_token.user_id=?',
                parameters: [
                    $filterUserId,
                ],
            );
        }
        $authTokenTypeEnum = $tokenSearchForm->authTokenTypeEnum;
        if ($authTokenTypeEnum !== null) {
            $dbQuery->addWherePart(
                wherePart: 'auth_token.type=?',
                parameters: [
                    $authTokenTypeEnum->value,
                ],
            );
        }
        $searchQuery = $tokenSearchForm->searchQuery;
        if ($searchQuery !== '') {
            $booleanQuery = SearchQueryBuilder::createBooleanQuery(
                spaceSeparatedFieldNames: 'auth_user.first_name auth_user.last_name auth_token.registered_client '
                    . 'auth_token.claimed_client',
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
        $messages = $context->messages;
        $registeredColumn = $this->createDateColumn(
            identifier: 'registered',
            label: HtmlText::fromText(text: $messages->log->tokenCreatedDateColumn),
            withTime: true,
            isSortable: true,
            sortAscendingByDefault: false,
        );
        $this->addColumn(abstractTableColumn: $registeredColumn, isDefaultSortColumn: true);
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'registered_client',
                label: HtmlText::fromText(text: $messages->log->tokenCreatedClientColumn),
                callbackFunction: static fn(TableItem $tableItem): string => TokenTable::renderClient(
                    clientJson: $tableItem->getRow()->getNullableString(column: 'registered_client'),
                ),
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'type',
                label: HtmlText::fromText(text: $messages->log->typeLabel),
                callbackFunction: static fn(TableItem $tableItem): string => HtmlEncoder::encode(
                    value: $tableItem->getRow()->getEnum(
                        column: 'type',
                        enumClass: AuthTokenTypeEnum::class,
                    )->render(messages: $messages->log),
                ),
                isSortable: true,
            ),
        );
        $claimedColumn = $this->createDateColumn(
            identifier: 'claimed',
            label: HtmlText::fromText(text: $messages->log->tokenClaimedDateColumn),
            withTime: true,
            isSortable: true,
        );
        $this->addColumn(abstractTableColumn: $claimedColumn);
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'claimed_client',
                label: HtmlText::fromText(text: $messages->log->tokenClaimedClientColumn),
                callbackFunction: static fn(TableItem $tableItem): string => TokenTable::renderClient(
                    clientJson: $tableItem->getRow()->getNullableString(column: 'claimed_client'),
                ),
                isSortable: true,
            ),
        );
    }

    /**
     * Lists the JSON client data as "key: value" lines. The values are output as stored (legacy behaviour).
     */
    private static function renderClient(?string $clientJson): string
    {
        if ($clientJson === null || $clientJson === '') {
            return '';
        }
        $client = json_decode(json: $clientJson);
        if (!is_object(value: $client)) {
            throw new UnexpectedValueException(message: 'The client data must be a JSON object.');
        }
        $list = [];
        foreach (get_object_vars(object: $client) as $key => $value) {
            // Client data is sent by the browser: always encode it
            $list[] = HtmlEncoder::encode(
                value: $key . ': ' . (is_scalar(value: $value) ? (string) $value : json_encode(value: $value)),
            );
        }

        return implode(separator: '<br>', array: $list);
    }
}
