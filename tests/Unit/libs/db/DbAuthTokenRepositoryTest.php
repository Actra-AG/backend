<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\db;

use actra\backend\libs\db\BackendRepositories;
use actra\backend\libs\db\ClientData;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\backend\settings\TokenSendLimit;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\yuf\auth\SecretTokenHash;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * One-time tokens: only the hash is stored, links are long enough against guessing, a token is claimed once.
 */
final class DbAuthTokenRepositoryTest extends TestCase
{
    private BackendRepositories $repositories;
    private TestUsers $testUsers;

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
        $this->repositories = ActraBackendTestInstance::create(dbSettings: TestDatabase::settings())->getRepositories();
    }

    private function createUser(): DbAuthUser
    {
        return $this->repositories->users()->selectById(
            id: $this->testUsers->create(email: 'token-' . bin2hex(string: random_bytes(length: 4)) . '@example.com'),
        ) ?? throw new LogicException(message: 'The user was not created.');
    }

    private function createToken(DbAuthUser $dbAuthUser, AuthTokenTypeEnum $type): string
    {
        return $this->repositories->tokens()->createToken(
            dbAuthUser: $dbAuthUser,
            authTokenTypeEnum: $type,
            clientData: new ClientData(userAgent: '', ipAddress: '192.0.2.1', sessionId: ''),
        );
    }

    public function testOnlyTheHashIsStored(): void
    {
        $dbAuthUser = $this->createUser();
        $token = $this->createToken(dbAuthUser: $dbAuthUser, type: AuthTokenTypeEnum::PASSWORD);

        $this->assertSame(
            [SecretTokenHash::fromSecret(secret: $token)->hash],
            array_map(
                callback: static fn($row): string => $row->getString(column: 'token_hash'),
                array: $this->repositories->db()->selectRows(
                    sql: 'SELECT token_hash FROM auth_token WHERE user_id=?',
                    parameters: [$dbAuthUser->id],
                ),
            ),
        );
    }

    public function testLinkTokensAreLongAndLoginCodesShort(): void
    {
        $dbAuthUser = $this->createUser();

        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9]{22}$/D',
            $this->createToken(dbAuthUser: $dbAuthUser, type: AuthTokenTypeEnum::PASSWORD),
        );
        $this->assertMatchesRegularExpression(
            '/^[A-Z0-9]{6}$/D',
            $this->createToken(dbAuthUser: $dbAuthUser, type: AuthTokenTypeEnum::LOGIN),
        );
    }

    public function testShortTokenIsNoPasswordResetLink(): void
    {
        $this->assertNull($this->repositories->tokens()->getClaimable(
            authTokenType: AuthTokenTypeEnum::PASSWORD,
            token: 'ABC123',
        ));
    }

    public function testTokenIsClaimedOnce(): void
    {
        $tokens = $this->repositories->tokens();
        $dbAuthUser = $this->createUser();
        $token = $this->createToken(dbAuthUser: $dbAuthUser, type: AuthTokenTypeEnum::PASSWORD);
        $dbAuthToken = $tokens->getClaimable(authTokenType: AuthTokenTypeEnum::PASSWORD, token: $token)
            ?? throw new LogicException(message: 'The token is not claimable.');
        $clientData = new ClientData(userAgent: '', ipAddress: '192.0.2.1', sessionId: '');

        $this->assertTrue($tokens->claim(dbAuthToken: $dbAuthToken, clientData: $clientData));
        $this->assertFalse($tokens->claim(dbAuthToken: $dbAuthToken, clientData: $clientData));
        $this->assertNull($tokens->getClaimable(authTokenType: AuthTokenTypeEnum::PASSWORD, token: $token));
    }

    public function testTokenOfAnotherUserIsNotClaimable(): void
    {
        $token = $this->createToken(dbAuthUser: $this->createUser(), type: AuthTokenTypeEnum::LOGIN);

        $this->assertNull($this->repositories->tokens()->getClaimable(
            authTokenType: AuthTokenTypeEnum::LOGIN,
            token: $token,
            userId: $this->createUser()->id,
        ));
    }

    public function testCreateTokenWithinLimitStopsAtTheLimit(): void
    {
        $dbAuthUser = $this->createUser();
        $created = [];
        for ($i = 0; $i < 3; $i++) {
            $created[] = $this->repositories->tokens()->createTokenWithinLimit(
                dbAuthUser: $dbAuthUser,
                authTokenTypeEnum: AuthTokenTypeEnum::LOGIN,
                clientData: new ClientData(userAgent: '', ipAddress: '192.0.2.1', sessionId: ''),
                tokenSendLimit: new TokenSendLimit(maxTokens: 2, withinMinutes: 15),
            );
        }

        $this->assertSame([true, true, false], array_map(
            callback: static fn(?string $token): bool => $token !== null,
            array: $created,
        ));
    }
}
