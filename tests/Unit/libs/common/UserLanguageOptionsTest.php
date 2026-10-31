<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\common;

use actra\backend\libs\common\UserLanguageOptions;
use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\form\FormOption;
use PHPUnit\Framework\TestCase;

final class UserLanguageOptionsTest extends TestCase
{
    /**
     * @param list<string> $additionalLanguageCodes The languages after German
     */
    private function createOptions(
        array $additionalLanguageCodes,
        string $displayLocale = 'de_CH',
    ): UserLanguageOptions {
        return new UserLanguageOptions(
            languages: new LanguageCollection(languages: array_map(
                callback: static fn(string $languageCode): Language => new Language(
                    code: $languageCode,
                    locale: $languageCode,
                ),
                array: ['de', ...$additionalLanguageCodes],
            )),
            displayLocale: $displayLocale,
            defaultLabel: 'Automatisch',
        );
    }

    public function testSingleLanguageIsNotSelectable(): void
    {
        $this->assertFalse($this->createOptions(additionalLanguageCodes: [])->isSelectable());
    }

    public function testSeveralLanguagesAreSelectable(): void
    {
        $this->assertTrue($this->createOptions(additionalLanguageCodes: ['en'])->isSelectable());
    }

    public function testDisplayNameIsWrittenInTheDisplayLocaleWithUpperCaseFirstLetter(): void
    {
        $options = $this->createOptions(additionalLanguageCodes: ['en']);

        $this->assertSame('Englisch', $options->getDisplayName(languageCode: 'en'));
        $this->assertSame('Deutsch', $options->getDisplayName(languageCode: 'de'));
    }

    public function testDisplayNameFollowsTheDisplayLocale(): void
    {
        $options = $this->createOptions(additionalLanguageCodes: ['fr'], displayLocale: 'fr');

        $this->assertSame('Allemand', $options->getDisplayName(languageCode: 'de'));
    }

    public function testFormOptionsContainEveryLanguage(): void
    {
        $formOptions = $this->createOptions(additionalLanguageCodes: ['en', 'fr'])->createFormOptions();

        $this->assertSame(['de', 'en', 'fr'], $formOptions->getKeys());
        $french = array_find(
            array: $formOptions->getItems(),
            callback: static fn(FormOption $formOption): bool => $formOption->key === 'fr',
        ) ?? UserLanguageOptionsTest::fail('No option fr');
        $this->assertSame('Französisch', $french->htmlText->render());
    }

    public function testRenderReturnsDefaultLabelWithoutLanguage(): void
    {
        $options = $this->createOptions(additionalLanguageCodes: ['en']);

        $this->assertSame('Automatisch', $options->render(languageCode: null));
        $this->assertSame('Englisch', $options->render(languageCode: 'en'));
    }
}
