<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\form;

use actra\backend\BackendViewContext;
use actra\backend\libs\form\LoginForm;
use actra\backend\libs\form\LoginPasswordForm;
use actra\backend\libs\form\LoginTokenForm;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\core\Route;
use actra\yuf\form\component\collection\Form;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The login forms keep the markup of v2.3.1 (`standards/versioning.md`, section 4): the hidden fields come first, the
 * button row directly after the last visible field, so `.form>:not([type=hidden])+*` gives it its margin.
 */
final class LoginFormMarkupTest extends TestCase
{
    /**
     * @return array<string, array{Closure(BackendViewContext): Form}>
     */
    public static function loginForms(): array
    {
        return [
            'LoginForm' => [static fn(BackendViewContext $context): Form => new LoginForm(context: $context)],
            'LoginPasswordForm' => [
                static fn(BackendViewContext $context): Form => new LoginPasswordForm(context: $context),
            ],
            'LoginTokenForm' => [static fn(BackendViewContext $context): Form => new LoginTokenForm(context: $context)],
        ];
    }

    /**
     * @param Closure(BackendViewContext): Form $createForm
     */
    #[DataProvider('loginForms')]
    public function testHiddenFieldsComeBeforeTheVisibleFields(Closure $createForm): void
    {
        $html = $createForm(ActraBackendTestInstance::create()->createContext(
            viewContext: ViewContextFactory::create(
                route: new Route(path: '/backend/', viewDirectory: __DIR__),
                fileTitle: 'login',
            ),
        ))->render();

        $firstDl = strpos(haystack: $html, needle: '<dl>');
        $this->assertNotFalse($firstDl);
        $this->assertStringContainsString(
            '<input type="hidden" name="returnTo"',
            substr(string: $html, offset: 0, length: $firstDl),
        );
        $this->assertSame(0, substr_count(haystack: substr(string: $html, offset: $firstDl), needle: 'type="hidden"'));
        $this->assertStringContainsString('</dl><div class="form-control">', $html);
    }
}
