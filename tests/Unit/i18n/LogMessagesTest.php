<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\i18n;

use actra\backend\i18n\LogMessages;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\auth\AuthResultEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LogMessagesTest extends TestCase
{
    /**
     * @return array<string, array{LogMessages}>
     */
    public static function languageProvider(): array
    {
        return [
            'english' => [new LogMessages()],
            'german' => [LogMessages::german()],
        ];
    }

    #[DataProvider('languageProvider')]
    public function testEveryAuthResultHasALabel(LogMessages $messages): void
    {
        foreach (AuthResultEnum::cases() as $authResult) {
            $this->assertNotSame('', trim(string: $messages->authResult(authResult: $authResult)), $authResult->name);
        }
    }

    #[DataProvider('languageProvider')]
    public function testEveryAuthTokenTypeHasALabel(LogMessages $messages): void
    {
        foreach (AuthTokenTypeEnum::cases() as $authTokenType) {
            $this->assertNotSame('', trim(string: $authTokenType->render(messages: $messages)), $authTokenType->name);
        }
    }

    public function testGermanAuthResultLabelsEqualTheYufLabels(): void
    {
        $messages = LogMessages::german();
        foreach (AuthResultEnum::cases() as $authResult) {
            $this->assertSame($authResult->render(), $messages->authResult(authResult: $authResult), $authResult->name);
        }
    }

    public function testGermanAuthTokenTypeLabels(): void
    {
        $messages = LogMessages::german();
        $this->assertSame('Passwort-Reset', AuthTokenTypeEnum::PASSWORD->render(messages: $messages));
        $this->assertSame('Aktivierung', AuthTokenTypeEnum::ACTIVATION->render(messages: $messages));
        $this->assertSame('Anmeldung', AuthTokenTypeEnum::LOGIN->render(messages: $messages));
    }
}
