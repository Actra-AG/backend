<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

/**
 * Counts the runs of the action of a confirmation page (`ConfirmationViewTest`); a project service passed by the
 * `create` closure of the view factory.
 */
final class ConfirmationCalls
{
    public int $count = 0;
}
