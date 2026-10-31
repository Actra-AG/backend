<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit;

use actra\backend\settings\BackendNavigation;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\Route;
use actra\yuf\html\HtmlText;
use actra\yuf\layout\NavigationItem;
use actra\yuf\layout\NavigationItemCollection;
use PHPUnit\Framework\TestCase;

final class NavigationTest extends TestCase
{
    public function testProjectItemsComeBeforeTheUsersItem(): void
    {
        $projectNavigation = new class implements BackendNavigation {
            #[\Override]
            public function addNavigationItems(
                NavigationItemCollection $navigationItemCollection,
                Language $language,
            ): void {
                $navigationItemCollection->addItem(navigationItem: new NavigationItem(
                    navKey: 'calendar',
                    href: '/backend/calendar.html',
                    svgPath: '',
                    title: HtmlText::fromText(text: $language->code === 'de' ? 'Kalender' : 'Calendar'),
                    requiredAccessRights: AccessRightCollection::createEmpty(),
                ));
            }
        };
        $actraBackend = ActraBackendTestInstance::create(projectNavigation: $projectNavigation);

        $navigation = $actraBackend->createNavigation(
            viewContext: ViewContextFactory::create(
                route: new Route(path: '/backend/', viewDirectory: __DIR__),
                fileTitle: 'users',
            ),
        );

        $rights = AccessRightCollection::createFromStringArray(input: ['backend_access', 'manage_users']);
        $this->assertSame('calendar', $navigation->getFirst(accessRightCollection: $rights)?->navKey);
        $this->assertTrue($navigation->has(navKey: 'users'));
    }

    public function testNavigationFollowsTheLanguageOfTheRequest(): void
    {
        $german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $english = new Language(code: 'en', locale: 'en_GB.UTF-8');
        $languages = new LanguageCollection(languages: [$german, $english]);
        $actraBackend = ActraBackendTestInstance::create(languages: $languages);
        $route = new Route(path: '/backend/', viewDirectory: __DIR__, usesRequestLanguage: true);

        $navigation = static fn(Language $language): NavigationItemCollection => $actraBackend->createNavigation(
            viewContext: ViewContextFactory::create(
                route: $route,
                fileTitle: 'users',
                locale: new LocaleHandler(language: $language, availableLanguages: $languages),
            ),
        );

        $rights = AccessRightCollection::createFromStringArray(input: ['backend_access', 'manage_users']);
        $this->assertSame('Benutzer', $navigation($german)->getFirst(accessRightCollection: $rights)?->title->render());
        $this->assertSame('Users', $navigation($english)->getFirst(accessRightCollection: $rights)?->title->render());
    }
}
