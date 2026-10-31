<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
use actra\backend\i18n\AuthMessages;
use actra\backend\i18n\BackendMessages;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\Route;
use PHPUnit\Framework\TestCase;

/**
 * One backend route for all languages: the texts and dates of a request follow its language (yuf's request language).
 */
final class RequestLanguageTest extends TestCase
{
    private LanguageCollection $languages;
    private ActraBackend $actraBackend;

    #[\Override]
    protected function setUp(): void
    {
        $this->languages = new LanguageCollection(languages: [
            new Language(code: 'de', locale: 'de_CH.UTF-8'),
            new Language(code: 'en', locale: 'en_GB.UTF-8'),
        ]);
        $this->actraBackend = ActraBackendTestInstance::create(
            languages: $this->languages,
            messages: ['en' => BackendMessages::english()->with(
                auth: new AuthMessages()->with(loginPageTitle: 'Sign in'),
            )],
        );
    }

    private function createContext(?string $languageCode): BackendViewContext
    {
        return $this->actraBackend->createContext(
            viewContext: ViewContextFactory::create(
                route: new Route(path: '/backend/', viewDirectory: __DIR__, usesRequestLanguage: true),
                fileTitle: 'login',
                locale: new LocaleHandler(
                    language: $languageCode === null
                        ? null
                        : $this->languages->getLanguageByCode(languageCode: $languageCode),
                    availableLanguages: $this->languages,
                ),
            ),
        );
    }

    public function testContextHasTheTextsAndDatesOfTheRequestLanguage(): void
    {
        $german = $this->createContext(languageCode: 'de');
        $english = $this->createContext(languageCode: 'en');

        $this->assertSame('de', $german->language->code);
        $this->assertSame(AuthMessages::german()->loginPageTitle, $german->messages->auth->loginPageTitle);
        $this->assertSame('de_CH.UTF-8', $german->dateFormatter->locale);
        $this->assertSame('en', $english->language->code);
        $this->assertSame('Sign in', $english->messages->auth->loginPageTitle);
        $this->assertSame('en_GB.UTF-8', $english->dateFormatter->locale);
    }

    public function testRequestWithoutLanguageGetsTheFirstLanguage(): void
    {
        $this->assertSame('de', $this->createContext(languageCode: null)->language->code);
    }

    public function testMessagesOfALanguage(): void
    {
        $this->assertSame('Sign in', $this->actraBackend->getMessages(languageCode: 'en')->auth->loginPageTitle);
        $this->assertSame(
            AuthMessages::german()->loginPageTitle,
            $this->actraBackend->getMessages(languageCode: 'de')->auth->loginPageTitle,
        );
        // Languages without included texts are English
        $this->assertSame(
            new AuthMessages()->loginPageTitle,
            $this->actraBackend->getMessages(languageCode: 'fr')->auth->loginPageTitle,
        );
        $this->assertSame(
            $this->actraBackend->getMessages(languageCode: 'de'),
            $this->actraBackend->getMessages(languageCode: 'de'),
        );
    }
}
