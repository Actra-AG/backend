<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
use actra\backend\libs\db\DbAuthTokenRepository;
use actra\backend\libs\form\TokenSearchForm;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\common\SearchQueryBuilder;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\TableItem;
use UnexpectedValueException;

class TokenTable extends AbstractTable
{
    public function __construct(
        BackendViewContext $context,
        string $identifier,
        ?int $filterUserID,
        TokenSearchForm $tokenSearchForm,
    ) {
        $dbQuery = DbAuthTokenRepository::getDbQuery();
        if ($filterUserID !== null) {
            $dbQuery->addWherePart(
                wherePart: 'auth_token.userID=?',
                parameters: [
                    $filterUserID,
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
                spaceSeparatedFieldNames: 'auth_user.firstName auth_user.lastName auth_token.token auth_token.registeredClient auth_token.claimedClient',
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
        $messages = ActraBackend::messages();
        $registeredColumn = new DateColumn(
            identifier: 'registered',
            label: $messages->log->tokenCreatedDateColumn,
            isSortable: true,
            sortAscendingByDefault: false,
        );
        $registeredColumn->format = $messages->common->dateTimeFormat;
        $this->addColumn(abstractTableColumn: $registeredColumn, isDefaultSortColumn: true);
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'registeredClient',
                label: $messages->log->tokenCreatedClientColumn,
                callbackFunction: static fn(TableItem $tableItem): string => TokenTable::renderClient(
                    clientJson: $tableItem->getRow()->getNullableString(column: 'registeredClient'),
                ),
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'type',
                label: $messages->log->typeLabel,
                callbackFunction: static fn(TableItem $tableItem): string => HtmlEncoder::encode(
                    value: $tableItem->getRow()->getEnum(
                        column: 'type',
                        enumClass: AuthTokenTypeEnum::class,
                    )->render(messages: $messages->log),
                ),
                isSortable: true,
            ),
        );
        $claimedColumn = new DateColumn(
            identifier: 'claimed',
            label: $messages->log->tokenClaimedDateColumn,
            isSortable: true,
        );
        $claimedColumn->format = $messages->common->dateTimeFormat;
        $this->addColumn(abstractTableColumn: $claimedColumn);
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'claimedClient',
                label: $messages->log->tokenClaimedClientColumn,
                callbackFunction: static fn(TableItem $tableItem): string => TokenTable::renderClient(
                    clientJson: $tableItem->getRow()->getNullableString(column: 'claimedClient'),
                ),
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'token',
                label: $messages->log->tokenColumn,
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
