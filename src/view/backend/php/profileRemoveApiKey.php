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
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlText;

/**
 * Server-side confirmation page for removing the own API key (without JavaScript the link opens it; with JavaScript
 * the dialog fetches it and submits its form).
 *
 * @internal
 */
final class profileRemoveApiKey extends ConfirmationView
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
        return HtmlText::fromText(text: $this->backendContext->messages->common->removeApiKeyTitle);
    }

    #[\Override]
    protected function prepareConfirmation(): void
    {
        if (
            !$this->backendContext->actraBackend->actraBackendSettings->hasApi
            || !$this->backendContext->repositories->apiKeys()->hasByUserId(userId: $this->getUserId())
        ) {
            throw new NotFoundException();
        }
    }

    private function getUserId(): int
    {
        return $this->backendContext->getCurrentUser()->dbAuthUser->id;
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
        return $this->backendContext->paths->profile();
    }

    #[\Override]
    protected function confirm(): string
    {
        $this->backendContext->repositories->apiKeys()->deleteByUserId(userId: $this->getUserId());

        return $this->backendContext->paths->profile() . '?' . profile::PARAM_CHANGED;
    }
}
