<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\i18n;

use actra\backend\i18n\BackendMessages;
use actra\backend\i18n\MessageTemplate;
use actra\yuf\form\FormMessages;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use ReflectionProperty;

/**
 * Checks every part of the backend texts (`BackendMessages`): complete texts in all four languages, the same
 * placeholders, translated French and Italian texts and the forms of address used in Switzerland.
 */
final class MessagesTest extends TestCase
{
    private const array PARTS = [
        'form',
        'common',
        'layout',
        'auth',
        'user',
        'profile',
        'notification',
        'log',
        'email',
        'table',
        'authResult',
    ];
    /**
     * Texts that are the same in English and in the language (names of things, abbreviations, placeholders only).
     */
    private const array SAME_AS_ENGLISH = [
        'fr' => [
            'auth.tokenLabel',
            'common.fullName',
            'common.messageBodyLabel',
            'common.userOption',
            'email.signature',
            'log.authTokenTypeActivation',
            'log.sentEmailCcColumn',
            'log.tokensNavigationTitle',
            'log.tokensPageTitle',
            'log.typeLabel',
            'log.visitDateColumn',
            'notification.dateLabel',
            'notification.messageLabel',
            'notification.title',
        ],
        'it' => [
            'auth.passwordLabel',
            'common.fullName',
            'common.userOption',
            'email.signature',
            'log.sentEmailCcColumn',
            'profile.passwordLabel',
            'user.no',
        ],
    ];

    /**
     * @return array<string, array{string, object}> Every part in every language
     */
    public static function partsProvider(): array
    {
        $parts = [];
        foreach (MessagesTest::createAllMessages() as $languageCode => $messages) {
            foreach (MessagesTest::PARTS as $part) {
                $parts[$languageCode . ' ' . $part] = [$languageCode, MessagesTest::readPart(
                    messages: $messages,
                    part: $part,
                )];
            }
        }

        return $parts;
    }

    /**
     * @return array<string, array{string}> The parts by name
     */
    public static function partNamesProvider(): array
    {
        $names = [];
        foreach (MessagesTest::PARTS as $part) {
            $names[$part] = [$part];
        }

        return $names;
    }

    #[DataProvider('partsProvider')]
    public function testEveryTextIsNonEmpty(string $languageCode, object $messages): void
    {
        $emptyTexts = [];
        foreach (MessagesTest::readTexts(messages: $messages) as $name => $text) {
            if (trim(string: $text) === '') {
                $emptyTexts[] = $name;
            }
        }

        $this->assertSame([], $emptyTexts, $languageCode);
    }

    /**
     * Every language has the placeholders of the German text (yuf's defaults are German for some parts).
     */
    #[DataProvider('partNamesProvider')]
    public function testEveryLanguageUsesThePlaceholdersOfTheGermanTexts(string $part): void
    {
        $allMessages = MessagesTest::createAllMessages();
        $germanTexts = MessagesTest::readPartTexts(messages: $allMessages['de'], part: $part);
        $differences = [];
        foreach ($allMessages as $languageCode => $messages) {
            $texts = MessagesTest::readPartTexts(messages: $messages, part: $part);
            foreach ($germanTexts as $name => $germanText) {
                $expected = MessageTemplate::listPlaceholderNames(template: $germanText);
                if (MessageTemplate::listPlaceholderNames(template: $texts[$name] ?? '') !== $expected) {
                    $differences[] = $languageCode . ': ' . $name;
                }
            }
        }

        $this->assertSame([], $differences);
    }

    /**
     * No French or Italian text is left in English or German, except the listed ones.
     */
    #[DataProvider('partNamesProvider')]
    public function testFrenchAndItalianTextsAreTranslated(string $part): void
    {
        $allMessages = MessagesTest::createAllMessages();
        $englishTexts = MessagesTest::readPartTexts(messages: $allMessages['en'], part: $part);
        $germanTexts = MessagesTest::readPartTexts(messages: $allMessages['de'], part: $part);
        $untranslated = [];
        foreach (['fr', 'it'] as $languageCode) {
            $texts = MessagesTest::readPartTexts(messages: $allMessages[$languageCode], part: $part);
            foreach ($texts as $name => $text) {
                $isAllowed = in_array(
                    needle: $part . '.' . $name,
                    haystack: MessagesTest::SAME_AS_ENGLISH[$languageCode],
                    strict: true,
                );
                $sources = [$englishTexts[$name] ?? null, $germanTexts[$name] ?? null];
                if (!$isAllowed && in_array(needle: $text, haystack: $sources, strict: true)) {
                    $untranslated[] = $languageCode . ': ' . $name;
                }
            }
        }

        $this->assertSame([], $untranslated);
    }

