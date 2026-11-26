<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\settings\BackendFooterLinks;
use actra\backend\settings\FooterLink;
use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\FixedFooterLinks;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\yuf\core\Language;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\TestCase;

/**
 * The footer links (`ActraBackendSettings::$footerLinks`) after the link to the website, and the escaping of the
 * project values `backendName`, `frontendHref` and the footer links.
 */
final class FooterLinksTest extends TestCase
{
    public function testLoginPageShowsTheWebsiteLinkAndTheFooterLinksInTheLanguageOfTheRequest(): void
    {
        TestDatabase::connect();
        $footerLinks = new FixedFooterLinks(labels: ['Imprint', 'Privacy']);
        $renderer = new BackendPageRenderer(
            dbSettings: TestDatabase::settings(),
            frontendHref: 'https://www.example.com/',
            footerLinks: $footerLinks,
        );

        $html = $this->renderLogin(renderer: $renderer, languageCode: 'en');

        $this->assertSame(['en'], $footerLinks->requestedLanguageCodes);
        $this->assertMatchesRegularExpression(
            '#<footer class="auth-footer">\s*<a href="https://www.example.com/">Go to website</a>'
            . '\s*<a href="/en/0.html">Imprint en</a>\s*<a href="/en/1.html">Privacy en</a>\s*</footer>#',
            $html,
        );
    }

    public function testLoginPageShowsTheFooterWithoutWebsiteLink(): void
    {
        TestDatabase::connect();
        $renderer = new BackendPageRenderer(
            dbSettings: TestDatabase::settings(),
            footerLinks: new FixedFooterLinks(labels: ['Impressum']),
        );

        $html = $this->renderLogin(renderer: $renderer, languageCode: 'de');

        $this->assertMatchesRegularExpression(
            '#<footer class="auth-footer">\s*<a href="/de/0.html">Impressum de</a>\s*</footer>#',
            $html,
        );
    }

    public function testNoFooterWithoutLinks(): void
    {
        TestDatabase::connect();
        foreach ([null, new FixedFooterLinks(labels: [])] as $footerLinks) {
            $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings(), footerLinks: $footerLinks);

            $this->assertStringNotContainsString('<footer', $this->renderLogin(renderer: $renderer, languageCode: 'de'));
            $this->assertStringNotContainsString('<footer', $this->renderProfile(renderer: $renderer));
        }
    }

    public function testPageAfterLoginShowsTheFooterLinksInTheSiteFooter(): void
    {
        TestDatabase::connect();
        $renderer = new BackendPageRenderer(
            dbSettings: TestDatabase::settings(),
            frontendHref: '/',
            footerLinks: new FixedFooterLinks(labels: ['Impressum', 'Datenschutz']),
        );

        $html = $this->renderProfile(renderer: $renderer);

        // The link to the website stays in the top section
        $this->assertStringContainsString('<a href="/" class="link-website">Zur Website</a>', $html);
        $this->assertMatchesRegularExpression(
            '#<footer class="site-footer">\s*<a href="/de/0.html">Impressum de</a>'
            . '\s*<a href="/de/1.html">Datenschutz de</a>\s*</footer>#',
            $html,
        );
    }

    public function testEscapesTheProjectValues(): void
    {
        TestDatabase::connect();
        $renderer = new BackendPageRenderer(
            dbSettings: TestDatabase::settings(),
            frontendHref: '/?a=1&b="2"',
            footerLinks: new class implements BackendFooterLinks {
                #[\Override]
                public function listFooterLinks(Language $language): array
                {
                    return [new FooterLink(label: '<script>alert(1)</script> & "x"', href: '/a"b<c>.html')];
                }
            },
            backendName: '<b>Müller & Söhne</b>',
        );

        foreach ([$this->renderLogin(renderer: $renderer, languageCode: 'de'), $this->renderProfile(renderer: $renderer)] as $html) {
            $this->assertStringNotContainsString('<script>alert', $html);
            $this->assertStringNotContainsString('<b>', $html);
            $this->assertStringContainsString('| &lt;b&gt;Müller &amp; Söhne&lt;/b&gt;</title>', $html);
            $this->assertStringContainsString('>&lt;b&gt;Müller &amp; Söhne&lt;/b&gt;<', $html);
            $this->assertStringContainsString('<a href="/a&quot;b&lt;c&gt;.html">', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;x&quot;</a>', $html);
            $this->assertStringContainsString('href="/?a=1&amp;b=&quot;2&quot;"', $html);
        }
    }

    private function renderLogin(BackendPageRenderer $renderer, string $languageCode): string
    {
        return $renderer->render(
            languageCode: $languageCode,
            fileTitle: 'login',
            pathVars: [],
            session: new Session(storage: new ArraySessionStorage()),
        );
    }

    private function renderProfile(BackendPageRenderer $renderer): string
    {
        $session = new Session(storage: new ArraySessionStorage());
        $userId = new TestUsers(db: TestDatabase::connect())->create(
            email: 'footer-' . bin2hex(string: random_bytes(length: 4)) . '@example.com',
            ipWhitelist: [BackendPageRenderer::IP_ADDRESS],
        );
        $renderer->logIn(session: $session, userId: $userId);

        return $renderer->render(languageCode: 'de', fileTitle: 'profile', pathVars: [], session: $session);
    }
}
