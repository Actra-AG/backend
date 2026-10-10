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
 * Server-side confirmation page for generating (or replacing) the own API key (without JavaScript the link opens
 * it; with JavaScript the dialog fetches it and submits its form). After the redirect, the target page shows the new
 * key once.
 *
 * @internal
 */
final class profileGenerateApiKey extends ConfirmationView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(
            context: $context,
            activeHtmlIdList: [
                'profile',
            ],
            useNavigator: true,
        );
    }

    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_BACKEND_ACCESS,
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
        if (
            !$this->backendContext->actraBackend->actraBackendSettings->hasApi
            || $this->getUser()->ipWhitelist === []
        ) {
            throw new NotFoundException();
        }
    }

    private function getUser(): DbAuthUser
    {
        return $this->backendContext->getCurrentUser()->dbAuthUser;
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
        return $this->backendContext->paths->profile();
    }

    #[\Override]
    protected function confirm(): string
    {
        $userId = $this->getUser()->id;
        GeneratedApiKeyFlash::store(
            session: $this->backendContext->session,
            userId: $userId,
            apiKey: $this->backendContext->repositories->apiKeys()->createForUserId(userId: $userId),
        );

        return $this->backendContext->paths->profile();
    }
}
