<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

readonly class MailerSettings
{
    public function __construct(
        public string $senderEmail,
        public string $hostname,
        public string $username,
        public string $password,
        public int $port,
        public bool $tls,
        public string $signature
    ) {
    }
}