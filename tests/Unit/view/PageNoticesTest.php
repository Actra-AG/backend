<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\settings\PageNotice;
use actra\backend\settings\PageNoticeLevelEnum;
use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\RecordingPageNoticeProvider;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\yuf\html\HtmlText;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\TestCase;

/**
 * The notices above the content of the pages after login (`ActraBackendSettings::$pageNoticeProvider`,
 * `BackendView::addPageNotice()`).
 */
final class PageNoticesTest extends TestCase
{
    public function testShowsTheNoticesOfTheProviderAboveTheContent(): void
    {
        TestDatabase::connect();
        $provider = new RecordingPageNoticeProvider(pageNotices: [
            new PageNotice(level: PageNoticeLevelEnum::WARNING, content: HtmlText::fromText(text: 'Support <until> 14:30')),
            new PageNotice(
                level: PageNoticeLevelEnum::NOTE,
                content: HtmlText::fromHtml(html: '<form method="post"><button>End</button></form>'),
                isLiveRegion: true,
            ),
        ]);
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings(), pageNoticeProvider: $provider);
        $session = $this->logIn(renderer: $renderer);

        foreach (['profile', 'profileRemoveApiKey'] as $fileTitle) {
            $html = $renderer->render(languageCode: 'de', fileTitle: $fileTitle, pathVars: [], session: $session);

            $this->assertMatchesRegularExpression(
                '#<main id="content">\s*<div class="msg msg-warning">Support &lt;until&gt; 14:30</div>'
                . '\s*<div class="msg msg-note" role="status" aria-live="polite">'
                . '<form method="post"><button>End</button></form></div>#',
                $html,
                $fileTitle,
            );
        }
        $this->assertSame(2, $provider->calls);
    }

    public function testNoticesOfTheViewFollowTheNoticesOfTheProvider(): void
    {
        TestDatabase::connect();
        $renderer = new BackendPageRenderer(
            dbSettings: TestDatabase::settings(),
            pageNoticeProvider: new RecordingPageNoticeProvider(pageNotices: [
                new PageNotice(level: PageNoticeLevelEnum::ERROR, content: HtmlText::fromText(text: 'Maintenance')),
            ]),
        );

        $html = $renderer->render(
            languageCode: 'de',
            fileTitle: 'noticePage',
            pathVars: [],
            session: $this->logIn(renderer: $renderer),
        );

        $this->assertMatchesRegularExpression(
            '#<div class="msg msg-error">Maintenance</div>\s*<div class="msg msg-note">Notice of the view</div>#',
            $html,
        );
    }

    public function testNoNoticesWithoutProvider(): void
    {
        TestDatabase::connect();
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());

        $html = $renderer->render(
            languageCode: 'de',
            fileTitle: 'profile',
            pathVars: [],
            session: $this->logIn(renderer: $renderer),
        );

        $this->assertMatchesRegularExpression('#<main id="content">\s*<div class="wrapper-sm#', $html);
    }

    public function testTheLoginPageDoesNotAskTheProvider(): void
    {
        TestDatabase::connect();
        $provider = new RecordingPageNoticeProvider(pageNotices: [
            new PageNotice(level: PageNoticeLevelEnum::WARNING, content: HtmlText::fromText(text: 'Support')),
        ]);
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings(), pageNoticeProvider: $provider);

        $html = $renderer->render(
            languageCode: 'de',
            fileTitle: 'login',
            pathVars: [],
            session: new Session(storage: new ArraySessionStorage()),
        );

        $this->assertStringNotContainsString('Support', $html);
        $this->assertSame(0, $provider->calls);
    }

    private function logIn(BackendPageRenderer $renderer): Session
    {
        $session = new Session(storage: new ArraySessionStorage());
        $userId = new TestUsers(db: TestDatabase::connect())->create(
            email: 'notice-' . bin2hex(string: random_bytes(length: 4)) . '@example.com',
            ipWhitelist: [BackendPageRenderer::IP_ADDRESS],
        );
        $renderer->actraBackend->getRepositories()->apiKeys()->createForUserId(userId: $userId);
        $renderer->logIn(session: $session, userId: $userId);

        return $session;
    }
}
