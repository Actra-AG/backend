<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\yuf\auth\SsoNonceCookieWriter;
use Override;

/**
 * Keeps the cookies a `LoginHandOver` sends (the browser cookie of one host), to send them with the next request of
 * that host.
 */
final class RecordingCookieWriter implements SsoNonceCookieWriter
{
    /** @var array<string, string> */
    public private(set) array $cookies = [];

    #[Override]
    public function set(string $name, string $value, int $maxAgeInSeconds, string $path): void
    {
        $this->cookies[$name] = $value;
    }

    #[Override]
    public function delete(string $name, string $path): void
    {
        unset($this->cookies[$name]);
    }
}
