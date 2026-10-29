<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\backend\ActraBackend;
use actra\backend\settings\ActraBackendSettings;
use actra\backend\settings\BackendNavigation;
use actra\backend\settings\BackendRoute;
use actra\backend\settings\MailerSettings;
use actra\backend\settings\TokenSendLimit;
use actra\yuf\auth\LoginRateLimit;
use actra\yuf\core\Language;
use actra\yuf\db\DbSettings;
use actra\yuf\mailer\AbstractMailer;
use actra\yuf\mailer\SmtpMailer;

/**
 * An `ActraBackend` with example settings for the tests (no database connection is opened unless a repository is used;
 * `TestDatabase::settings()` for a real one).
 */
final class ActraBackendTestInstance
{
    /**
     * @param list<BackendRoute> $additionalRoutes
     * @param list<string> $ipWhitelist The global IP whitelist of the backend
     * @param Language $language The language of the main route `/backend/`
     */
    public static function create(
        ?DbSettings $dbSettings = null,
        Language $language = new Language(code: 'de', locale: 'de_CH.UTF-8'),
        ?BackendNavigation $projectNavigation = null,
        bool $hasApi = false,
        array $additionalRoutes = [],
        ?TokenSendLimit $tokenSendLimit = new TokenSendLimit(),
        array $ipWhitelist = [],
        ?LoginRateLimit $loginRateLimit = null,
        AbstractMailer $mailer = new SmtpMailer(
            serverAddress: '127.0.0.1',
            hostName: 'smtp.example.com',
            smtpUserName: 'example',
            smtpPassword: 'example',
            serverNameCache: null,
        ),
    ): ActraBackend {
        return ActraBackend::create(
            path: '/backend/',
            isDefaultForLanguage: false,
            actraBackendSettings: new ActraBackendSettings(
                language: $language,
                ipWhitelist: $ipWhitelist,
                backendName: 'Test backend',
                javaScriptPaths: [],
                stylesPaths: [],
                loginRateLimit: $loginRateLimit,
                hasApi: $hasApi,
                additionalRoutes: $additionalRoutes,
                projectNavigation: $projectNavigation,
                tokenSendLimit: $tokenSendLimit,
            ),
            dbSettings: $dbSettings ?? new DbSettings(
                hostName: 'db.example.com',
                databaseName: 'example',
                userName: 'example',
                password: 'example',
            ),
            mailerSettings: new MailerSettings(
                senderEmail: 'backend@example.com',
                senderName: 'Backend',
                signature: '',
                mailer: $mailer,
            ),
        );
    }
}
