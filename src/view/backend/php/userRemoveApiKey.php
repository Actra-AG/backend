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
 * Server-side confirmation page for removing the API key of a user (without JavaScript the link opens it; with
 * JavaScript the dialog fetches it and submits its form).
 *
 * @internal
 */
final class userRemoveApiKey extends BackendView
{
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
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $pathUserID = $this->getRequiredPathVarAsInt(nr: 1);
        $dbAuthUser = $this->backendContext->repositories->users()->selectByID(ID: $pathUserID);
        if (
            $dbAuthUser === null
            || !$this->backendContext->actraBackend->actraBackendSettings->hasApi
            || !$this->backendContext->repositories->apiKeys()->hasByUserID(userID: $dbAuthUser->ID)
        ) {
            throw new NotFoundException();
        }
        $userPath = $this->backendContext->paths->user(ID: $dbAuthUser->ID);
        $apiKeyRemoveForm = new ApiKeyRemoveForm(
            context: $this->backendContext,
            userID: $dbAuthUser->ID,
            cancelLink: $userPath,
        );
        if ($apiKeyRemoveForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $userPath . '?' . user::PARAM_CHANGED,
                httpRequest: $this->context->httpRequest,
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
