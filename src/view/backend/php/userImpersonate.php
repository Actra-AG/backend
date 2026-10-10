<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;
use LogicException;

/**
 * Starts the impersonation of a user with one click on the link of the user page; the link carries the CSRF token of
 * the session (`BackendView::createCsrfLink()`), without it the page answers 404.
 *
 * @internal
 */
final class userImpersonate extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(
            context: $context,
            maxAllowedPathVars: 1,
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
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $context = $this->backendContext;
        $dbAuthUser = $context->repositories->users()->selectById(id: $this->getRequiredPathVarAsInt(nr: 1));
        if (
            $dbAuthUser === null
            || !$this->hasValidCsrfLinkToken()
            || !$context->getCurrentUser()->canImpersonateUser(dbAuthUser: $dbAuthUser)
        ) {
            throw new NotFoundException();
        }
        $firstNavigationItem = $context->getNavigation()->getFirst(
            accessRightCollection: $dbAuthUser->accessRightCollection,
        );
        if ($firstNavigationItem === null) {
            throw new LogicException(
                message: 'The user has no accessible navigation item, so there is no page to redirect to after '
                . 'impersonation.',
            );
        }
        $authSession = $context->authSession;
        $authSession->logIn(
            authSessionId: $context->repositories->sessions()->insert(
                parentId: $authSession->getAuthSessionId(),
                userId: $dbAuthUser->id,
                clientData: $context->clientData,
            ),
        );
        HttpResponse::redirectAndExit(
            relativeOrAbsoluteUri: $firstNavigationItem->href,
            httpRequest: $this->context->httpRequest,
            responseSender: $this->context->responseSender,
        );
    }
}
