<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

/**
 * Fills the placeholders of a message: `[name]` is replaced by the value with the key `name`.
 */
final class MessageTemplate
{
    /**
     * @param array<string, string> $values
     */
    public static function fill(string $template, array $values): string
    {
        $placeholders = [];
        foreach ($values as $name => $value) {
            $placeholders['[' . $name . ']'] = $value;
        }

        return strtr(string: $template, from: $placeholders);
    }

    /**
     * @return list<string> The placeholder names used in the message, sorted and without duplicates
     */
    public static function listPlaceholderNames(string $template): array
    {
        preg_match_all(pattern: '/\[([a-zA-Z][a-zA-Z0-9]*)]/', subject: $template, matches: $matches);
        $names = array_values(array: array_unique(array: $matches[1]));
        sort(array: $names);

        return $names;
    }
}