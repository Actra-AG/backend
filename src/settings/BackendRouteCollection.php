<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use LogicException;

/**
 * The routes of the backend, one per language. The first route is the main route.
 */
final readonly class BackendRouteCollection
{
    /** @var non-empty-list<BackendRoute> */
    public array $routes;

    /**
     * @param list<BackendRoute> $additionalRoutes
     * @throws LogicException If two routes have the same path or language
     */
    public function __construct(BackendRoute $mainRoute, array $additionalRoutes)
    {
        $routes = [$mainRoute, ...$additionalRoutes];
        $paths = [];
        $languageCodes = [];
        foreach ($routes as $route) {
            if (in_array(needle: $route->path, haystack: $paths, strict: true)) {
                throw new LogicException(message: 'The backend path ' . $route->path . ' is used by two routes.');
            }
            $languageCode = $route->language->code;
            if (in_array(needle: $languageCode, haystack: $languageCodes, strict: true)) {
                throw new LogicException(
                    message: 'The backend has two routes for the language ' . $languageCode
                        . '. Use one route per language.'
                );
            }
            $paths[] = $route->path;
            $languageCodes[] = $languageCode;
        }
        $this->routes = $routes;
    }

    public function getMainRoute(): BackendRoute
    {
        return $this->routes[0];
    }

    public function findByPath(string $path): ?BackendRoute
    {
        return array_find(
            array: $this->routes,
            callback: static fn(BackendRoute $route): bool => $route->path === $path
        );
    }

    public function findByLanguage(string $languageCode): ?BackendRoute
    {
        return array_find(
            array: $this->routes,
            callback: static fn(BackendRoute $route): bool => $route->language->code === $languageCode
        );
    }

    /**
     * The route of a language, the main route for an unknown or missing language.
     */
    public function getForLanguage(?string $languageCode): BackendRoute
    {
        if ($languageCode === null) {
            return $this->getMainRoute();
        }

        return $this->findByLanguage(languageCode: $languageCode) ?? $this->getMainRoute();
    }

    /**
     * Moves a URI path (with query) of one backend route to another route, e.g. '/backend/user-5.html?x' to
     * '/en/backend/user-5.html?x'. A path outside the backend routes is returned unchanged.
     */
    public function translatePath(string $uri, BackendRoute $targetRoute): string
    {
        $sourceRoute = null;
        foreach ($this->routes as $route) {
            $isLongerMatch = $sourceRoute === null || strlen(string: $route->path) > strlen(string: $sourceRoute->path);
            if ($isLongerMatch && str_starts_with(haystack: $uri, needle: $route->path)) {
                $sourceRoute = $route;
            }
        }
        if ($sourceRoute === null) {
            return $uri;
        }

        return $targetRoute->path . substr(string: $uri, offset: strlen(string: $sourceRoute->path));
    }

    /**
     * @return list<string>
     */
    public function listLanguageCodes(): array
    {
        return array_map(
            callback: static fn(BackendRoute $route): string => $route->language->code,
            array: $this->routes
        );
    }
}