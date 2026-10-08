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
use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\db\DbAuthApiKeyRepository;
use actra\backend\libs\form\ApiKeyRemoveForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * Server-side confirmation page for removing the own API key (without JavaScript the link opens it; with JavaScript
 * the dialog fetches it and submits its form).
 */
class profileRemoveApiKey extends BackendView
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

    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_BACKEND_ACCESS,
        ]);
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: ActraBackend::messages()->common->removeApiKeyTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $userID = MyAuthUser::get()->dbAuthUser->ID;
        if (
            !ActraBackend::get()->actraBackendSettings->hasApi
            || !DbAuthApiKeyRepository::hasByUserID(userID: $userID)
        ) {
            throw new NotFoundException();
        }
        $apiKeyRemoveForm = new ApiKeyRemoveForm(
            context: $this->backendContext,
            userID: $userID,
            cancelLink: profile::getPath(),
        );
        if ($apiKeyRemoveForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: profile::getPath() . '?' . profile::PARAM_CHANGED,
                httpRequest: $this->context->httpRequest,
            );
        }
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'confirmMessage',
            htmlText: HtmlText::fromText(text: ActraBackend::messages()->common->removeApiKeyConfirm),
        );
        $replacements->addHtml(
            identifier: 'form',
            html: $apiKeyRemoveForm->render(),
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::path() . 'profileRemoveApiKey.html';
    }
}
