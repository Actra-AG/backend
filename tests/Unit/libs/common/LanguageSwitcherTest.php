<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\common;

use actra\backend\libs\common\LanguageSwitcher;
use actra\backend\libs\common\LanguageSwitcherEntry;
use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use PHPUnit\Framework\TestCase;

final class LanguageSwitcherTest extends TestCase
{
    /**
     * @param list<string> $languageCodes
     */
    private function createSwitcher(
        array $languageCodes,
        string $currentUri,
        string $currentLanguageCode = 'de',
        string $switchPath = '/backend/language.html',
    ): LanguageSwitcher {
        return new LanguageSwitcher(
            languages: new LanguageCollection(languages: array_map(
                callback: static fn(string $code): Language => new Language(code: $code, locale: $code),
                array: $languageCodes,
            )),
            currentLanguage: new Language(code: $currentLanguageCode, locale: $currentLanguageCode),
            switchPath: $switchPath,
            currentUri: $currentUri,
        );
    }

    public function testIsNotAvailableWithOneLanguage(): void
    {
        $switcher = $this->createSwitcher(languageCodes: ['de'], currentUri: '/backend/');

        $this->assertFalse($switcher->isAvailable());
    }

    public function testEntriesSwitchTheLanguageAndLeadBackToThePage(): void
    {
        $switcher = $this->createSwitcher(languageCodes: ['de', 'en'], currentUri: '/backend/user-5.html');

        $entries = $switcher->createEntries();

        $this->assertTrue($switcher->isAvailable());
        $this->assertSame(
            [
                ['de', 'Deutsch', '/backend/language.html?language=de&returnTo=%2Fbackend%2Fuser-5.html', true],
                ['en', 'English', '/backend/language.html?language=en&returnTo=%2Fbackend%2Fuser-5.html', false],
            ],
            $this->describeEntries(entries: $entries),
        );
    }

    public function testQueryIsNotCarriedOver(): void
    {
        $switcher = $this->createSwitcher(
            languageCodes: ['de', 'en'],
            currentUri: '/backend/profile.html?generateApiKey=1',
            currentLanguageCode: 'en',
        );

        $entries = $switcher->createEntries();

        $this->assertSame(
            [
                ['de', 'Deutsch', '/backend/language.html?language=de&returnTo=%2Fbackend%2Fprofile.html', false],
                ['en', 'English', '/backend/language.html?language=en&returnTo=%2Fbackend%2Fprofile.html', true],
            ],
            $this->describeEntries(entries: $entries),
        );
    }

    public function testSwitchPathWithCsrfTokenKeepsItsQuery(): void
    {
        $switcher = $this->createSwitcher(
            languageCodes: ['de', 'en'],
            currentUri: '/backend/users.html',
            switchPath: '/backend/language.html?csrf=token',
        );

        $this->assertSame(
            '/backend/language.html?csrf=token&language=en&returnTo=%2Fbackend%2Fusers.html',
            array_last(array: $switcher->createEntries())?->href,
        );
    }

    public function testRenderCreatesOneDataObjectPerEntryWithEncodedValues(): void
    {
        $switcher = $this->createSwitcher(languageCodes: ['de', 'fr'], currentUri: '/backend/?a=1');

        $data = $switcher->render()->items;

        $this->assertCount(2, $data);
        $french = $data[1]->toTemplateData();
        $this->assertSame('Français', $french->label);
        $this->assertSame('/backend/language.html?language=fr&amp;returnTo=%2Fbackend%2F', $french->href);
        $this->assertFalse($french->isCurrent);
    }

    /**
     * @param list<LanguageSwitcherEntry> $entries
     *
     * @return list<array{string, string, string, bool}>
     */
    private function describeEntries(array $entries): array
    {
        return array_map(
            callback: static fn(LanguageSwitcherEntry $entry): array => [
                $entry->languageCode,
                $entry->label,
                $entry->href,
                $entry->isCurrent,
            ],
            array: $entries,
        );
    }
}
