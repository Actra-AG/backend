# Languages

The backend has one path for all languages (yuf's `usesRequestLanguage`, see yuf's
[docs/setup.md](https://github.com/Actra-AG/yuf/blob/main/docs/setup.md#languages)): `/backend/users.html` is the
same page in every language. Its languages are those of the `App` (`withLanguage()`); the first one is the main
language. The language of each request is the first available one of:

1. the profile language of the logged-in user (`auth_users.language`; during an impersonation that of the
   impersonating user),
2. the language chosen with the language switcher (stored in the session),
3. the browser language (`Accept-Language`),
4. the first language of the `App`.

```php
$app = App::fromEnvironment(/* … */)
    ->withLanguage(code: 'de', locale: 'de_CH.UTF-8')
    ->withLanguage(code: 'en', locale: 'en_GB.UTF-8');
$actraBackend = ActraBackend::create(
    path: '/backend/',
    languages: $app->core->availableLanguages, // App adds its languages when it runs
    actraBackendSettings: new ActraBackendSettings(/* … */),
    /* … */
);
$actraBackend->configure(app: $app)->run();
```

The project views of the backend route have the same language ([views.md](views.md)); their language files
(`app/view/backend/language/<code>/`) keep working. `configure()` registers the profile language as yuf's
`RequestLanguageProvider` (`createRequestLanguageProvider()`), so further project routes with
`usesRequestLanguage: true` (e.g. a JSON route) get the same language as the backend. Routes with a fixed language
(e.g. a frontend at `/de/`, `/fr/`) are not affected.

With `Core`, pass `languages: $core->availableLanguages` and
`requestLanguageProvider: $actraBackend->createRequestLanguageProvider()` to `Core::prepareHttpResponse()`.

## Texts

A language gets German texts for `de` and English texts for every other language. To change single texts, pass own
messages per language code (`ActraBackendSettings::$messages`):

```php
use actra\backend\i18n\AuthMessages;
use actra\backend\i18n\BackendMessages;

new ActraBackendSettings(
    /* … */
    messages: [
        'de' => BackendMessages::german()->with(auth: AuthMessages::german()->with(loginPageTitle: 'Login')),
    ],
);
```

Views read the texts, the `Language` and the `DateFormatter` of the request from `BackendViewContext` (`messages`,
`language`, `dateFormatter`); text for another user (e.g. an email) comes from `$actraBackend->getMessages()` with the
user's language. The welcome email uses the language of the user (without one: the first language), its link the path of the
backend.

## Language switcher and profile

With several languages, the page header shows a language switcher, also on the login page. Its links lead to
`/backend/language.html` (yuf's `LanguageSwitch` parameters `language` and `returnTo`), which sets the language of the
session and returns to the page. For a logged-in user it also saves the language in the profile (the link carries the
CSRF token of the session), because the profile language wins over the session.

In the user forms and the profile, every user can choose a language or "Automatic" (no profile language: the session
or the browser decides). After the login the user stays on the requested page; it is shown in the profile language.

## Navigation of the project

Navigation items of the project follow the language of the request if they are added by a `BackendNavigation`:

```php
use actra\backend\settings\BackendNavigation;
use actra\yuf\core\Language;

final class ProjectNavigation implements BackendNavigation
{
    public function addNavigationItems(NavigationItemCollection $navigationItemCollection, Language $language): void
    {
        $navigationItemCollection->addItem(navigationItem: new NavigationItem(
            navKey: 'orders',
            href: '/backend/orders.html', // a project view of the backend route
            svgPath: '...',
            title: HtmlText::fromText(text: $language->code === 'de' ? 'Bestellungen' : 'Orders'),
            requiredAccessRights: AccessRightCollection::createEmpty()
        ));
    }
}

new ActraBackendSettings(
    /* … */
    projectNavigation: new ProjectNavigation()
);
```

`ActraBackend::createNavigation()` (the backend navigation of `configure()` and `createNavigationProvider()`) calls
the hook once per request; each navigation key may be added once.
