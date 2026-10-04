<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\common;

use actra\backend\libs\common\UserLanguageOptions;
use actra\backend\settings\BackendRoute;
use actra\backend\settings\BackendRouteCollection;
use actra\yuf\core\Language;
use PHPUnit\Framework\TestCase;

final class UserLanguageOptionsTest extends TestCase
{
    /**
     * @param list<string> $additionalLanguageCodes
     */
    private function createOptions(
        array $additionalLanguageCodes,
        string $displayLocale = 'de_CH'
    ): UserLanguageOptions {
        $additionalRoutes = array_map(
            callback: static fn(string $languageCode): BackendRoute => new BackendRoute(
                path: '/' . $languageCode . '/backend/',
                language: new Language(code: $languageCode, locale: $languageCode)
            ),
            array: $additionalLanguageCodes
        );

        return new UserLanguageOptions(
            backendRouteCollection: new BackendRouteCollection(
                mainRoute: new BackendRoute(
                    path: '/backend/',
                    language: new Language(code: 'de', locale: 'de_CH')
                ),
                additionalRoutes: $additionalRoutes
            ),
            displayLocale: $displayLocale,
            defaultTemplate: 'Standard ([language])'
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

    public function testFormOptionsContainTheLanguageOfEveryRoute(): void
    {
        $formOptions = $this->createOptions(additionalLanguageCodes: ['en', 'fr'])->createFormOptions();

        $this->assertSame(['de', 'en', 'fr'], array_map(strval(...), array_keys($formOptions->data)));
        $this->assertSame('Französisch', $formOptions->data['fr']->render());
    }

    public function testDefaultLabelNamesTheLanguageOfTheMainRoute(): void
    {
        $this->assertSame(
            'Standard (Deutsch)',
            $this->createOptions(additionalLanguageCodes: ['en'])->getDefaultLabel()
        );
    }

    public function testRenderReturnsDefaultLabelWithoutLanguage(): void
    {
        $options = $this->createOptions(additionalLanguageCodes: ['en']);

        $this->assertSame('Standard (Deutsch)', $options->render(languageCode: null));
        $this->assertSame('Englisch', $options->render(languageCode: 'en'));
    }
}