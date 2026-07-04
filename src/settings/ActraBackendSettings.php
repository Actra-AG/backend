<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\yuf\core\Language;

readonly class ActraBackendSettings
{
    public function __construct(
        public Language $language,
        public array $ipWhitelist,
        public string $backendName,
        public array $javaScriptPaths,
        public array $stylesPaths,
        public int $maxAllowedLoginAttempts = 5,
        public string $frontendHref = '',
        public string $frontendName = '',
        public bool $hasApi = false
    ) {
    }
}