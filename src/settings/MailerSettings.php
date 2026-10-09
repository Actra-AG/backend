<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\yuf\common\FileCache;

final readonly class MailerSettings
{
    /**
     * @param ?FileCache $serverNameCache Keeps the host name of the server for a day (`$core->fileCache`), `null` to
     *                                    look it up for every mail
     */
    public function __construct(
        public string $senderEmail,
        public string $senderName,
        public string $hostname,
        public string $username,
        public string $password,
        public int $port,
        public bool $tls,
        public string $signature,
        public ?FileCache $serverNameCache,
    ) {}
}
