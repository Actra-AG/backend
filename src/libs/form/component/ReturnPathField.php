<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form\component;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\LoginRedirect;
use actra\yuf\form\component\field\HiddenField;

/**
 * Carries the page requested before the login (`?returnTo=` of yuf's login redirect) through the login steps: yuf's
 * forms post to `?<form name>`, which drops the query of the page.
 *
 * @internal
 */
final readonly class ReturnPathField
{
    public static function create(HttpRequest $httpRequest): HiddenField
    {
        return new HiddenField(
            name: LoginRedirect::RETURN_PARAMETER,
            value: LoginRedirect::findReturnPath(httpRequest: $httpRequest),
        );
    }

    /**
     * The validated local path of the field, `null` if it is empty or no local path.
     */
    public static function getReturnPath(HiddenField $hiddenField): ?string
    {
        $returnPath = $hiddenField->getValueAsString();

        return LoginRedirect::isLocalPath(uri: $returnPath) ? $returnPath : null;
    }
}
