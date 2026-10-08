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
use actra\backend\libs\form\ProfileForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class profile extends BackendView
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
        return HtmlText::fromText(text: $this->backendContext->messages->profile->profilePageTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = $this->backendContext->getCurrentUser()->dbAuthUser;
        $hasApi = $this->backendContext->actraBackend->actraBackendSettings->hasApi;
        $profileForm = new ProfileForm(context: $this->backendContext, dbAuthUser: $dbAuthUser);
        if ($profileForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $this->backendContext->paths->profile() . '?' . profile::PARAM_CHANGED,
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
                html: $this->backendContext->paths->profileCreatePassword(),
            );
        } else {
            $replacements->addHtml(
                identifier: 'createPasswordHref',
                html: '',
            );
            $replacements->addHtml(
                identifier: 'loginPasswordHref',
                html: $this->context->httpRequest->getProtocol()->value . '://'
                    . $this->context->httpRequest->getHost() . $this->backendContext->paths->loginPassword(),
            );
            $replacements->addHtml(
                identifier: 'changePasswordHref',
                html: $this->backendContext->paths->profileChangePassword(),
            );
            $replacements->addHtml(
                identifier: 'removePasswordHref',
                html: $this->backendContext->paths->profileRemovePassword(),
            );
        }
        if (!$hasApi) {
            return;
        }
        $replacements->addHtml(
            identifier: 'apiKey',
            html: $this->backendContext->repositories->apiKeys()->hasByUserID(userID: $dbAuthUser->ID) ? '***' : '',
        );
        $replacements->addHtml(
            identifier: 'generateApiKeyHref',
            html: $dbAuthUser->ipWhitelist !== [] ? $this->backendContext->paths->profileGenerateApiKey() : '',
        );
        $replacements->addHtml(
            identifier: 'removeApiKeyHref',
            html: $this->backendContext->paths->profileRemoveApiKey(),
        );
        $replacements->addHtml(
            identifier: 'generatedApiKey',
            html: GeneratedApiKeyFlash::pull(
                session: $this->backendContext->session,
                userID: $dbAuthUser->ID,
            ) ?? '',
        );
    }

    private function addProfileTexts(HtmlReplacementCollection $replacements): void
    {
        $messages = $this->backendContext->messages;
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
}
