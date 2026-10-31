<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\email;

use Closure;

/**
 * The secrets of the mail being sent (e.g. a login code in the subject), which the log of sent emails shows masked.
 * `ActraBackend` shares one instance between its `Mailer` and its `SentEmailLogger`: the mailer registers the secrets
 * while it sends, the logger (called within the sending) masks them.
 */
final class SentEmailSecrets
{
    public const string MASK = '••••••';
    /** @var list<string> */
    private array $secrets = [];

    /**
     * Runs `$send`; the mails sent meanwhile are logged with `MASK` instead of the secrets.
     *
     * @param list<string> $secrets
     * @param Closure(): void $send
     */
    public function maskWhile(#[\SensitiveParameter] array $secrets, Closure $send): void
    {
        $previousSecrets = $this->secrets;
        $this->secrets = [
            ...$previousSecrets,
            ...array_values(array: array_filter(
                array: $secrets,
                callback: static fn(string $secret): bool => $secret !== '',
            )),
        ];
        try {
            $send();
        } finally {
            $this->secrets = $previousSecrets;
        }
    }

    public function mask(string $text): string
    {
        return $this->secrets === []
            ? $text
            : str_replace(search: $this->secrets, replace: SentEmailSecrets::MASK, subject: $text);
    }
}
