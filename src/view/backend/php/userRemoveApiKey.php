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
use actra\backend\libs\db\DbAuthUser;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlText;

/**
 * Server-side confirmation page for removing the API key of a user (without JavaScript the link opens it; with
 * JavaScript the dialog fetches it and submits its form).
 *
 * @internal
 */
final class userRemoveApiKey extends ConfirmationView
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
        return HtmlText::fromText(text: $this->backendContext->messages->common->removeApiKeyTitle);
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
            || !$this->backendContext->getCurrentUser()->canManageUser(dbAuthUser: $dbAuthUser)
            || !$this->backendContext->actraBackend->actraBackendSettings->hasApi
            || !$this->backendContext->repositories->apiKeys()->hasByUserId(userId: $dbAuthUser->id)
        ) {
            throw new NotFoundException();
        }

        return $this->dbAuthUser = $dbAuthUser;
    }

    #[\Override]
    protected function getQuestion(): string
    {
        return $this->backendContext->messages->common->removeApiKeyConfirm;
    }

    #[\Override]
    protected function getConfirmLabel(): string
    {
        return $this->backendContext->messages->common->removeApiKeyTitle;
    }

    #[\Override]
    protected function getCancelLink(): string
    {
        return $this->backendContext->paths->user(id: $this->getDbAuthUser()->id);
    }

    #[\Override]
    protected function confirm(): string
    {
        $this->backendContext->repositories->apiKeys()->deleteByUserId(userId: $this->getDbAuthUser()->id);

        return $this->backendContext->paths->user(id: $this->getDbAuthUser()->id) . '?' . user::PARAM_CHANGED;
    }
}
