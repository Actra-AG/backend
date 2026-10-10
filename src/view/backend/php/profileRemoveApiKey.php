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
use actra\backend\libs\form\ApiKeyRemoveForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * Server-side confirmation page for removing the own API key (without JavaScript the link opens it; with JavaScript
 * the dialog fetches it and submits its form).
 *
 * @internal
 */
final class profileRemoveApiKey extends BackendView
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
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $userId = $this->backendContext->getCurrentUser()->dbAuthUser->id;
        if (
            !$this->backendContext->actraBackend->actraBackendSettings->hasApi
            || !$this->backendContext->repositories->apiKeys()->hasByUserId(userId: $userId)
        ) {
            throw new NotFoundException();
        }
        $apiKeyRemoveForm = new ApiKeyRemoveForm(
            context: $this->backendContext,
            userId: $userId,
            cancelLink: $this->backendContext->paths->profile(),
        );
        if ($apiKeyRemoveForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $this->backendContext->paths->profile() . '?' . profile::PARAM_CHANGED,
                httpRequest: $this->context->httpRequest,
                responseSender: $this->context->responseSender,
            );
        }
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'confirmMessage',
            htmlText: HtmlText::fromText(text: $this->backendContext->messages->common->removeApiKeyConfirm),
        );
        $replacements->addHtml(
            identifier: 'form',
            html: $apiKeyRemoveForm->render(),
        );
    }
}
