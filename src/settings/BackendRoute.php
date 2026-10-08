<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\backend\i18n\BackendMessages;
use actra\yuf\core\Language;

/**
 * A route (URL path) of the backend in one language. The texts of the route follow its language.
 */
final readonly class BackendRoute
{
    public BackendMessages $messages;

    /**
     * @param string $path The URL path of the backend in this language, e.g. '/en/backend/'
     * @param ?BackendMessages $messages Default: `BackendMessages::forLanguageCode()` of the language
     */
    public function __construct(
        public string $path,
        public Language $language,
        public bool $isDefaultForLanguage = false,
        ?BackendMessages $messages = null,
    ) {
        $this->messages = $messages ?? BackendMessages::forLanguageCode(languageCode: $language->code);
    }
}
