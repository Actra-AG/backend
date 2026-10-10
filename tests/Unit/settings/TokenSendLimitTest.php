<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\settings;

use actra\backend\settings\TokenSendLimit;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class TokenSendLimitTest extends TestCase
{
    public function testDefaultIsFiveTokensWithinFifteenMinutes(): void
    {
        $tokenSendLimit = new TokenSendLimit();

        $this->assertSame(5, $tokenSendLimit->maxTokens);
        $this->assertSame(15, $tokenSendLimit->withinMinutes);
    }

    #[TestWith([0, 15])]
    #[TestWith([5, 0])]
    #[TestWith([-1, 15])]
    public function testValuesMustBePositive(int $maxTokens, int $withinMinutes): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TokenSendLimit(maxTokens: $maxTokens, withinMinutes: $withinMinutes);
    }
}
