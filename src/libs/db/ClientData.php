<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\auth\AuthSession;
use actra\yuf\core\HttpRequest;

/**
 * The client of a request as stored with a session or a token: user agent, IP address and session ID.
 */
final readonly class ClientData
{
    public function __construct(
        public string $userAgent,
        public string $ipAddress,
        public string $sessionId,
    ) {}

    public static function fromRequest(HttpRequest $httpRequest, AuthSession $authSession): ClientData
    {
        return new ClientData(
            userAgent: $httpRequest->getUserAgent(),
            ipAddress: $httpRequest->getRemoteAddress(),
            sessionId: $authSession->getSessionId(),
        );
    }

    public function toJson(): string
    {
        return json_encode(
            value: ['userAgent' => $this->userAgent, 'ipAddress' => $this->ipAddress, 'sessionId' => $this->sessionId],
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
