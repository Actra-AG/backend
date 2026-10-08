<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\db\DbAuthApiKeyRepository;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\form\ApiKeyRemoveForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * Server-side confirmation page for removing the API key of a user (without JavaScript the link opens it; with
 * JavaScript the dialog fetches it and submits its form).
 */
class userRemoveApiKey extends BackendView
{
    public function __construct()
    {
        parent::__construct(
            maxAllowedPathVars: 1,
            activeHtmlIdList: [
                'users',
                'userList',
            ],
            useNavigator: true,
        );
    }

    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::unencoded(textContent: ActraBackend::messages()->common->removeApiKeyTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = DbAuthUserRepository::selectByID(ID: $this->getRequiredPathVarAsInt(nr: 1));
        if (
            $dbAuthUser === null
            || !ActraBackend::get()->actraBackendSettings->hasApi
            || !DbAuthApiKeyRepository::hasByUserID(userID: $dbAuthUser->ID)
        ) {
            throw new NotFoundException();
        }
        $userPath = user::getPath(ID: $dbAuthUser->ID);
        $apiKeyRemoveForm = new ApiKeyRemoveForm(userID: $dbAuthUser->ID, cancelLink: $userPath);
        if ($apiKeyRemoveForm->process()) {
            HttpResponse::redirectAndExit(relativeOrAbsoluteUri: $userPath . '?' . user::PARAM_CHANGED);
        }
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'confirmMessage',
            htmlText: HtmlText::unencoded(textContent: ActraBackend::messages()->common->removeApiKeyConfirm),
        );
        $replacements->addEncodedText(
            identifier: 'form',
            content: $apiKeyRemoveForm->render(),
        );
    }

    public static function getPath(int $ID): string
    {
        return ActraBackend::path() . 'userRemoveApiKey-' . $ID . '.html';
    }
}
