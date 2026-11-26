<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

/**
 * The level of a `PageNotice`; the value is the class of the `.msg` block (`src/assets/css/blocks/_msg.css`).
 */
enum PageNoticeLevelEnum: string
{
    case NOTE = 'msg-note';
    case SUCCESS = 'msg-success';
    case WARNING = 'msg-warning';
    case ERROR = 'msg-error';
}
