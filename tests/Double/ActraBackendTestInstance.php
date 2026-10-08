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
use LogicException;

/**
 * The `ActraBackend` of the tests: initialized once per test run with example settings (no database connection is
 * opened, `DB::get()` is never called).
 */
final class ActraBackendTestInstance
{
    public static function get(): ActraBackend
    {
        try {
            return ActraBackend::get();
        } catch (LogicException) {
            ActraBackend::init(
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
                    hostname: 'smtp.example.com',
                    username: 'example',
                    password: 'example',
                    port: 587,
                    tls: true,
                    signature: '',
                ),
                navigationItemCollection: new NavigationItemCollection(),
            );

            return ActraBackend::get();
        }
    }
}
