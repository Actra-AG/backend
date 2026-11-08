<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\TestDatabase;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The login pages of the backend run on `BackendLogin` since v5.5.0 and keep their HTML (`standards/versioning.md`,
 * section 4): each page, with a return path, is compared with the output of v5.4.0 in `snapshot/`.
 */
final class LoginPageMarkupTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function loginPages(): iterable
    {
        foreach (['de', 'en'] as $languageCode) {
            foreach (['login', 'loginToken', 'loginPassword', 'loginPasswordToken'] as $fileTitle) {
                yield $languageCode . ' ' . $fileTitle => [$languageCode, $fileTitle];
            }
        }
    }

    #[DataProvider('loginPages')]
    public function testLoginPageKeepsItsHtml(string $languageCode, string $fileTitle): void
    {
        TestDatabase::connect();
        $html = new BackendPageRenderer(dbSettings: TestDatabase::settings())->render(
            languageCode: $languageCode,
            fileTitle: $fileTitle,
            pathVars: [],
            session: new Session(storage: new ArraySessionStorage()),
            queryParameters: ['returnTo' => '/backend/user-5.html'],
        );
        // The CSRF token differs per session
        $html = (string) preg_replace(
            pattern: '/(name="csrftoken" value=")[^"]*"/',
            replacement: '$1…"',
            subject: $html,
        );

        $this->assertStringEqualsFile(__DIR__ . '/snapshot/' . $languageCode . '-' . $fileTitle . '.html', $html);
    }
}
