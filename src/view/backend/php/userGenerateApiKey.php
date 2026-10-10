<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
use actra\backend\ConfirmationView;
use actra\backend\libs\auth\GeneratedApiKeyFlash;
use actra\backend\libs\db\DbAuthUser;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlText;

/**
 * Server-side confirmation page for generating (or replacing) the API key of a user (without JavaScript the link opens
 * it; with JavaScript the dialog fetches it and submits its form). After the redirect, the target page shows the new
 * key once.
 *
 * @internal
 */
final class userGenerateApiKey extends ConfirmationView
{
    private ?DbAuthUser $dbAuthUser = null;

    public function __construct(BackendViewContext $context)
    {
        parent::__construct(
            context: $context,
            maxAllowedPathVars: 1,
            activeHtmlIdList: [
                'users',
                'userList',
            ],
            useNavigator: true,
        );
    }

    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: $this->backendContext->messages->common->generateApiKeyTitle);
    }

    #[\Override]
    protected function prepareConfirmation(): void
    {
        $this->getDbAuthUser();
    }

    private function getDbAuthUser(): DbAuthUser
    {
        if ($this->dbAuthUser !== null) {
            return $this->dbAuthUser;
        }
        $dbAuthUser = $this->backendContext->repositories->users()->selectById(
            id: $this->getRequiredPathVarAsInt(nr: 1),
        );
        if (
            $dbAuthUser === null
            || !$this->backendContext->actraBackend->actraBackendSettings->hasApi
            || $dbAuthUser->ipWhitelist === []
        ) {
            throw new NotFoundException();
        }

        return $this->dbAuthUser = $dbAuthUser;
    }

    #[\Override]
    protected function getQuestion(): string
    {
        return $this->backendContext->messages->common->generateApiKeyConfirm;
    }

    #[\Override]
    protected function getConfirmLabel(): string
    {
        return $this->backendContext->messages->common->generateApiKeyTitle;
    }

    #[\Override]
    protected function getCancelLink(): string
    {
        return $this->backendContext->paths->user(id: $this->getDbAuthUser()->id);
    }

    #[\Override]
    protected function confirm(): string
    {
        $userId = $this->getDbAuthUser()->id;
        GeneratedApiKeyFlash::store(
            session: $this->backendContext->session,
            userId: $userId,
            apiKey: $this->backendContext->repositories->apiKeys()->createForUserId(userId: $userId),
        );

        return $this->backendContext->paths->user(id: $userId);
    }
}
