<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit;

use actra\backend\settings\BackendNavigation;
use actra\backend\settings\BackendRoute;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\Route;
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
                BackendRoute $backendRoute,
            ): void {
                $navigationItemCollection->addItem(navigationItem: new NavigationItem(
                    navKey: 'calendar',
                    href: $backendRoute->path . 'calendar.html',
                    svgPath: '',
                    title: 'Calendar',
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
}
