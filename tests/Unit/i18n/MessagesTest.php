<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\i18n;

use actra\backend\i18n\AuthMessages;
use actra\backend\i18n\BackendMessages;
use actra\backend\i18n\CommonMessages;
use actra\backend\i18n\EmailMessages;
use actra\backend\i18n\LayoutMessages;
use actra\backend\i18n\LogMessages;
use actra\backend\i18n\MessageTemplate;
use actra\backend\i18n\NotificationMessages;
use actra\backend\i18n\ProfileMessages;
use actra\backend\i18n\UserMessages;
use actra\yuf\form\FormMessages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use ReflectionProperty;

/**
 * Checks every message class of the backend: complete texts in both languages and the same placeholders.
 */
final class MessagesTest extends TestCase
{
    /**
     * @return array<string, array{object, object}> English and German variant per message class
     */
    public static function messagesProvider(): array
    {
        $english = BackendMessages::english();
        $german = BackendMessages::german();

        return [
            CommonMessages::class => [$english->common, $german->common],
            LayoutMessages::class => [$english->layout, $german->layout],
            AuthMessages::class => [$english->auth, $german->auth],
            UserMessages::class => [$english->user, $german->user],
            ProfileMessages::class => [$english->profile, $german->profile],
            NotificationMessages::class => [$english->notification, $german->notification],
            LogMessages::class => [$english->log, $german->log],
            EmailMessages::class => [$english->email, $german->email],
        ];
    }

    #[DataProvider('messagesProvider')]
    public function testEveryTextIsNonEmptyInBothLanguages(object $english, object $german): void
    {
        $emptyTexts = [];
        foreach (['en' => $english, 'de' => $german] as $languageCode => $messages) {
            foreach ($this->readTexts(messages: $messages) as $name => $text) {
                if (trim(string: $text) === '') {
                    $emptyTexts[] = $languageCode . ': ' . $name;
                }
            }
        }

        $this->assertSame([], $emptyTexts);
    }

    #[DataProvider('messagesProvider')]
    public function testGermanTextsUseTheSamePlaceholdersAsTheEnglishTexts(object $english, object $german): void
    {
        $germanTexts = $this->readTexts(messages: $german);
        $differences = [];
        foreach ($this->readTexts(messages: $english) as $name => $englishText) {
            $englishPlaceholders = MessageTemplate::listPlaceholderNames(template: $englishText);
            $germanPlaceholders = MessageTemplate::listPlaceholderNames(template: $germanTexts[$name] ?? '');
            if ($englishPlaceholders !== $germanPlaceholders) {
                $differences[] = $name;
            }
        }

        $this->assertSame([], $differences);
    }

    #[DataProvider('messagesProvider')]
    public function testMessagesContainNoHtml(object $english, object $german): void
    {
        $textsWithHtml = [];
        foreach (['en' => $english, 'de' => $german] as $languageCode => $messages) {
            foreach ($this->readTexts(messages: $messages) as $name => $text) {
                if (preg_match(pattern: '/<[a-z\/!]/i', subject: $text) === 1) {
                    $textsWithHtml[] = $languageCode . ': ' . $name;
                }
            }
        }

        $this->assertSame([], $textsWithHtml);
    }

    public function testGermanVariantUsesGermanFormMessages(): void
    {
        $this->assertEquals(FormMessages::german(), BackendMessages::german()->form);
    }

    public function testEnglishVariantUsesEnglishFormMessages(): void
    {
        $this->assertEquals(new FormMessages(), BackendMessages::english()->form);
    }

    public function testForLanguageCodeReturnsGermanForGermanAndEnglishForEveryOtherLanguage(): void
    {
        $this->assertEquals(BackendMessages::german(), BackendMessages::forLanguageCode(languageCode: 'de'));
        $this->assertEquals(BackendMessages::english(), BackendMessages::forLanguageCode(languageCode: 'en'));
        $this->assertEquals(BackendMessages::english(), BackendMessages::forLanguageCode(languageCode: 'fr'));
    }

    /**
     * @return array<string, string> The string properties of a message class by name
     */
    private function readTexts(object $messages): array
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