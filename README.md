# Actra Backend

A comprehensive backend management library for the [YUF framework](https://github.com/Actra-AG/yuf). This library
provides a ready-to-use administrative interface with user account management and secure authentication using optional
password login, and one-time tokens sent via email.

## Features

- **Password Login**: Authenticate backend users with their email address and password.
- **Password Reset**: Allow users to request a reset link and set a new password.
- **One-Time Token Authentication**: Secure login confirmation and token-based authentication using tokens sent to the
  user's email.
- **User Management**: Add, modify, and invite users to the system, including phone number support.
- **User Profile Management**: Logged-in users can update their own profile details, IP whitelist, and API key.
- **API Key Management**: Generate hashed API keys for users and validate bearer tokens for API access.
- **User Notifications**: Send email notifications to specific user groups directly from the backend.
- **Role-Based Access Control**: Basic functionality to manage user permissions.
- **IP Whitelisting**: Additional security layer to restrict backend access.
- **Activity Monitoring**: Track visits and token usage.
- **Responsive UI**: Built-in HTML templates and frontend assets for common backend tasks.

## Requirements

- PHP >= 8.5
- `actra/yuf` framework in the version range of `composer.json`. [UPGRADE.md](UPGRADE.md) names every raise of the
  yuf requirement; projects migrate their own code with yuf's
  [UPGRADE.md](https://github.com/Actra-AG/yuf/blob/main/UPGRADE.md).

## Installation

### 1. Composer

Add the library to your project via Composer:

```bash
composer require actra/backend
```

### 2. Assets

The package ships default assets in `src/assets`.

Projects using this library should include these assets in their own build or asset publishing process. Depending on the
project setup, this can mean importing them into a npm, Grunt, or other asset pipeline, bundling and minifying them
together with project-specific assets, or publishing them directly as static files.

The main entrypoints are:

- `src/assets/css/backend.css`
- `src/assets/js/backend.js`

The default CSS expects the bundled backend fonts to be available below the public font path:

- `/fonts/backend/`

For example, when publishing the package assets directly, publish the backend font files so that
`/fonts/backend/inter-v18-latin-regular.woff2`, `/fonts/backend/inter-v18-latin-italic.woff2`, and the used bold weights
are reachable by the browser.

Messages use the `msg` block (`src/assets/css/blocks/_msg.css`) with one variant: `msg-success`, `msg-note`,
`msg-warning` or `msg-error`. Use `role="status"` for success and notes, `role="alert"` for errors that need
attention:

```html
<p class="msg msg-error" role="alert"><strong>Error:</strong> This event has subscriptions and cannot be deleted.</p>
<p class="msg msg-warning" role="status"><strong>Warning:</strong> The event is fully booked.</p>
```

After changing the version of `actra/backend`, rebuild (or republish) the CSS and JavaScript bundles of the project.

After the assets are available through the application's public asset URLs, reference them when initializing the
backend:

```php
ActraBackend::init();
```

### 3. Database Setup

The library requires several database tables to function. For new installations, import the provided SQL files into your
database:

1. Import `db/schema.sql` to create the required table structure.
2. Import `db/data.sql` to populate the tables with initial data, including a test user account.

For existing installations, apply the incremental SQL update files from `db/updates/` as documented
in [UPGRADE.md](UPGRADE.md).

## Usage

To integrate the backend into your YUF-based application, you need to call `ActraBackend::init()` during your
application's bootstrap process.

### Basic Initialization

```php
use actra\backend\ActraBackend;
use actra\backend\settings\ActraBackendSettings;
use actra\backend\settings\MailerSettings;
use actra\yuf\db\DbSettings;

// ... initialize your $routeCollection, $language, $navigationItemCollection ...

ActraBackend::init(
    routeCollection: $routeCollection,
    path: '/backend/', // The URL path where the backend will be accessible
    isDefaultForLanguage: false,
    actraBackendSettings: new ActraBackendSettings(
        language: $language,
        ipWhitelist: ['127.0.0.1'], // Allowed IP addresses
        backendName: 'My Project Backend',
        javaScriptPaths: [
            '/assets/js/backend.js'
        ],
        stylesPaths: [
            '/assets/css/backend.css',
        ],
        maxAllowedLoginAttempts: 5, // Optional, defaults to 5
        frontendHref: 'https://example.com', // Optional
        frontendName: 'Go to Website', // Optional
        hasApi: false // Optional, defaults to false
    ),
    dbSettings: new DbSettings,
    mailerSettings: new MailerSettings(
    senderEmail: 'noreply@example.com',
        senderName: 'My Project',
        hostname: 'smtp.example.com',
        username: 'mailer@example.com',
        password: 'smtp_password',
        port: 587,
        tls: true, // certificate and host name are verified; false only for a local mail catcher
        signature: 'Best regards, Your Team'
    ),
    navigationItemCollection: $navigationItemCollection
);
```

Once initialized, the library automatically registers the necessary routes under the specified path (e.g., `/backend/`)
and adds navigation items to your `NavigationItemCollection`.

### Project Views, Tables and Search Forms

Project views based on `BackendView` receive a `BackendViewContext`: the `ViewContext` of yuf
(`$this->context`) and the services of the backend (`$this->backendContext`). The routes of these views use the view
factory of the backend; other views on the same route keep getting the yuf `ViewContext`:

```php
use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\BackendViewContext;

new Route(
    path: '/de/orders/',
    viewDirectory: $core->viewDirectory,
    viewGroup: 'orders',
    viewFactory: ActraBackend::get()->createViewFactory(), // after ActraBackend::init()
);

final class orders extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(context: $context, requiredViewGroupName: 'orders');
    }
}
```

Tables based on `AbstractTable` and search forms based on `AbstractSearchForm` get the same context as first
argument; the database of a table defaults to `DB::get()`:

```php
new OrderTable(context: $this->backendContext);
new OrderSearchForm(context: $this->backendContext, name: 'OrderSearch');

// in OrderTable::__construct(BackendViewContext $context)
parent::__construct(context: $context, identifier: 'OrderTable', dbQuery: $dbQuery);
```

The values of a search form come from the posted form (`reset` and `find` from the query string), and tables and
search forms keep their state in the session. New services of the backend are added to `BackendViewContext`, so these
constructors do not change again.

### Breadcrumb

With `useNavigator: true`, a view shows the pages visited before it as breadcrumb (kept in the session; `?reset` in a
link or `resetNavigator: true` restarts it). A page reached directly (bookmark, link in an email) then shows the trail
of whatever was visited before. A view that knows its parents declares them instead; they replace the trail, and the
trail restarts at this page for the views that follow:

```php
use actra\backend\libs\common\BreadcrumbItem;
use actra\backend\libs\common\BreadcrumbItemCollection;

protected function getBreadcrumbParents(): ?BreadcrumbItemCollection
{
    // called when the page is rendered, after prepareHtmlDocument() has loaded the subscription
    return new BreadcrumbItemCollection(
        new BreadcrumbItem(title: $this->event->title, href: event::getPath(ID: $this->event->ID)),
        new BreadcrumbItem(title: '#' . $this->subscription->ID, href: subscription::getPath(ID: $this->subscription->ID)),
    );
}
```

Titles and links are plain text and escaped; the current page is the page title of the view.

### Languages

All texts of the backend come from message classes; English and German are included. The texts of a request follow
the language of its route: the main route (`path` of `ActraBackend::init()`) uses `ActraBackendSettings::$language`,
further languages get their own route:

```php
use actra\backend\settings\BackendRoute;
use actra\yuf\core\Language;

ActraBackend::init(
    routeCollection: $routeCollection,
    path: '/backend/',
    isDefaultForLanguage: false,
    actraBackendSettings: new ActraBackendSettings(
        language: new Language(code: 'de', locale: 'de_CH'), // German texts under /backend/
        ...
        additionalRoutes: [
            // English texts under /en/backend/
            new BackendRoute(path: '/en/backend/', language: new Language(code: 'en', locale: 'en_GB')),
        ]
    ),
    ...
);
```

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

Navigation items of the project follow the route language if they are added by a `BackendNavigationInterface`:

```php
use actra\backend\settings\BackendNavigationInterface;
use actra\backend\settings\BackendRoute;

final class ProjectNavigation implements BackendNavigationInterface
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

The hook is called once per request, for the backend route of the request; each navigation key may be added once.

With several languages, the page header shows a language switcher that links to the same page in each language.

### User Deletion Handler

When a backend user is deleted, the library removes its own user-related records first and then deletes the row from
`auth_user`. Projects that store additional foreign-key references to `auth_user.ID` can register a delete handler to
remove or update their project-specific records before the user itself is deleted.

In the consuming project, register the handler during application bootstrap, for example in the same `index.php` or
bootstrap file where `ActraBackend::init()` is called:

```php
use actra\backend\libs\auth\UserController;
use actra\backend\libs\auth\UserDeleteHandlerInterface;

final class ProjectUserDeleteHandler implements UserDeleteHandlerInterface {
    public function beforeDeleteUser(int $userID): void {
        ProjectUserProfileRepository::deleteByUserID(userID: $userID);
        ProjectUserSettingsRepository::deleteByUserID(userID: $userID);
    }
}
UserController::registerUserDeleteHandler(
    userDeleteHandler: new ProjectUserDeleteHandler()
);
```

The delete handler is executed inside the same database transaction as the built-in user cleanup and before `auth_user`
is deleted. If the handler throws an exception, the transaction is rolled back and the user is not deleted.

Only one delete handler can be registered. Calling `UserController::registerUserDeleteHandler()` again replaces the
previously registered handler.

For simple database relations, projects can alternatively use foreign keys with `ON DELETE CASCADE` or
`ON DELETE SET NULL`, depending on whether related rows should be removed or preserved without the user reference.

### Confirmation Dialog for Destructive Actions

Destructive actions run only on POST. The backend JavaScript (`initDialog()`, `src/assets/js/modules/dialog.js`)
enhances a link to a server-side confirmation page: without JavaScript the link opens the confirmation page with the
POST form; with JavaScript the `#dialog` modal opens, and on confirm the script fetches that page, submits its form
by POST and follows the redirect of the server. No form is needed on the page with the link.

```html
<!-- href = confirmation page, data-form = CSS selector of its POST form -->
<a href="/backend/userDelete-5.html" class="btn btn-danger" data-action="confirm-deletion"
   data-form="main form" data-confirm="Really delete Jane Doe?">Delete</a>
```

The confirmation page (`userDelete-5.html`) shows the message and a normal (yuf) POST form with CSRF token and a
submit button. Its processing redirects after success.

- The script submits the fields of the form, including the CSRF token and the name of the first named submit button.
- Success is a redirect of the server. Any other result (no form found, network error, a re-rendered page with an
  error) navigates to the confirmation page, where the user sees the result.
- Without `data-form`, the previous behaviour stays: a confirmation navigates to the `href` (GET, legacy).
- The `#dialog` element (`.dialog-message`, `[data-action="modal-submit"]`, `[data-action="modal-cancel"]`) is part of
  the default page template. `data-confirm` sets the message.
- `data-confirm-label` (optional) sets the text of the confirm button while the dialog is open (as plain text, no
  HTML); on close or cancel the button gets its template text back ("Yes, delete" / "Ja, löschen"). Use it for actions
  that are not a deletion:

```html
<a href="/backend/subscriptionCancel-42.html" class="btn btn-danger" data-action="confirm-deletion"
   data-form="main form" data-confirm="Really cancel the subscription?"
   data-confirm-label="Yes, cancel">Cancel subscription</a>
```

The same pattern also protects state-changing actions that are not deletions, e.g. generating an API key
(`userGenerateApiKey-5.html`, `profileGenerateApiKey.html`).

### API Key Authentication

API-key functionality is optional and must be enabled through `ActraBackendSettings`:

```php
new ActraBackendSettings(
    ...
    hasApi: true
);
```

Users with management access can generate, replace, or remove a user's API key on the user detail page. Logged-in users
can also manage their own API key on their profile page.

API keys can only be generated if an IP whitelist is configured for the user. If an API key exists, the user's IP
whitelist cannot be emptied until the API key has been removed. Generated keys are shown only once and stored as
SHA-256 hash of the random secret (yuf's `SecretTokenHash`, fast enough for every API request); keys generated before
v1.11.0 keep their former hash until they are generated again.

Generating and removing a key run only on POST, through the confirmation pages `userGenerateApiKey-{ID}.html`,
`userRemoveApiKey-{ID}.html`, `profileGenerateApiKey.html` and `profileRemoveApiKey.html` (see "Confirmation Dialog
for Destructive Actions"). After generating, the new key is kept in the session until the user or profile page has
shown it once.

API clients should send the generated key as a bearer token:

```http
Authorization: Bearer api_key_<public-id>_<secret>
```

To validate the bearer token and retrieve the authenticated user ID, pass the request (in a view:
`$this->context->httpRequest`):

```php
use actra\backend\libs\db\DbAuthApiKeyRepository;
$userID = DbAuthApiKeyRepository::getUserIDForBearerOrThrow(httpRequest: $this->context->httpRequest);
```

If the bearer token is missing, malformed, unknown, or invalid, an `UnauthorizedException` is thrown.

### Integration Tests

The general setup of PHPStan and the PHPUnit bootstrap for projects using yuf (and therefore the backend) is described
in yuf's README, section [Static analysis and tests](https://github.com/Actra-AG/yuf#static-analysis-and-tests).

`DB::get()` creates its connection from `ActraBackend::get()->dbSettings`. Integration tests that run without
`ActraBackend::init()` set the connection explicitly with `DB::useConnection()`, once per process, before the first
`DB::get()`. It returns the same instance as `DB::get()`, so all repositories use it. A second call throws a
`LogicException`.

```php
// tests/bootstrap.php
DB::useConnection(
    dbSettings: new DbSettings(
        hostName: 'db',
        databaseName: 'app_test',
        userName: 'db',
        password: 'db'
    )
);
```

```php
abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        DB::get()->beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::get()->rollBack();
    }
}
```

Everything the repositories write inside the test is rolled back. DDL statements (`CREATE`, `ALTER`, `DROP`,
`TRUNCATE`) cause an implicit commit in MariaDB/MySQL and end the transaction, so do not use them in these tests.

## Documentation

- [Upgrade Guide](UPGRADE.md) - Record of changes and migration instructions.

## Development

This project follows the [Actra coding standard](https://github.com/Actra-AG/coding-standard) (development dependency
`actra/coding-standard`); project-specific rules are in [AGENTS.md](AGENTS.md). Run `composer check` (PHP-CS-Fixer,
PHPStan level 10 strict and PHPUnit; with DDEV: `ddev composer check`) before every commit.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

- **Actra AG** - [https://www.actra.ch](https://www.actra.ch)
