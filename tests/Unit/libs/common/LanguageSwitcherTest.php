<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\common;

use actra\backend\libs\common\LanguageSwitcher;
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
    private function createSwitcher(string $currentUri, array $additionalRoutes, int $currentIndex = 0): LanguageSwitcher
    {
        $mainRoute = $this->createRoute(path: '/backend/', languageCode: 'de');
        $collection = new BackendRouteCollection(mainRoute: $mainRoute, additionalRoutes: $additionalRoutes);

        return new LanguageSwitcher(
            backendRouteCollection: $collection,
            currentRoute: $collection->routes[$currentIndex],
            currentUri: $currentUri
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
            additionalRoutes: [$this->createRoute(path: '/en/backend/', languageCode: 'en')]
        );

        $entries = $switcher->createEntries();

        $this->assertTrue($switcher->isAvailable());
        $this->assertCount(2, $entries);
        $this->assertSame('de', $entries[0]->languageCode);
        $this->assertSame('Deutsch', $entries[0]->label);
        $this->assertSame('/backend/user-5.html', $entries[0]->href);
        $this->assertTrue($entries[0]->isCurrent);
        $this->assertSame('en', $entries[1]->languageCode);
        $this->assertSame('English', $entries[1]->label);
        $this->assertSame('/en/backend/user-5.html', $entries[1]->href);
        $this->assertFalse($entries[1]->isCurrent);
    }

    public function testQueryIsNotCarriedOver(): void
    {
        $switcher = $this->createSwitcher(
            currentUri: '/en/backend/profile.html?generateApiKey=1',
            additionalRoutes: [$this->createRoute(path: '/en/backend/', languageCode: 'en')],
            currentIndex: 1
        );

        $entries = $switcher->createEntries();

        $this->assertSame('/backend/profile.html', $entries[0]->href);
        $this->assertFalse($entries[0]->isCurrent);
        $this->assertSame('/en/backend/profile.html', $entries[1]->href);
        $this->assertTrue($entries[1]->isCurrent);
    }

    public function testRenderCreatesOneDataObjectPerEntryWithEncodedValues(): void
    {
        $switcher = $this->createSwitcher(
            currentUri: '/backend/?a=1',
            additionalRoutes: [$this->createRoute(path: '/fr/backend/', languageCode: 'fr')]
        );

        $data = $switcher->render()->items;

        $this->assertCount(2, $data);
        $this->assertSame('Français', $data[1]->data->label);
        $this->assertSame('/fr/backend/', $data[1]->data->href);
        $this->assertFalse($data[1]->data->isCurrent);
    }
}