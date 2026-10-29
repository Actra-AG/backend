# Languages

All texts of the backend come from message classes; English and German are included. The texts of a request follow
the language of its route: the main route (`path` of `ActraBackend::create()`) uses `ActraBackendSettings::$language`,
further languages get their own route. Every language is a language of the `App` (`withLanguage()`), and the backend
gets its `Language` object (`App::withRoute()` throws for another object with the same code):

```php
use actra\backend\settings\BackendRoute;

$app = App::fromEnvironment(/* … */)
    ->withLanguage(code: 'en', locale: 'en_GB')
    ->withLanguage(code: 'de', locale: 'de_CH');
$actraBackend = ActraBackend::create(
    path: '/backend/',
    isDefaultForLanguage: false,
    actraBackendSettings: new ActraBackendSettings(
        language: $app->getLanguage(code: 'en'), // English texts under /backend/
        ...
        additionalRoutes: [
            // German texts under /de/backend/
            new BackendRoute(path: '/de/backend/', language: $app->getLanguage(code: 'de')),
        ]
    ),
    ...
);
$actraBackend->configure(app: $app)->run();
```

With `Core`, the `Language` objects are those of `Core::$availableLanguages`.

A route gets German texts for `de` and English texts for every other language. To change single texts, pass own
messages (`ActraBackendSettings::$messages` for the main route, `messages` of a `BackendRoute`):

```php
use actra\backend\i18n\AuthMessages;
use actra\backend\i18n\BackendMessages;

messages: BackendMessages::german()->with(
    auth: AuthMessages::german()->with(loginPageTitle: 'Login')
)
```

The languages of the routes must be available languages of the yuf application if a route is the default route of
its language (`isDefaultForLanguage`).

With several languages, every user can choose a language (user forms, profile). After login the user continues on
the backend route of that language, and the welcome email uses it (text and link). Users without a language stay on
the route they logged in with; their welcome email uses the main route.

Navigation items of the project follow the route language if they are added by a `BackendNavigation`:

```php
use actra\backend\settings\BackendNavigation;
use actra\backend\settings\BackendRoute;

final class ProjectNavigation implements BackendNavigation
{
    public function addNavigationItems(
        NavigationItemCollection $navigationItemCollection,
        BackendRoute $backendRoute
    ): void {
        $navigationItemCollection->addItem(navigationItem: new NavigationItem(
            navKey: 'orders',
            href: '/' . $backendRoute->language->code . '/orders/', // the project's route of that language
            svgPath: '...',
            title: $backendRoute->language->code === 'de' ? 'Bestellungen' : 'Orders',
            requiredAccessRights: AccessRightCollection::createEmpty()
        ));
    }
}

new ActraBackendSettings(
    ...
    projectNavigation: new ProjectNavigation()
);
```

`ActraBackend::createNavigation()` (the backend navigation of `configure()` and `createNavigationProvider()`) calls
the hook once per request, for the backend route of the request; each
navigation key may be added once.

With several languages, the page header shows a language switcher that links to the same page in each language.
