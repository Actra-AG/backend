<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\settings;

use actra\backend\settings\ActraBackendSettings;
use actra\backend\settings\FooterLink;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FooterLinkTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function allowedHrefs(): iterable
    {
        yield 'https URL' => ['https://example.com/impressum'];
        yield 'http URL with query' => ['http://example.com/privacy?lang=de#top'];
        yield 'upper-case scheme' => ['HTTPS://example.com/'];
        yield 'path' => ['/de/privacy.html'];
        yield 'root' => ['/'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedHrefs(): iterable
    {
        yield 'empty' => [''];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'javascript with tab' => ["java\tscript:alert(1)"];
        yield 'data' => ['data:text/html,<script>alert(1)</script>'];
        yield 'mailto' => ['mailto:info@example.com'];
        yield 'tel' => ['tel:+41000000000'];
        yield 'protocol-relative' => ['//evil.example/'];
        yield 'backslash' => ['/\evil.example/'];
        yield 'relative path' => ['privacy.html'];
        yield 'scheme without host' => ['https:///privacy.html'];
        yield 'control character' => ["/privacy\n.html"];
        yield 'space' => ['/privacy policy.html'];
        yield 'leading space' => [' https://example.com/'];
    }

    #[DataProvider('allowedHrefs')]
    public function testAcceptsHttpUrlsAndPaths(string $href): void
    {
        $footerLink = new FooterLink(label: 'Privacy', href: $href);

        $this->assertSame($href, $footerLink->href);
        $this->assertSame($href, $this->createSettings(frontendHref: $href)->frontendHref);
    }

    #[DataProvider('refusedHrefs')]
    public function testRefusesOtherUrls(string $href): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FooterLink(label: 'Privacy', href: $href);
    }

    #[DataProvider('refusedHrefs')]
    public function testFrontendHrefFollowsTheSameRule(string $href): void
    {
        if ($href === '') {
            // No link to the website
            $this->assertSame('', $this->createSettings(frontendHref: $href)->frontendHref);

            return;
        }
        $this->expectException(InvalidArgumentException::class);

        $this->createSettings(frontendHref: $href);
    }

    public function testRefusesAnEmptyLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FooterLink(label: ' ', href: '/privacy.html');
    }

    private function createSettings(string $frontendHref): ActraBackendSettings
    {
        return new ActraBackendSettings(
            ipWhitelist: [],
            backendName: 'Backend',
            javaScriptPaths: [],
            stylesPaths: [],
            loginRateLimit: null,
            loginHandOver: null,
            sharedLogin: null,
            footerLinks: null,
            userScope: null,
            frontendHref: $frontendHref,
        );
    }
}
