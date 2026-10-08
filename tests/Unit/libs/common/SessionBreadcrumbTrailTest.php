<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\common;

use actra\backend\libs\common\SessionBreadcrumbTrail;
use actra\yuf\core\HttpRequest;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\TestCase;

/**
 * Same cases as the characterization of the former OldNavigator (plan step 6), plus the escaped link.
 */
final class SessionBreadcrumbTrailTest extends TestCase
{
    private Session $session;

    #[\Override]
    protected function setUp(): void
    {
        $this->session = new Session(storage: new ArraySessionStorage());
    }

    /**
     * @param list<string> $pathVars
     * @param array<string, string> $query
     * @param array<int, string> $navigationLevels
     */
    private function createTrail(
        array $pathVars,
        array $query = [],
        array $navigationLevels = [],
    ): SessionBreadcrumbTrail {
        return new SessionBreadcrumbTrail(
            session: $this->session,
            httpRequest: new HttpRequest(host: 'example.com', queryParameters: $query),
            pathVars: $pathVars,
            navigationLevels: $navigationLevels,
            separator: ' › ',
        );
    }

    /**
     * @param list<string> $pathVars
     * @param array<string, string> $query
     */
    private function visit(array $pathVars, string $title, array $query = []): string
    {
        $trail = $this->createTrail(pathVars: $pathVars, query: $query);
        $trail->add(titleHtml: $title);

        return $trail->render();
    }

    /**
     * @return list<int|string>
     */
    private function listTrailPages(): array
    {
        return array_keys($this->session->getArray(key: 'sess_breadcrumb') ?? []);
    }

    public function testFirstPageShowsNoBreadcrumb(): void
    {
        $this->assertSame('', $this->visit(pathVars: ['users'], title: 'Users'));
    }

    public function testDetailPageLinksThePreviousPages(): void
    {
        $this->visit(pathVars: ['users'], title: 'Users');

        $this->assertSame(
            '<p class="breadcrumb"><a href="users.html">Users</a> › <strong>User 5</strong></p>',
            $this->visit(pathVars: ['user', '5'], title: 'User 5'),
        );
    }

    public function testGoingBackRemovesTheLaterPages(): void
    {
        $this->visit(pathVars: ['users'], title: 'Users');
        $this->visit(pathVars: ['user', '5'], title: 'User 5');
        $this->visit(pathVars: ['userMod', '5'], title: 'Edit');

        $this->assertSame(
            '<p class="breadcrumb"><a href="users.html">Users</a> › <strong>User 5</strong></p>',
            $this->visit(pathVars: ['user', '5'], title: 'User 5'),
        );
        $this->assertSame(['users', 'user'], $this->listTrailPages());
    }

    public function testDirectJumpBetweenDetailPagesKeepsTheStaleEntry(): void
    {
        $this->visit(pathVars: ['subscription', '42'], title: 'Subscription #42');

        $this->assertSame(
            '<p class="breadcrumb"><a href="subscription-42.html">Subscription #42</a> › '
            . '<strong>Confirmation #44</strong></p>',
            $this->visit(pathVars: ['subscriptionConfirm', '44'], title: 'Confirmation #44'),
        );
    }

    public function testResetParameterClearsTheTrail(): void
    {
        $this->visit(pathVars: ['users'], title: 'Users');

        $this->assertSame('', $this->visit(pathVars: ['user', '5'], title: 'User 5', query: ['reset' => '']));
        $this->assertSame(['user'], $this->listTrailPages());
    }

    public function testResetClearsTheTrail(): void
    {
        $this->visit(pathVars: ['users'], title: 'Users');

        $this->createTrail(pathVars: ['user', '5'])->reset();

        $this->assertFalse($this->session->has(key: 'sess_breadcrumb'));
    }

    public function testNavigationLevelsFromTheQueryAreKept(): void
    {
        $this->createTrail(pathVars: ['orders'], query: ['n' => 'orders|open'], navigationLevels: [0 => 'start'])
            ->listNavigationLevels();

        $levels = $this->createTrail(pathVars: ['order', '3'], navigationLevels: [0 => 'start', 2 => 'x'])
            ->listNavigationLevels();

        $this->assertSame([0 => 'orders', 2 => 'x', 1 => 'open'], $levels);
    }

    public function testLinkIsEscaped(): void
    {
        $this->visit(pathVars: ['page', '"x'], title: 'Page');

        $this->assertStringContainsString(
            '<a href="page-&quot;x.html">Page</a>',
            $this->visit(pathVars: ['other'], title: 'Other'),
        );
    }
}
