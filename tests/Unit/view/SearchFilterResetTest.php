<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\settings\AuthTokenTypeEnum;
use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * A remembered filter of the search forms (`VisitSearchForm`, `TokenSearchForm`, `UserSearchForm`) is reset by the
 * empty option ("alle"): the list is unfiltered again (fixed in yuf 5.2.1).
 */
final class SearchFilterResetTest extends TestCase
{
    private BackendPageRenderer $renderer;
    private TestUsers $testUsers;
    private Session $session;

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
        $this->renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
        $this->session = new Session(storage: new ArraySessionStorage());
        $this->renderer->logIn(
            session: $this->session,
            userId: $this->testUsers->create(email: SearchFilterResetTest::email()),
        );
    }

    private static function email(): string
    {
        return 'search-' . bin2hex(string: random_bytes(length: 4)) . '@example.com';
    }

    public function testTokenTypeFilterIsReset(): void
    {
        $repositories = $this->renderer->actraBackend->getRepositories();
        $userId = $this->testUsers->create(email: SearchFilterResetTest::email());
        $token = $repositories->tokens()->createToken(
            dbAuthUser: $repositories->users()->selectById(id: $userId)
                ?? throw new LogicException(message: 'The user was not created.'),
            authTokenTypeEnum: AuthTokenTypeEnum::PASSWORD,
            clientData: BackendPageRenderer::createClientData(),
        );

        $this->assertFilterIsReset(
            fileTitle: 'tokens',
            pathVars: [(string) $userId],
            fieldName: 'typeFilterField',
            filterValue: AuthTokenTypeEnum::LOGIN->value,
            searchParameters: [],
            expectedText: $token,
        );
    }

    public function testVisitStatusFilterIsReset(): void
    {
        $email = SearchFilterResetTest::email();
        $userId = $this->testUsers->create(email: $email);
        $this->renderer->actraBackend->getRepositories()->logins()->insert(
            userId: $userId,
            sessionId: '',
            ipAddress: BackendPageRenderer::IP_ADDRESS,
            inputEmail: $email,
            authResult: AuthResultEnum::ERROR_WRONG_PASSWORD,
        );

        $this->assertFilterIsReset(
            fileTitle: 'visits',
            pathVars: [(string) $userId],
            fieldName: 'statusFilterField',
            filterValue: (string) AuthResultEnum::ERROR_OUT_TRIED->value,
            searchParameters: [],
            expectedText: $email,
        );
    }

    public function testUserGroupFilterIsReset(): void
    {
        $email = SearchFilterResetTest::email();
        $this->testUsers->create(email: $email, withRights: false);

        $this->assertFilterIsReset(
            fileTitle: 'users',
            pathVars: [],
            fieldName: 'userGroup',
            filterValue: '1',
            searchParameters: ['searchQuery' => $email],
            expectedText: $email,
        );
    }

    /**
     * Posts the filter, shows the page again without input (the filter is remembered), then posts the empty option.
     *
     * @param list<string> $pathVars
     * @param array<string, string> $searchParameters Further search fields, posted with each filter
     * @param string $expectedText The content of a table cell of the unfiltered list
     */
    private function assertFilterIsReset(
        string $fileTitle,
        array $pathVars,
        string $fieldName,
        string $filterValue,
        array $searchParameters,
        string $expectedText,
    ): void {
        $filtered = $this->renderer->render(
            languageCode: 'de',
            fileTitle: $fileTitle,
            pathVars: $pathVars,
            session: $this->session,
            postParameters: [$fieldName => $filterValue, ...$searchParameters],
        );
        $remembered = $this->renderer->render(
            languageCode: 'de',
            fileTitle: $fileTitle,
            pathVars: $pathVars,
            session: $this->session,
        );
        $reset = $this->renderer->render(
            languageCode: 'de',
            fileTitle: $fileTitle,
            pathVars: $pathVars,
            session: $this->session,
            postParameters: [$fieldName => '', ...$searchParameters],
        );

        $selectedFilter = '<option value="' . $filterValue . '" selected>';
        // The content of a table cell, not the value of the search field
        $expectedCell = '>' . $expectedText . '<';
        $this->assertStringContainsString($selectedFilter, $filtered);
        $this->assertStringNotContainsString($expectedCell, $filtered);
        $this->assertStringContainsString($selectedFilter, $remembered);
        $this->assertStringNotContainsString($expectedCell, $remembered);
        $this->assertStringContainsString('<option value="" selected>', $reset);
        $this->assertStringContainsString($expectedCell, $reset);
    }
}
