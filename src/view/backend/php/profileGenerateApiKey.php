<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\auth\MyAuthUser;
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
 */
final class profileGenerateApiKey extends BackendView
{
    public function __construct()
    {
        parent::__construct(
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
        return HtmlText::unencoded(textContent: ActraBackend::messages()->common->generateApiKeyTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = MyAuthUser::get()->dbAuthUser;
        if (
            !ActraBackend::get()->actraBackendSettings->hasApi
            || $dbAuthUser->ipWhitelist === []
        ) {
            throw new NotFoundException();
        }
        $apiKeyGenerateForm = new ApiKeyGenerateForm(userID: $dbAuthUser->ID, cancelLink: profile::getPath());
        if ($apiKeyGenerateForm->process()) {
            HttpResponse::redirectAndExit(relativeOrAbsoluteUri: profile::getPath());
        }
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'confirmMessage',
            htmlText: HtmlText::unencoded(textContent: ActraBackend::messages()->common->generateApiKeyConfirm),
        );
        $replacements->addEncodedText(
            identifier: 'form',
            content: $apiKeyGenerateForm->render(),
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::path() . 'profileGenerateApiKey.html';
    }
}
