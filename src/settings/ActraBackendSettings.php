<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\backend\i18n\BackendMessages;
use actra\backend\libs\auth\UserDeleteHandler;
use actra\yuf\core\Language;

final readonly class ActraBackendSettings
{
    /** The texts of the main route (path of `ActraBackend::init()` in `$language`) */
    public BackendMessages $messages;

    /**
     * @param Language $language The language of the main route
     * @param list<string> $ipWhitelist
     * @param list<string> $javaScriptPaths
     * @param list<string> $stylesPaths
     * @param ?BackendMessages $messages Texts of the main route; default: `BackendMessages::forLanguageCode()`
     * @param list<BackendRoute> $additionalRoutes The backend in further languages, one route per language
     * @param ?BackendNavigation $projectNavigation Adds the project's navigation items per route language
     * @param ?UserDeleteHandler $userDeleteHandler Deletes the project's data of a user before the user is deleted
     */
    public function __construct(
        public Language $language,
        public array $ipWhitelist,
        public string $backendName,
        public array $javaScriptPaths,
        public array $stylesPaths,
        public int $maxAllowedLoginAttempts = 5,
        public string $frontendHref = '',
        public string $frontendName = '',
        public bool $hasApi = false,
        ?BackendMessages $messages = null,
        public array $additionalRoutes = [],
        public ?BackendNavigation $projectNavigation = null,
        public ?UserDeleteHandler $userDeleteHandler = null,
    ) {
        $this->messages = $messages ?? BackendMessages::forLanguageCode(languageCode: $language->code);
    }
}
