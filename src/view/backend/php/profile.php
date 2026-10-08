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
use actra\backend\libs\auth\GeneratedApiKeyFlash;
use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\db\DbAuthApiKeyRepository;
use actra\backend\libs\form\ProfileForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlText;

class profile extends BackendView
{
    public const string PARAM_CHANGED = 'changed';

    public function __construct(BackendViewContext $context)
    {
        $inputParameterCollection = new InputParameterCollection();
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: profile::PARAM_CHANGED,
                source: InputSourceEnum::QUERY,
                isRequired: false,
            ),
        );
        parent::__construct(
            context: $context,
            inputParameterCollection: $inputParameterCollection,
            activeHtmlIdList: [
                'profile',
            ],
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
        return HtmlText::fromText(text: ActraBackend::messages()->profile->profilePageTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = MyAuthUser::get()->dbAuthUser;
        $hasApi = ActraBackend::get()->actraBackendSettings->hasApi;
        $profileForm = new ProfileForm(context: $this->backendContext, dbAuthUser: $dbAuthUser);
        if ($profileForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: profile::getPath() . '?' . profile::PARAM_CHANGED,
                httpRequest: $this->context->httpRequest,
            );
        }
        $replacements = $htmlDocument->replacements;
        $this->addProfileTexts(replacements: $replacements);
        $replacements->addBool(
            identifier: 'changed',
            booleanValue: $this->getInputString(keyName: profile::PARAM_CHANGED) !== null,
        );
        $replacements->addHtml(
            identifier: 'form',
            html: $profileForm->render(),
        );
        if ($dbAuthUser->password === null) {
            $replacements->addHtml(
                identifier: 'createPasswordHref',
                html: profileCreatePassword::getPath(),
            );
        } else {
            $replacements->addHtml(
                identifier: 'createPasswordHref',
                html: '',
            );
            $replacements->addHtml(
                identifier: 'loginPasswordHref',
                html: $this->context->httpRequest->getProtocol()->value . '://'
                    . $this->context->httpRequest->getHost() . loginPassword::getPath(),
            );
            $replacements->addHtml(
                identifier: 'changePasswordHref',
                html: profileChangePassword::getPath(),
            );
            $replacements->addHtml(
                identifier: 'removePasswordHref',
                html: profileRemovePassword::getPath(),
            );
        }
        if (!$hasApi) {
            return;
        }
        $replacements->addHtml(
            identifier: 'apiKey',
            html: DbAuthApiKeyRepository::hasByUserID(userID: $dbAuthUser->ID) ? '***' : '',
        );
        $replacements->addHtml(
            identifier: 'generateApiKeyHref',
            html: $dbAuthUser->ipWhitelist !== [] ? profileGenerateApiKey::getPath() : '',
        );
        $replacements->addHtml(
            identifier: 'removeApiKeyHref',
            html: profileRemoveApiKey::getPath(),
        );
        $replacements->addHtml(
            identifier: 'generatedApiKey',
            html: GeneratedApiKeyFlash::pull(
                session: $this->backendContext->actraBackend->getSession(),
                userID: $dbAuthUser->ID,
            ) ?? '',
        );
    }

    private function addProfileTexts(HtmlReplacementCollection $replacements): void
    {
        $messages = ActraBackend::messages();
        $profileMessages = $messages->profile;
        $common = $messages->common;
        $this->addTexts(
            replacements: $replacements,
            texts: [
                'changedLabel' => $common->successLabel,
                'changedText' => $common->changesSaved,
                'apiKeyGeneratedText' => $common->apiKeyGenerated,
                'removeApiKeyConfirm' => $common->removeApiKeyConfirm,
                'generateApiKeyConfirm' => $common->generateApiKeyConfirm,
                'generateApiKeyConfirmLabel' => $common->generateApiKeyConfirmLabel,
                'generatedApiKeyLabel' => $common->apiKeyValueLabel,
                'passwordProtectionHeading' => $profileMessages->passwordProtectionHeading,
                'passwordProtectionIntro' => $profileMessages->passwordProtectionIntro,
                'createPasswordLink' => $profileMessages->createPasswordLink,
                'passwordLoginIntro' => $profileMessages->passwordLoginIntro,
                'passwordLabel' => $profileMessages->passwordLabel,
                'changeLink' => $profileMessages->changeLink,
                'removeLink' => $common->removeLink,
                'apiKeyHeading' => $common->apiKeyLabel,
                'apiKeyLabel' => $common->apiKeyLabel,
                'apiKeyNone' => $common->apiKeyNone,
                'generateApiKeyLink' => $common->generateApiKeyLink,
                'apiKeyNeedsIpWhitelist' => $common->apiKeyNeedsIpWhitelist,
            ],
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::path() . 'profile.html';
    }
}
