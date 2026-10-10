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
use actra\backend\i18n\MessageTemplate;
use actra\backend\libs\db\DbAuthUser;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlText;

/**
 * Server-side confirmation page for the deletion of a user (without JavaScript the link opens it; with JavaScript
 * the dialog fetches it and submits its form).
 *
 * @internal
 */
final class userDelete extends ConfirmationView
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
        return HtmlText::fromText(text: $this->backendContext->messages->user->deleteButton);
    }

    #[\Override]
    protected function prepareConfirmation(): void
    {
        $this->getDbAuthUser();
    }

    /**
     * The user to delete: not the current user, and not the last active user who manages users.
     */
    private function getDbAuthUser(): DbAuthUser
    {
        if ($this->dbAuthUser !== null) {
            return $this->dbAuthUser;
        }
        $context = $this->backendContext;
        $currentUser = $context->getCurrentUser();
        $dbAuthUser = $context->repositories->users()->selectById(id: $this->getRequiredPathVarAsInt(nr: 1));
        if (
            $dbAuthUser === null
            || $dbAuthUser->id === $currentUser->id
            || (
                $dbAuthUser->isActive
                && $dbAuthUser->accessRightCollection->hasAccessRight(accessRight: ActraBackend::RIGHT_MANAGE_USERS)
                && $context->repositories->users()->countActiveWithRight(
                    accessRight: ActraBackend::RIGHT_MANAGE_USERS,
                    exceptUserId: $dbAuthUser->id,
                ) === 0
            )
        ) {
            throw new NotFoundException();
        }

        return $this->dbAuthUser = $dbAuthUser;
    }

    #[\Override]
    protected function getQuestion(): string
    {
        return MessageTemplate::fill(
            template: $this->backendContext->messages->user->deleteConfirm,
            values: ['name' => $this->getDbAuthUser()->renderFullName(messages: $this->backendContext->messages->common)],
        );
    }

    #[\Override]
    protected function getConfirmLabel(): string
    {
        return $this->backendContext->messages->user->deleteButton;
    }

    #[\Override]
    protected function getCancelLink(): string
    {
        return $this->backendContext->paths->user(id: $this->getDbAuthUser()->id);
    }

    #[\Override]
    protected function confirm(): string
    {
        $this->backendContext->userController->deleteUser(userId: $this->getDbAuthUser()->id);

        return $this->backendContext->paths->users() . '?' . users::PARAM_REMOVED;
    }
}
