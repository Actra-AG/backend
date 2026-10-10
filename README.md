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

Composer loads all classes (yuf, the backend, the project): the entry point (`public/index.php`) and CLI scripts
include `vendor/autoload.php` before `Core::fromEnvironment()`; without it PHP reports
`Class "actra\backend\settings\ActraBackendSettings" not found`. Composer is needed to build, not on the server: deploy
with `composer install --no-dev --optimize-autoloader`.

```php
require __DIR__ . '/../vendor/autoload.php';

$core = Core::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026);
```

### 2. Assets

Include `src/assets/css/backend.css` and `src/assets/js/backend.js` in the asset build or publishing of the project and
publish the fonts below `/fonts/backend/` ([docs/assets.md](docs/assets.md)). Rebuild them after every update of
`actra/backend`.

### 3. Database Setup

The library requires several database tables to function. For new installations, import the provided SQL files into your
database:

1. Import `db/schema.sql` to create the required table structure.
2. Import `db/data.sql` to populate the tables with initial data, including a test user account.

For existing installations, apply the incremental SQL update files from `db/updates/` as documented
in [UPGRADE.md](UPGRADE.md).

## Usage

To integrate the backend into your YUF-based application, call `ActraBackend::init()` during your application's
bootstrap process and keep the returned instance: the routes of project views based on `BackendView` need it. The
backend has no global state; everything a view needs comes through its `BackendViewContext`.

### Basic Initialization

```php
use actra\backend\ActraBackend;
use actra\backend\settings\ActraBackendSettings;
use actra\backend\settings\MailerSettings;
use actra\yuf\core\RouteCollection;
use actra\yuf\db\DbSettings;
use actra\yuf\mailer\SmtpMailer;

// Visitors without login are sent to the login page and back to the requested page afterwards (one login page for
// all language routes: yuf's RouteCollection has one login path)
$routeCollection = new RouteCollection(loginPath: '/backend/login.html');
// ... initialize your $language ...

$actraBackend = ActraBackend::init(
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
        signature: 'Best regards, Your Team',
        // Or GraphMailer for Microsoft 365 (yuf's docs/mail.md); the same mailer can serve the FileLogger
        mailer: new SmtpMailer(
            serverAddress: $core->httpRequest->getServerAddress(),
            hostName: 'smtp.example.com',
            smtpUserName: 'mailer@example.com',
            smtpPassword: $core->environmentSettings->getString(key: 'mailer.password'),
            serverNameCache: $core->fileCache,
        ),
    ),
);

// The navigation is built per request (project items: ActraBackendSettings::$projectNavigation)
$core->prepareHttpResponse(
    routeCollection: $routeCollection,
    navigationProvider: $actraBackend->createNavigation(...),
);
```

Once initialized, the library automatically registers the necessary routes under the specified path (e.g., `/backend/`).
Dates are formatted for the locale of the route (`Language::$locale`). Login codes and password reset links are
mailed after the response (yuf's `ResponseSender::afterResponse()`), so the response time does not tell whether an
email address exists.

## Documentation

- [Assets](docs/assets.md): CSS, JavaScript, fonts and message blocks.
- [Project views](docs/views.md): views, tables and search forms of the project in the backend, breadcrumb,
  confirmation dialogs.
- [Languages](docs/languages.md): language of the backend, additional language routes, own texts.
- [Users and API keys](docs/users.md): user deletion handler, API key authentication.
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
