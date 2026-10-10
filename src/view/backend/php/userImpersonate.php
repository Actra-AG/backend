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
use LogicException;

/**
 * Confirmation page to work as another user (impersonation): only a POST with CSRF token switches the session.
 *
 * @internal
 */
final class userImpersonate extends ConfirmationView
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
        return HtmlText::fromText(text: $this->backendContext->messages->user->impersonateButton);
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
            || !$this->backendContext->getCurrentUser()->canImpersonateUser(dbAuthUser: $dbAuthUser)
        ) {
            throw new NotFoundException();
        }

        return $this->dbAuthUser = $dbAuthUser;
    }

    #[\Override]
    protected function getQuestion(): string
    {
        return MessageTemplate::fill(
            template: $this->backendContext->messages->user->impersonateConfirm,
            values: ['name' => $this->getDbAuthUser()->renderFullName(messages: $this->backendContext->messages->common)],
        );
    }

    #[\Override]
    protected function getConfirmLabel(): string
    {
        return $this->backendContext->messages->user->impersonateButton;
    }

    #[\Override]
    protected function getCancelLink(): string
    {
        return $this->backendContext->paths->user(id: $this->getDbAuthUser()->id);
    }

    #[\Override]
    protected function confirm(): string
    {
        $dbAuthUser = $this->getDbAuthUser();
        $firstNavigationItem = $this->backendContext->getNavigation()->getFirst(
            accessRightCollection: $dbAuthUser->accessRightCollection,
        );
        if ($firstNavigationItem === null) {
            throw new LogicException(
                message: 'The user has no accessible navigation item, so there is no page to redirect to after '
                . 'impersonation.',
            );
        }
        $authSession = $this->backendContext->authSession;
        $authSession->logIn(
            authSessionId: $this->backendContext->repositories->sessions()->insert(
                parentId: $authSession->getAuthSessionId(),
                userId: $dbAuthUser->id,
                clientData: $this->backendContext->clientData,
            ),
        );

        return $firstNavigationItem->href;
    }
}
