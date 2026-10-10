<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\common;

use actra\yuf\core\HttpRequest;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\session\Session;

/**
 * The breadcrumb of views with `useNavigator: true` that declare no parents: the pages visited in this order, kept in
 * the session (one entry per page name, a page visited again removes the pages after it). `?reset` restarts the
 * trail, `?n=a|b` sets the active navigation levels for the following pages.
 *
 * @internal
 */
final class SessionBreadcrumbTrail
{
    private const string TRAIL_KEY = 'sess_breadcrumb';
    private const string NAVIGATION_LEVELS_KEY = 'sess_navistufe';

    private readonly string $currentPage;
    private readonly string $currentLink;

    /**
     * @param list<string> $pathVars The path variables of the request (`user-5.html` → `['user', '5']`)
     * @param array<int, string> $navigationLevels The active navigation levels of the view
     */
    public function __construct(
        private readonly Session $session,
        private readonly HttpRequest $httpRequest,
        array $pathVars,
        private readonly array $navigationLevels,
        private readonly string $separator = ' ',
    ) {
        $this->currentPage = array_key_exists(key: 0, array: $pathVars) ? $pathVars[0] : '';
        $this->currentLink = implode(separator: '-', array: $pathVars);
        if ($httpRequest->hasQueryValue(name: 'reset')) {
            $this->reset();
        }
    }

    public function reset(): void
    {
        $this->session->remove(key: SessionBreadcrumbTrail::TRAIL_KEY);
    }

    /**
     * Adds the current page (or updates its title and link) at its place in the trail.
     */
    public function add(string $titleHtml): void
    {
        $trail = $this->readTrail();
        $trail[$this->currentPage] = ['title' => $titleHtml, 'link' => $this->currentLink];
        $this->session->set(key: SessionBreadcrumbTrail::TRAIL_KEY, value: $trail);
    }

    /**
     * The trail up to the current page as HTML, empty with less than two pages. Removes the pages after the current
     * one.
     */
    public function render(): string
    {
        $trail = $this->readTrail();
        $parts = [];
        $isCurrentPageFound = false;
        $hasRemovedPages = false;
        foreach ($trail as $page => $entry) {
            if ($page === $this->currentPage) {
                $parts[] = '<strong>' . $entry['title'] . '</strong>';
                $isCurrentPageFound = true;
                continue;
            }
            if ($isCurrentPageFound) {
                unset($trail[$page]);
                $hasRemovedPages = true;
                continue;
            }
            $parts[] = '<a href="' . HtmlEncoder::encode(value: $entry['link'] . '.html') . '">'
                . $entry['title'] . '</a>';
        }
        if ($hasRemovedPages) {
            $this->session->set(key: SessionBreadcrumbTrail::TRAIL_KEY, value: $trail);
        }
        if (count(value: $trail) <= 1) {
            return '';
        }

        return '<p class="breadcrumb">' . implode(separator: $this->separator, array: $parts) . '</p>';
    }

    /**
     * The navigation levels of the view, overwritten by the levels kept from the last `?n=` parameter.
     *
     * @return array<int, string>
     */
    public function listNavigationLevels(): array
    {
        $queryLevels = $this->httpRequest->getQueryString(name: 'n');
        if ($queryLevels !== null) {
            $this->session->set(
                key: SessionBreadcrumbTrail::NAVIGATION_LEVELS_KEY,
                value: explode(separator: '|', string: $queryLevels),
            );
        }
        $navigationLevels = $this->navigationLevels;
        $storedLevels = $this->session->getStringList(key: SessionBreadcrumbTrail::NAVIGATION_LEVELS_KEY) ?? [];
        foreach ($storedLevels as $level => $id) {
            $navigationLevels[$level] = $id;
        }

        return $navigationLevels;
    }

    /**
     * @return array<string, array{title: string, link: string}>
     */
    private function readTrail(): array
    {
        return $this->session->getStruct(
            key: SessionBreadcrumbTrail::TRAIL_KEY,
            map: SessionBreadcrumbTrail::trailFromSessionArray(...),
        ) ?? [];
    }

    /**
     * The entries of the stored trail that have a title and a link.
     *
     * @param array<array-key, mixed> $stored
     *
     * @return array<string, array{title: string, link: string}>
     */
    private static function trailFromSessionArray(array $stored): array
    {
        $trail = [];
        foreach ($stored as $page => $entry) {
            if (
                is_array(value: $entry)
                && array_key_exists(key: 'title', array: $entry)
                && array_key_exists(key: 'link', array: $entry)
                && is_string(value: $entry['title'])
                && is_string(value: $entry['link'])
            ) {
                $trail[(string) $page] = ['title' => $entry['title'], 'link' => $entry['link']];
            }
        }

        return $trail;
    }
}
