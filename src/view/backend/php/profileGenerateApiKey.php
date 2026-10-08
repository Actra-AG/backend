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
use actra\backend\libs\form\ApiKeyGenerateForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * Server-side confirmation page for generating (or replacing) the own API key (without JavaScript the link opens
 * it; with JavaScript the dialog fetches it and submits its form). After the redirect, the target page shows the new
 * key once.
 *
 * @internal
 */
final class profileGenerateApiKey extends BackendView
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
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = $this->backendContext->getCurrentUser()->dbAuthUser;
        if (
            !$this->backendContext->actraBackend->actraBackendSettings->hasApi
            || $dbAuthUser->ipWhitelist === []
        ) {
            throw new NotFoundException();
        }
        $apiKeyGenerateForm = new ApiKeyGenerateForm(
            context: $this->backendContext,
            userID: $dbAuthUser->ID,
            cancelLink: $this->backendContext->paths->profile(),
        );
        if ($apiKeyGenerateForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $this->backendContext->paths->profile(),
                httpRequest: $this->context->httpRequest,
            );
        }
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'confirmMessage',
            htmlText: HtmlText::fromText(text: $this->backendContext->messages->common->generateApiKeyConfirm),
        );
        $replacements->addHtml(
            identifier: 'form',
            html: $apiKeyGenerateForm->render(),
        );
    }
}