    #[DataProvider('partsProvider')]
    public function testMessagesContainNoHtml(string $languageCode, object $messages): void
    {
        $textsWithHtml = [];
        foreach (MessagesTest::readTexts(messages: $messages) as $name => $text) {
            if (preg_match(pattern: '/<[a-z\/!]/i', subject: $text) === 1) {
                $textsWithHtml[] = $name;
            }
        }

        $this->assertSame([], $textsWithHtml, $languageCode);
    }

    /**
     * Swiss usage: formal address ("Sie", "vous", "Lei"), no "ß" in German.
     */
    #[DataProvider('partsProvider')]
    public function testTextsUseTheFormalAddressAndSwissSpelling(string $languageCode, object $messages): void
    {
        $patterns = [
            'de' => '/\bihr(e|en|em|er|es)?\b|\bdu\b|\bdein|ß/u',
            'fr' => '/\b(tu|te|toi|ton|ta|tes)\b/iu',
            'it' => '/\b(tu|ti|tuo|tua|tuoi|tue)\b/iu',
        ];
        if (!array_key_exists(key: $languageCode, array: $patterns)) {
            $this->assertSame('en', $languageCode);

            return;
        }
        $informalTexts = [];
        foreach (MessagesTest::readTexts(messages: $messages) as $name => $text) {
            if (preg_match(pattern: $patterns[$languageCode], subject: $text) === 1) {
                $informalTexts[] = $name;
            }
        }

        $this->assertSame([], $informalTexts);
    }

    public function testGermanVariantUsesGermanFormMessages(): void
    {
        $this->assertEquals(FormMessages::german(), BackendMessages::german()->form);
    }

    public function testEnglishVariantUsesEnglishFormMessages(): void
    {
        $this->assertEquals(new FormMessages(), BackendMessages::english()->form);
    }

    public function testForLanguageCodeReturnsTheIncludedLanguagesAndEnglishForEveryOtherLanguage(): void
    {
        $this->assertEquals(BackendMessages::german(), BackendMessages::forLanguageCode(languageCode: 'de'));
        $this->assertEquals(BackendMessages::english(), BackendMessages::forLanguageCode(languageCode: 'en'));
        $this->assertEquals(BackendMessages::french(), BackendMessages::forLanguageCode(languageCode: 'fr'));
        $this->assertEquals(BackendMessages::italian(), BackendMessages::forLanguageCode(languageCode: 'it'));
        $this->assertEquals(BackendMessages::english(), BackendMessages::forLanguageCode(languageCode: 'rm'));
    }

    /**
     * @return array{en: BackendMessages, de: BackendMessages, fr: BackendMessages, it: BackendMessages} The included
     *     texts by language code
     */
    private static function createAllMessages(): array
    {
        return [
            'en' => BackendMessages::english(),
            'de' => BackendMessages::german(),
            'fr' => BackendMessages::french(),
            'it' => BackendMessages::italian(),
        ];
    }

    private static function readPart(BackendMessages $messages, string $part): object
    {
        $value = new ReflectionObject(object: $messages)->getProperty(name: $part)->getValue(object: $messages);
        if (!is_object(value: $value)) {
            throw new LogicException(message: 'No part ' . $part);
        }

        return $value;
    }

    /**
     * @return array<string, string> The texts of a part by name
     */
    private static function readPartTexts(BackendMessages $messages, string $part): array
    {
        return MessagesTest::readTexts(messages: MessagesTest::readPart(messages: $messages, part: $part));
    }

    /**
     * @return array<string, string> The string properties of a message class by name
     */
    private static function readTexts(object $messages): array
    {
        $texts = [];
        $properties = new ReflectionObject(object: $messages)->getProperties(filter: ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $property) {
            $value = $property->getValue(object: $messages);
            if (is_string(value: $value)) {
                $texts[$property->getName()] = $value;
            }
        }

        return $texts;
    }
}
