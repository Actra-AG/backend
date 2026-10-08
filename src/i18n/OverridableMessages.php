<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

use Error;

/**
 * Lets a project replace single texts of a message class, e.g. `AuthMessages::german()->with(loginPageTitle: 'Login')`.
 * The properties are readonly, so they can only be changed in a copy made inside the class.
 */
trait OverridableMessages
{
    /**
     * @param string ...$texts The new texts as named arguments (property name => text)
     * @throws Error If a name is not a text of the class
     */
    public function with(string ...$texts): static
    {
        return clone($this, $texts);
    }
}
