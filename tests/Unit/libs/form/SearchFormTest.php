<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\form;

use actra\backend\libs\form\TokenSearchForm;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\core\Route;
use PHPUnit\Framework\TestCase;

final class SearchFormTest extends TestCase
{
    /**
     * Every label of a search form points to the id of its control (B-4: it pointed to the field name).
     */
    public function testLabelsPointToTheIdsOfTheControls(): void
    {
        $context = ActraBackendTestInstance::create()->createContext(
            viewContext: ViewContextFactory::create(
                route: new Route(path: '/backend/', viewDirectory: __DIR__),
                fileTitle: 'tokens',
            ),
        );

        $html = new TokenSearchForm(context: $context, name: 'TokenSearch')->render();

        preg_match_all(pattern: '/<label for="([^"]+)"/', subject: $html, matches: $labels);
        preg_match_all(pattern: '/<(?:input|select)[^>]* id="([^"]+)"/', subject: $html, matches: $controls);
        $this->assertCount(2, $labels[1]);
        $this->assertSame([], array_diff($labels[1], $controls[1]));
        $this->assertSame(2, substr_count(haystack: $html, needle: '<div class="form-compact-field">'));
    }
}
