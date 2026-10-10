<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * Ends an impersonation ("Cancel session change") with one click; the link carries the CSRF token of the session
 * (`BackendView::createCsrfLink()`), without it or without impersonation the page answers 404.
 *
 * @internal
 */
final class userImpersonateEnd extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(context: $context);
    }

    /**
     * No right: the impersonated user may have project rights only; the view requires an impersonation.
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
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $context = $this->backendContext;
        $currentUser = $context->currentUser;
        $parentSessionId = $currentUser?->parentSessionId;
        if ($currentUser === null || $parentSessionId === null || !$this->hasValidCsrfLinkToken()) {
            throw new NotFoundException();
        }
        $context->authSession->logIn(authSessionId: $parentSessionId);
        HttpResponse::redirectAndExit(
            relativeOrAbsoluteUri: $context->paths->user(id: $currentUser->id),
            httpRequest: $this->context->httpRequest,
            responseSender: $this->context->responseSender,
        );
    }
}
