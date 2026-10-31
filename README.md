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
- **Activity Monitoring**: Track visits, token usage and sent emails.
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

Composer loads all classes (yuf, the backend, the project): the entry point (`public/index.php`) and CLI scripts
include `vendor/autoload.php` before `App::fromEnvironment()` (or `Core::fromEnvironment()`); without it PHP reports
`Class "actra\backend\settings\ActraBackendSettings" not found`. Composer is needed to build, not on the server: deploy
with `composer install --no-dev --optimize-autoloader`.

### 2. Assets

Include `src/assets/css/backend.css` and `src/assets/js/backend.js` in the asset build or publishing of the project and
publish the fonts below `/fonts/backend/` ([docs/assets.md](docs/assets.md)). Rebuild them after every update of
`actra/backend`.

### 3. Database Setup

The library requires several database tables to function. For new installations, import the provided SQL files into your
database:

1. Import `db/schema.sql` to create the required table structure.
2. Import `db/data.sql` to populate the tables with initial data: the group "Administrator" and its first user
   `admin@example.com`.
   - Local development (DDEV, `'debug' => true` in `.env.php`, so email fields skip the mail domain check): log in
     with `admin@example.com`; Mailpit (`ddev mailpit`) shows the login code.
   - Production: `example.com` accepts no mail, so nobody can log in with the seed user. Create the real users
     instead, or give the seed user your own address (`UPDATE auth_users SET email='…' WHERE id=1`)
     and log in with the code sent there.

For existing installations, apply the incremental SQL update files from `db/updates/` as documented
in [UPGRADE.md](UPGRADE.md).

## Usage

