<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\backend\ActraBackend;
use actra\backend\settings\ActraBackendSettings;
use actra\backend\settings\MailerSettings;
use actra\yuf\core\Language;
use actra\yuf\core\RouteCollection;
use actra\yuf\db\DbSettings;
use actra\yuf\layout\NavigationItemCollection;
use actra\yuf\mailer\SmtpMailer;

/**
 * An `ActraBackend` with example settings for the tests (no database connection is opened unless a repository is used).
 */
final class ActraBackendTestInstance
{
    public static function create(): ActraBackend
    {
        return ActraBackend::init(
            routeCollection: new RouteCollection(),
            path: '/backend/',
            isDefaultForLanguage: false,
            actraBackendSettings: new ActraBackendSettings(
                language: new Language(code: 'de', locale: 'de_CH.UTF-8'),
                ipWhitelist: [],
                backendName: 'Test backend',
                javaScriptPaths: [],
                stylesPaths: [],
            ),
            dbSettings: new DbSettings(
                hostName: 'db.example.com',
                databaseName: 'example',
                userName: 'example',
                password: 'example',
            ),
            mailerSettings: new MailerSettings(
                senderEmail: 'backend@example.com',
                senderName: 'Backend',
                signature: '',
                mailer: new SmtpMailer(
                    serverAddress: '127.0.0.1',
                    hostName: 'smtp.example.com',
                    smtpUserName: 'example',
                    smtpPassword: 'example',
                    serverNameCache: null,
                ),
            ),
            navigationItemCollection: new NavigationItemCollection(),
        );
    }
}
