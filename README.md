# Actra Backend

A comprehensive backend management library for the [YUF framework](https://github.com/Actra-AG/yuf). This library
provides a ready-to-use administrative interface with user account management and secure authentication using one-time
tokens sent via email.

## Features

- **One-Time Token Authentication**: Secure login without passwords, using tokens sent to the user's email.
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
- `actra/yuf` framework

## Installation

### 1. Composer

Add the library to your project via Composer:

```bash
composer require actra/backend
```

### 2. Assets

The package ships default assets in `src/assets`.

Projects using this library should include these assets in their own build or asset publishing process. Depending
on the project setup, this can mean importing them into a npm, Grunt, or other asset pipeline, bundling and minifying
them together with project-specific assets, or publishing them directly as static files.

The main entrypoints are:

- `src/assets/css/backend.css`
- `src/assets/js/backend.js`

The default CSS expects the bundled backend fonts to be available below the public font path:

- `/fonts/backend/`

For example, when publishing the package assets directly, publish the backend font files so that
`/fonts/backend/inter-v18-latin-regular.woff2`, `/fonts/backend/inter-v18-latin-italic.woff2`, and the used bold
weights are reachable by the browser.

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
use actra\backend\settings\MailerSettings;
use actra\yuf\db\DbSettingsModel;

// ... initialize your $routeCollection, $language, $navigationItemCollection ...

ActraBackend::init(
    routeCollection: $routeCollection,
    path: '/backend/', // The URL path where the backend will be accessible
    isDefaultForLanguage: false,
    language: $language,
    ipWhitelist: ['127.0.0.1'], // Allowed IP addresses
    backendName: 'My Project Backend',
    javaScriptPaths: [
        '/assets/js/backend.js'
    ],
    stylesHref: '/assets/css/backend.css',
    dbSettingsModel: new DbSettingsModel(
        hostname: 'localhost',
        username: 'db_user',
        password: 'db_password',
        database: 'my_database'
    ),
    mailerSettings: new MailerSettings(
        senderEmail: 'noreply@example.com',
        senderName: 'My Project',
        hostname: 'smtp.example.com',
        username: 'mailer@example.com',
        password: 'smtp_password',
        port: 587,
        tls: true,
        signature: 'Best regards, Your Team'
    ),
    navigationItemCollection: $navigationItemCollection,
    maxAllowedLoginAttempts: 5, // Optional, defaults to 5
    frontendHref: 'https://example.com', // Optional
    frontendName: 'Go to Website' // Optional
);
```

Once initialized, the library automatically registers the necessary routes under the specified path (e.g., `/backend/`)
and adds navigation items to your `NavigationItemCollection`.

### API Key Authentication

Users with management access can generate, replace, or remove a user's API key on the user detail page. Logged-in users
can
also manage their own API key on their profile page.

API keys can only be generated if an IP whitelist is configured for the user. If an API key exists, the user's IP
whitelist cannot be emptied until the API key has been removed. Generated keys are shown only once and stored hashed
with
a salt.

API clients should send the generated key as a bearer token:

```http
Authorization: Bearer api_key_<public-id>_<secret>
```

To validate the bearer token and retrieve the authenticated user ID, use:

```php
use actra\backend\libs\db\DbAuthApiKeyRepository;
$userID = DbAuthApiKeyRepository::getUserIDForBearerOrThrow();
```

If the bearer token is missing, malformed, unknown, or invalid, an `UnauthorizedException` is thrown.

## Documentation

- [Upgrade Guide](UPGRADE.md) - Record of changes and migration instructions.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

- **Actra AG** - [https://www.actra.ch](https://www.actra.ch)
