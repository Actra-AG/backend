<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\common;

use actra\backend\libs\common\LanguageSwitcher;
use actra\backend\libs\common\LanguageSwitcherEntry;
use actra\backend\settings\BackendRoute;
use actra\backend\settings\BackendRouteCollection;
use actra\yuf\core\Language;
use PHPUnit\Framework\TestCase;

final class LanguageSwitcherTest extends TestCase
{
    private function createRoute(string $path, string $languageCode): BackendRoute
    {
        return new BackendRoute(path: $path, language: new Language(code: $languageCode, locale: $languageCode));
    }

    /**
     * @param list<BackendRoute> $additionalRoutes
     */
    private function createSwitcher(
        string $currentUri,
        array $additionalRoutes,
        int $currentIndex = 0,
    ): LanguageSwitcher {
        $mainRoute = $this->createRoute(path: '/backend/', languageCode: 'de');
        $collection = new BackendRouteCollection(mainRoute: $mainRoute, additionalRoutes: $additionalRoutes);

        return new LanguageSwitcher(
            backendRouteCollection: $collection,
            currentRoute: $collection->routes[$currentIndex]
                ?? LanguageSwitcherTest::fail('No route at index ' . $currentIndex),
            currentUri: $currentUri,
        );
    }

    public function testIsNotAvailableWithOneRoute(): void
    {
        $switcher = $this->createSwitcher(currentUri: '/backend/', additionalRoutes: []);

        $this->assertFalse($switcher->isAvailable());
    }

    public function testEntriesLinkTheSamePageUnderEveryRoute(): void
    {
        $switcher = $this->createSwitcher(
            currentUri: '/backend/user-5.html',
            additionalRoutes: [$this->createRoute(path: '/en/backend/', languageCode: 'en')],
        );

        $entries = $switcher->createEntries();

        $this->assertTrue($switcher->isAvailable());
        $this->assertSame(
            [
                ['de', 'Deutsch', '/backend/user-5.html', true],
                ['en', 'English', '/en/backend/user-5.html', false],
            ],
            $this->describeEntries(entries: $entries),
        );
    }

    public function testQueryIsNotCarriedOver(): void
    {
        $switcher = $this->createSwitcher(
            currentUri: '/en/backend/profile.html?generateApiKey=1',
            additionalRoutes: [$this->createRoute(path: '/en/backend/', languageCode: 'en')],
            currentIndex: 1,
        );

        $entries = $switcher->createEntries();

        $this->assertSame(
            [
                ['de', 'Deutsch', '/backend/profile.html', false],
                ['en', 'English', '/en/backend/profile.html', true],
            ],
            $this->describeEntries(entries: $entries),
        );
    }

    public function testRenderCreatesOneDataObjectPerEntryWithEncodedValues(): void
    {
        $switcher = $this->createSwitcher(
            currentUri: '/backend/?a=1',
            additionalRoutes: [$this->createRoute(path: '/fr/backend/', languageCode: 'fr')],
        );

        $data = $switcher->render()->items;

        $this->assertCount(2, $data);
        $french = $data[1] ?? LanguageSwitcherTest::fail('No data object for the second entry');
        $this->assertSame('Français', $french->data->label);
        $this->assertSame('/fr/backend/', $french->data->href);
        $this->assertFalse($french->data->isCurrent);
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
