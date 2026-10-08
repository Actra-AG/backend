<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\common;

use actra\backend\libs\common\BreadcrumbItem;
use actra\backend\libs\common\BreadcrumbItemCollection;
use PHPUnit\Framework\TestCase;

final class BreadcrumbItemCollectionTest extends TestCase
{
    public function testParentsAreLinksAndTheCurrentPageIsStrong(): void
    {
        $parents = new BreadcrumbItemCollection(
            new BreadcrumbItem(title: 'Event 7', href: 'event-7.html'),
            new BreadcrumbItem(title: 'Subscription #44', href: 'subscription-44.html'),
        );

        $this->assertSame(
            '<p class="breadcrumb"><a href="event-7.html">Event 7</a> › <a href="subscription-44.html">Subscription #44'
            . '</a> › <strong>Confirmation</strong></p>',
            $parents->render(currentTitleHtml: 'Confirmation', separator: ' › '),
        );
    }

    public function testTitleAndLinkAreEscaped(): void
    {
        $parents = new BreadcrumbItemCollection(new BreadcrumbItem(title: 'A & <b>', href: 'a.html?x="1"&y=2'));

        $this->assertSame(
            '<p class="breadcrumb"><a href="a.html?x=&quot;1&quot;&amp;y=2">A &amp; &lt;b&gt;</a> '
            . '<strong>B</strong></p>',
            $parents->render(currentTitleHtml: 'B', separator: ' '),
        );
    }

    public function testNoParentsShowsOnlyTheCurrentPage(): void
    {
        $this->assertSame(
            '<p class="breadcrumb"><strong>B</strong></p>',
            new BreadcrumbItemCollection()->render(currentTitleHtml: 'B', separator: ' '),
        );
    }
}
