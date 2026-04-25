# Actra Backend

A comprehensive backend management library for the [YUF framework](https://github.com/Actra-AG/yuf). This library provides a ready-to-use administrative interface with user account management and secure authentication using one-time tokens sent via email.

## Features

- **One-Time Token Authentication**: Secure login without passwords, using tokens sent to the user's email.
- **User Management**: Add, modify, and invite users to the system.
- **Role-Based Access Control**: Basic functionality to manage user permissions.
- **IP Whitelisting**: Additional security layer to restrict backend access.
- **Activity Monitoring**: Track visits and token usage.
- **Responsive UI**: Built-in HTML templates for common backend tasks.

## Requirements

- PHP >= 8.5
- `actra/yuf` framework

## Installation

### 1. Composer

Add the library to your project via Composer:

```bash
composer require actra/backend
```

### 2. Database Setup

The library requires several database tables to function. You must import the provided SQL files into your database:

1. Import `db/schema.sql` to create the required table structure.
2. Import `db/data.sql` to populate the tables with initial data, including a test user account.

## Usage

To integrate the backend into your YUF-based application, you need to call `ActraBackend::init()` during your application's bootstrap process.

### Basic Initialization

```php
use actra\backend\ActraBackend;
use actra\backend\settings\MailerSettings;
use actra\yuf\db\DbSettingsModel;

// ... initialize your $routeCollection, $language, $navigationItemCollection ...

ActraBackend::init(
    routeCollection: $routeCollection,
    path: '/backend/', // The URL path where the backend will be accessible
    language: $language,
    ipWhitelist: ['127.0.0.1'], // Allowed IP addresses
    siteName: 'My Project Backend',
    stylesHref: '/assets/css/backend.css',
    dbSettingsModel: new DbSettingsModel(
        hostname: 'localhost',
        username: 'db_user',
        password: 'db_password',
        database: 'my_database'
    ),
    mailerSettings: new MailerSettings(
        hostname: 'smtp.example.com',
        username: 'mailer@example.com',
        password: 'smtp_password',
        port: 587,
        tls: true
    ),
    navigationItemCollection: $navigationItemCollection,
    maxAllowedLoginAttempts: 5 // Optional, defaults to 5
);
```

Once initialized, the library automatically registers the necessary routes under the specified path (e.g., `/backend/`) and adds navigation items to your `NavigationItemCollection`.

## Components

- **Authentication**: Handles login, token verification, and logout.
- **User Management**: Interface for managing administrators and users.
- **Audit Logs**: Visibility into user logins and system interactions.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

- **Actra AG** - [https://www.actra.ch](https://www.actra.ch)