Create the backend with `ActraBackend::create()` in the front controller and add it to yuf's
[`App`](https://github.com/Actra-AG/yuf/blob/main/docs/setup.md#application-entry-point) with `configure()`: it
adds the backend route, the profile language of the logged-in user as language of the request, the login page as login
path (visitors without login are sent there and back to the requested page afterwards) and the navigation. The backend
route serves the pages of the backend (login, users, profile, …) and the project views of its view group
(`app/view/backend/`) under one path for all languages ([docs/views.md](docs/views.md),
[docs/languages.md](docs/languages.md)): `/` for an application that is only a backend, e.g. `/backend/` next to a
frontend. The backend has no global state; everything a
view needs comes through its `BackendViewContext`.

### With `App`

```php
use actra\backend\ActraBackend;
use actra\backend\settings\ActraBackendSettings;
use actra\backend\settings\MailerSettings;
use actra\backend\settings\TokenSendLimit;
use actra\yuf\App;
use actra\yuf\auth\LoginRateLimit;
use actra\yuf\db\DbSettings;
use actra\yuf\mailer\SmtpEncryptionEnum;
use actra\yuf\mailer\SmtpMailer;
use actra\yuf\security\RateLimiter;
use actra\yuf\security\RateLimitFailModeEnum;

require __DIR__ . '/../vendor/autoload.php';

$app = App::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026)
    ->withLanguage(code: 'en', locale: 'en_GB')
    ->withHtmlRoute(path: '/', viewGroup: 'frontend')
    ->withFileLogger(); // yuf's FileLogger, optionally with a mailer
$core = $app->core;

$actraBackend = ActraBackend::create(
    path: '/backend/', // The URL path of the backend pages and of the project views of viewGroup
    languages: $app->core->availableLanguages, // The languages of the App (withLanguage())
    actraBackendSettings: new ActraBackendSettings(
        ipWhitelist: ['127.0.0.1'], // Allowed IP addresses
        backendName: 'My Project Backend',
        javaScriptPaths: [
            '/assets/js/backend.js'
        ],
        stylesPaths: [
            '/assets/css/backend.css',
        ],
        // Required, null as explicit opt-out: limits logins and code/reset requests per IP and email address
        loginRateLimit: new LoginRateLimit(
            rateLimiter: new RateLimiter(
                directory: $core->cacheDirectory . 'rateLimit', // Not served by the web server
                secret: $core->environmentSettings->getString(key: 'rateLimit.secret'),
            ),
            failMode: RateLimitFailModeEnum::CLOSED,
            logger: $app->getLogger(),
        ),
        maxAllowedLoginAttempts: 5, // Optional, defaults to 5
        frontendHref: 'https://example.com', // Optional
        frontendName: 'Go to Website', // Optional
        hasApi: false, // Optional, defaults to false
        tokenSendLimit: new TokenSendLimit(maxTokens: 5, withinMinutes: 15), // Optional, this default; null: off
    ),
    dbSettings: new DbSettings,
    mailerSettings: new MailerSettings(
        senderEmail: 'noreply@example.com',
        senderName: 'My Project',
        signature: 'Best regards, Your Team',
        // Or GraphMailer for Microsoft 365 (yuf's docs/mail.md); the same mailer can serve withFileLogger()
        mailer: new SmtpMailer(
            serverAddress: $core->httpRequest->getServerAddress(),
            hostName: $core->environmentSettings->getString(key: 'mailer.hostname'),
            smtpUserName: $core->environmentSettings->getString(key: 'mailer.username'),
            smtpPassword: $core->environmentSettings->getString(key: 'mailer.password'),
            serverNameCache: $core->fileCache,
            encryption: $core->environmentSettings->getEnum(
                key: 'mailer.encryption',
                enumClass: SmtpEncryptionEnum::class,
            ),
            port: $core->environmentSettings->getInt(key: 'mailer.port'),
        ),
    ),
    // Required, null as explicit opt-out (error_log()): logs a failed entry of the log of sent emails
    logger: $app->getLogger(),
    viewGroup: 'backend', // Optional, this default: the project views in app/view/backend/
    defaultFileTitle: 'login', // Optional, this default: the page of the path itself
);

$actraBackend->configure(app: $app)->run();
```

`App` has one navigation: the routes with the view factory of the backend (its own route and the project routes with
`createViewFactory()`) get the backend navigation (project items: `ActraBackendSettings::$projectNavigation`), all
other routes get an empty one. A project with its own frontend navigation passes it to `configure()`:

```php
$actraBackend->configure(
    app: $app,
    frontendNavigation: static fn(ViewContext $viewContext): NavigationItemCollection => /* … */,
)->run();
```

The backend route accepts `.html` only and shows the page of `defaultFileTitle` for the path itself (`/backend/` and
`/backend/login.html`, no redirect between them). Former backend paths can redirect to it permanently:
`configure(app: $app, formerPaths: ['/auth/'])` (`/auth/users.html?reset` → `/backend/users.html?reset`).

### With `Core`

Projects that call `Core::prepareHttpResponse()` themselves create the backend the same way (`languages:
$core->availableLanguages`) and add its route, the login path, the navigation provider and the request language
provider:

```php
$logger = $core->createFileLogger(); // yuf's FileLogger, also for LoginRateLimit
$core->prepareHttpResponse(
    logger: $logger,
    routeCollection: new RouteCollection(
        routes: [...$projectRoutes, ...$actraBackend->createRoutes(viewDirectory: $core->viewDirectory)],
        loginPath: $actraBackend->getLoginPath(),
    ),
    // $frontendNavigation: the navigation of the other routes (see "With App"), null: none
    navigationProvider: $actraBackend->createNavigationProvider(frontendNavigation: null),
    requestLanguageProvider: $actraBackend->createRequestLanguageProvider(),
);
```

The SMTP settings in `.env.php` (yuf's [mail docs](https://github.com/Actra-AG/yuf/blob/main/docs/mail.md)):

```php
'mailer.hostname' => 'smtp.example.com',
'mailer.port' => 587,
'mailer.username' => 'mailer@example.com',
'mailer.password' => '…',
'mailer.encryption' => 'starttls', // 'implicit_tls' for port 465, 'none' only for a server on the same host
```

The secret of the login rate limit in `.env.php` (at least 32 bytes, e.g. `bin2hex(random_bytes(32))`; see
[docs/users.md](docs/users.md#login-rate-limit)):

```php
'rateLimit.secret' => '…',
```

The backend is served under the specified path (e.g., `/backend/`), further languages under their own routes
([docs/languages.md](docs/languages.md)). Dates are formatted for the locale of the route (`Language::$locale`). Login
codes and password reset links are mailed after the response (yuf's `ResponseSender::afterResponse()`), so the
response time does not tell whether an email address exists.

## Documentation

- [Assets](docs/assets.md): CSS, JavaScript, fonts and message blocks.
- [Project views](docs/views.md): views, tables and search forms of the project in the backend, project services
  in views (`configure(create:)`), breadcrumb, confirmation dialogs and pages (`ConfirmationView`).
- [Languages](docs/languages.md): one path for all languages, language of the request, switcher, own texts.
- [Users and API keys](docs/users.md): user deletion handler, user managers and impersonation, IP whitelists,
  login codes, reset links and lock-out, send limit, login rate limit, API key authentication, log of sent emails.
- [Integration tests](docs/testing.md): tests of project code against the backend tables.
- [UPGRADE.md](UPGRADE.md): changes and migration instructions.

## Development

This project follows the [Actra coding standard](https://github.com/Actra-AG/coding-standard) (development dependency
`actra/coding-standard`); project-specific rules are in [AGENTS.md](AGENTS.md). Run `composer check` (PHP-CS-Fixer,
PHPStan level 10 strict and PHPUnit; with DDEV: `ddev composer check`) before every commit.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

- **Actra AG** - [https://www.actra.ch](https://www.actra.ch)
