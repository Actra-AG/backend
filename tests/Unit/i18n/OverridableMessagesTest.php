<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\i18n;

use actra\backend\i18n\AuthMessages;
use actra\backend\i18n\BackendMessages;
use Error;
use PHPUnit\Framework\TestCase;

final class OverridableMessagesTest extends TestCase
{
    public function testWithReplacesOnlyTheGivenTextsInACopy(): void
    {
        $german = AuthMessages::german();

        $custom = $german->with(loginPageTitle: 'Login', logoutPageTitle: 'Logout');

        $this->assertSame('Login', $custom->loginPageTitle);
        $this->assertSame('Logout', $custom->logoutPageTitle);
        $this->assertSame($german->loginIntro, $custom->loginIntro);
        $this->assertSame('Anmelden', $german->loginPageTitle);
    }

    public function testWithFailsForAnUnknownText(): void
    {
        $this->expectException(Error::class);

        AuthMessages::german()->with(unknownText: 'x');
    }

    public function testBackendMessagesWithReplacesOnlyTheGivenParts(): void
    {
        $german = BackendMessages::german();
        $auth = AuthMessages::german()->with(loginPageTitle: 'Login');

        $custom = $german->with(auth: $auth);

        $this->assertSame($auth, $custom->auth);
        $this->assertSame($german->common, $custom->common);
    }
}