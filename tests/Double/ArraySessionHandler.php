<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\yuf\core\HttpRequest;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\session\SessionSettings;
use Override;

/**
 * A session handler for `Core` in tests: it never starts a PHP session (the test sets `$_SESSION`), so no cookie or
 * header is sent.
 */
final class ArraySessionHandler extends AbstractSessionHandler
{
    private bool $started = false;
    private bool $closed = false;

    public function __construct(HttpRequest $httpRequest)
    {
        parent::__construct(httpRequest: $httpRequest, sessionSettings: new SessionSettings());
    }

    #[Override]
    public function ensureStarted(): void
    {
        $this->started = true;
    }

    #[Override]
    public function isStarted(): bool
    {
        return $this->started;
    }

    #[Override]
    public function isClosed(): bool
    {
        return $this->closed;
    }

    #[Override]
    public function writeClose(): void
    {
        $this->closed = true;
    }

    #[Override]
    protected function executePreStartActions(): void {}

    #[Override]
    protected function sessionExists(string $id): bool
    {
        return false;
    }

    #[Override]
    public function changeCookieSameSiteToLax(): void {}

    #[Override]
    public function getId(): string
    {
        return 'test-session';
    }

    #[Override]
    public function regenerateId(): void {}
}
