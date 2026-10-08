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
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\form\ApiKeyGenerateForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * Server-side confirmation page for generating (or replacing) the API key of a user (without JavaScript the link opens
 * it; with JavaScript the dialog fetches it and submits its form). After the redirect, the target page shows the new
 * key once.
 *
 * @internal
 */
final class userGenerateApiKey extends BackendView
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
        return HtmlText::fromText(text: ActraBackend::messages()->common->generateApiKeyTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = DbAuthUserRepository::selectByID(ID: $this->getRequiredPathVarAsInt(nr: 1));
        if (
            $dbAuthUser === null
            || !ActraBackend::get()->actraBackendSettings->hasApi
            || $dbAuthUser->ipWhitelist === []
        ) {
            throw new NotFoundException();
        }
        $userPath = user::getPath(ID: $dbAuthUser->ID);
        $apiKeyGenerateForm = new ApiKeyGenerateForm(
            context: $this->backendContext,
            userID: $dbAuthUser->ID,
            cancelLink: $userPath,
        );
        if ($apiKeyGenerateForm->process()) {
            HttpResponse::redirectAndExit(relativeOrAbsoluteUri: $userPath, httpRequest: $this->context->httpRequest);
        }
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'confirmMessage',
            htmlText: HtmlText::fromText(text: ActraBackend::messages()->common->generateApiKeyConfirm),
        );
        $replacements->addHtml(
            identifier: 'form',
            html: $apiKeyGenerateForm->render(),
        );
    }

    public static function getPath(int $ID): string
    {
        return ActraBackend::path() . 'userGenerateApiKey-' . $ID . '.html';
    }
}
