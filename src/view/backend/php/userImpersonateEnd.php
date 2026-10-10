<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\BackendViewContext;
use actra\backend\ConfirmationView;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlText;

/**
 * Confirmation page to end an impersonation ("Cancel session change"): only a POST with CSRF token returns to the
 * session of the impersonating user.
 *
 * @internal
 */
final class userImpersonateEnd extends ConfirmationView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(context: $context);
    }

    /**
     * No right: the impersonated user may have project rights only; `prepareConfirmation()` requires an impersonation.
     */
    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: $this->backendContext->messages->layout->cancelSessionChange);
    }

    #[\Override]
    protected function prepareConfirmation(): void
    {
        $this->getParentSessionId();
    }

    private function getParentSessionId(): int
    {
        return $this->backendContext->currentUser->parentSessionId ?? throw new NotFoundException();
    }

    #[\Override]
    protected function getQuestion(): string
    {
        return $this->backendContext->messages->layout->cancelSessionChangeConfirm;
    }

    #[\Override]
    protected function getConfirmLabel(): string
    {
        return $this->backendContext->messages->layout->cancelSessionChange;
    }

    #[\Override]
    protected function getCancelLink(): string
    {
        return $this->backendContext->getCurrentUser()->getFirstAllowedPage(context: $this->backendContext, returnPath: null);
    }

    #[\Override]
    protected function confirm(): string
    {
        $impersonatedUserId = $this->backendContext->getCurrentUser()->id;
        $this->backendContext->authSession->logIn(authSessionId: $this->getParentSessionId());

        return $this->backendContext->paths->user(id: $impersonatedUserId);
    }
}
