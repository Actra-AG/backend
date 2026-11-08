<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form\component;

use actra\backend\libs\auth\BackendLogin;
use actra\yuf\auth\LoginHandOver;
use actra\yuf\form\component\field\HiddenField;

/**
 * Carries the login request of a site (`?loginRequest=`, yuf's `LoginHandOver`) through the login steps on the login
 * host: yuf's forms post to `?<form name>`, which drops the query of the page. Only the ID of a valid request is
 * kept; it is checked again when the login is handed over. Only a login host has the field, so the HTML of the login
 * forms of other backends is unchanged.
 *
 * @internal
 */
final readonly class LoginRequestField
{
    /**
     * The field on a login host (`BackendLogin::isLoginHost()`), `null` elsewhere.
     */
    public static function create(BackendLogin $login): ?HiddenField
    {
        if (!$login->isLoginHost()) {
            return null;
        }

        return new HiddenField(
            name: LoginHandOver::REQUEST_PARAMETER,
            value: $login->findLoginRequest()?->id,
        );
    }

    /**
     * The ID of the field, `null` if it is empty.
     */
    public static function getLoginRequestId(?HiddenField $hiddenField): ?string
    {
        $loginRequestId = $hiddenField?->getValueAsString() ?? '';

        return $loginRequestId === '' ? null : $loginRequestId;
    }
}
