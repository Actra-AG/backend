<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\backend\ActraBackend;
use actra\backend\i18n\BackendMessages;
use actra\backend\settings\ActraBackendSettings;
use actra\backend\settings\BackendNavigation;
use actra\backend\settings\LoginHandOverSettings;
use actra\backend\settings\MailerSettings;
use actra\backend\settings\SharedLoginSettings;
use actra\backend\settings\TokenSendLimit;
use actra\yuf\auth\LoginRateLimit;
use actra\yuf\clock\SystemClock;
use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\Logger;
use actra\yuf\db\DbConnectionParameters;
use actra\yuf\db\DbQueryLogList;
use actra\yuf\db\DbSettings;
use actra\yuf\db\FrameworkDb;
use actra\yuf\mailer\AbstractMailer;
use actra\yuf\mailer\SmtpMailer;
use Closure;

/**
 * An `ActraBackend` with example settings for the tests (no database connection is opened unless a repository is used;
 * `TestDatabase::settings()` for a real one).
 */
final class ActraBackendTestInstance
{
    /**
     * @param list<string> $ipWhitelist The global IP whitelist of the backend
     * @param ?LanguageCollection $languages The languages of the application; default: German only
     * @param array<string, BackendMessages> $messages Own texts by language code
     * @param string $path The backend path
     * @param string $viewGroup The view group of the project views on the backend route
     * @param ?Closure(): FrameworkDb $connectDb The connection of the project; default: one from `$dbSettings`
     * @param string $frontendHref The link to the website, `''` for none
     */
    public static function create(
        ?DbSettings $dbSettings = null,
        ?LanguageCollection $languages = null,
        ?BackendNavigation $projectNavigation = null,
        bool $hasApi = false,
        array $messages = [],
        ?TokenSendLimit $tokenSendLimit = new TokenSendLimit(),
        array $ipWhitelist = [],
        ?LoginRateLimit $loginRateLimit = null,
        AbstractMailer $mailer = new SmtpMailer(
            serverAddress: '127.0.0.1',
            hostName: 'smtp.example.com',
            smtpUserName: 'example',
            smtpPassword: 'example',
            serverNameCache: null,
            clock: new SystemClock(),
        ),
        string $path = '/backend/',
        string $viewGroup = 'backend',
        ?Logger $logger = new RecordingLogger(),
        ?LoginHandOverSettings $loginHandOver = null,
        ?Closure $connectDb = null,
        ?SharedLoginSettings $sharedLogin = null,
        string $frontendHref = '',
    ): ActraBackend {
        return ActraBackend::create(
            path: $path,
            languages: $languages ?? new LanguageCollection(
                languages: [new Language(code: 'de', locale: 'de_CH.UTF-8')],
            ),
            actraBackendSettings: new ActraBackendSettings(
                ipWhitelist: $ipWhitelist,
                backendName: 'Test backend',
                javaScriptPaths: [],
                stylesPaths: [],
                loginRateLimit: $loginRateLimit,
                loginHandOver: $loginHandOver,
                sharedLogin: $sharedLogin,
                frontendHref: $frontendHref,
                hasApi: $hasApi,
                messages: $messages,
                projectNavigation: $projectNavigation,
                tokenSendLimit: $tokenSendLimit,
            ),
            // Connects on first use, like a project's lazy connection
            connectDb: $connectDb ?? static fn(): FrameworkDb => new FrameworkDb(
                connectionParameters: DbConnectionParameters::forMysql(dbSettings: $dbSettings ?? new DbSettings(
                    hostName: 'db.example.com',
                    databaseName: 'example',
                    userName: 'example',
                    password: 'example',
                )),
                queryLog: new DbQueryLogList(clock: new SystemClock()),
            ),
            mailerSettings: new MailerSettings(
                senderEmail: 'backend@example.com',
                senderName: 'Backend',
                mailer: $mailer,
            ),
            logger: $logger,
            viewGroup: $viewGroup,
        );
    }
}
