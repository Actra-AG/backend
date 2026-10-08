<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\settings;

use actra\backend\i18n\BackendMessages;
use actra\backend\settings\BackendRoute;
use actra\backend\settings\BackendRouteCollection;
use actra\yuf\core\Language;
use LogicException;
use PHPUnit\Framework\TestCase;

final class BackendRouteCollectionTest extends TestCase
{
    private function createRoute(string $path, string $languageCode): BackendRoute
    {
        return new BackendRoute(path: $path, language: new Language(code: $languageCode, locale: $languageCode));
    }

    private function createCollection(): BackendRouteCollection
    {
        return new BackendRouteCollection(
            mainRoute: $this->createRoute(path: '/backend/', languageCode: 'de'),
            additionalRoutes: [
                $this->createRoute(path: '/en/backend/', languageCode: 'en'),
                $this->createRoute(path: '/fr/backend/', languageCode: 'fr'),
            ],
        );
    }

    public function testRouteTextsFollowTheLanguageByDefault(): void
    {
        $this->assertEquals(BackendMessages::german(), $this->createRoute(path: '/b/', languageCode: 'de')->messages);
        $this->assertEquals(BackendMessages::english(), $this->createRoute(path: '/b/', languageCode: 'fr')->messages);
    }

    public function testRouteUsesGivenTexts(): void
    {
        $messages = BackendMessages::english();
        $route = new BackendRoute(
            path: '/b/',
            language: new Language(code: 'de', locale: 'de_CH'),
            messages: $messages,
        );

        $this->assertSame($messages, $route->messages);
    }

    public function testMainRouteIsTheFirstRoute(): void
    {
        $this->assertSame('/backend/', $this->createCollection()->getMainRoute()->path);
        $this->assertSame(['de', 'en', 'fr'], $this->createCollection()->listLanguageCodes());
    }

    public function testFindByPathReturnsTheRouteOrNull(): void
    {
        $collection = $this->createCollection();

        $this->assertSame('en', $collection->findByPath(path: '/en/backend/')?->language->code);
        $this->assertNull($collection->findByPath(path: '/other/'));
    }

    public function testGetForLanguageFallsBackToTheMainRoute(): void
    {
        $collection = $this->createCollection();

        $this->assertSame('/fr/backend/', $collection->getForLanguage(languageCode: 'fr')->path);
        $this->assertSame('/backend/', $collection->getForLanguage(languageCode: 'it')->path);
        $this->assertSame('/backend/', $collection->getForLanguage(languageCode: null)->path);
    }

    public function testTwoRoutesWithTheSamePathAreRejected(): void
    {
        $this->expectException(LogicException::class);

        new BackendRouteCollection(
            mainRoute: $this->createRoute(path: '/backend/', languageCode: 'de'),
            additionalRoutes: [$this->createRoute(path: '/backend/', languageCode: 'en')],
        );
    }

    public function testTwoRoutesWithTheSameLanguageAreRejected(): void
    {
        $this->expectException(LogicException::class);

        new BackendRouteCollection(
            mainRoute: $this->createRoute(path: '/backend/', languageCode: 'de'),
            additionalRoutes: [$this->createRoute(path: '/de/backend/', languageCode: 'de')],
        );
    }

    public function testFindByLanguageReturnsNullForALanguageWithoutRoute(): void
    {
        $this->assertSame('/en/backend/', $this->createCollection()->findByLanguage(languageCode: 'en')?->path);
        $this->assertNull($this->createCollection()->findByLanguage(languageCode: 'it'));
    }

    public function testTranslatePathMovesAPathToTheTargetRoute(): void
    {
        $collection = $this->createCollection();
        $english = $collection->getForLanguage(languageCode: 'en');

        $this->assertSame(
            '/en/backend/user-5.html?changed&fromLogin',
            $collection->translatePath(uri: '/backend/user-5.html?changed&fromLogin', targetRoute: $english),
        );
        $this->assertSame(
            '/backend/users.html',
            $collection->translatePath(uri: '/fr/backend/users.html', targetRoute: $collection->getMainRoute()),
        );
    }

    public function testTranslatePathUsesTheLongestMatchingRoutePath(): void
    {
        $collection = new BackendRouteCollection(
            mainRoute: $this->createRoute(path: '/backend/', languageCode: 'de'),
            additionalRoutes: [$this->createRoute(path: '/backend/en/', languageCode: 'en')],
        );

        $this->assertSame(
            '/backend/users.html',
            $collection->translatePath(uri: '/backend/en/users.html', targetRoute: $collection->getMainRoute()),
        );
    }

    public function testTranslatePathKeepsAPathOutsideTheBackend(): void
    {
        $collection = $this->createCollection();

        $this->assertSame(
            '/shop/cart.html',
            $collection->translatePath(uri: '/shop/cart.html', targetRoute: $collection->getMainRoute()),
        );
    }
}
