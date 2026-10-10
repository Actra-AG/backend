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
use actra\backend\i18n\MessageTemplate;
use actra\backend\libs\auth\GeneratedApiKeyFlash;
use actra\backend\libs\common\UserLanguageOptions;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;
use LogicException;

/**
 * @internal
 */
final class user extends BackendView
{
    public const string PARAM_IMPERSONATE = 'impersonate';
    public const string PARAM_ADDED = 'add';
    public const string PARAM_CHANGED = 'mod';
    public const string PARAM_INVITED = 'invited';

    private ?HtmlText $pageTitle = null;

    public function __construct(BackendViewContext $context)
    {
        $inputParameterCollection = new InputParameterCollection();
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: user::PARAM_IMPERSONATE,
                source: InputSourceEnum::QUERY,
                isRequired: false,
            ),
        );
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: user::PARAM_ADDED,
                source: InputSourceEnum::QUERY,
                isRequired: false,
            ),
        );
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: user::PARAM_CHANGED,
                source: InputSourceEnum::QUERY,
                isRequired: false,
            ),
        );
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: user::PARAM_INVITED,
                source: InputSourceEnum::QUERY,
                isRequired: false,
            ),
        );
        parent::__construct(
            context: $context,
            inputParameterCollection: $inputParameterCollection,
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
        return $this->pageTitle ?? HtmlText::fromText(text: '');
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $pathUserId = $this->getRequiredPathVarAsInt(nr: 1);
        $dbAuthUser = $this->backendContext->repositories->users()->selectById(id: $pathUserId);
        if ($dbAuthUser === null) {
            throw new NotFoundException();
        }
        $this->pageTitle = HtmlText::fromText(
            text: $dbAuthUser->renderFullName(messages: $this->backendContext->messages->common),
        );
        $authUser = $this->backendContext->getCurrentUser();
        $canImpersonate = $authUser->canImpersonateUser(dbAuthUser: $dbAuthUser);
        if (
            $canImpersonate
            && $this->getInputString(keyName: user::PARAM_IMPERSONATE) !== null
        ) {
            $this->backendContext->authSession->logIn(
                authSessionId: $this->backendContext->repositories->sessions()->insert(
                    parentId: $this->backendContext->authSession->getAuthSessionId(),
                    userId: $dbAuthUser->id,
                    clientData: $this->backendContext->clientData,
                ),
            );
            $firstNavigationItem = $this->backendContext->getNavigation()->getFirst(
                accessRightCollection: $dbAuthUser->accessRightCollection,
            );
            if ($firstNavigationItem === null) {
                throw new LogicException(
                    message: 'The user has no accessible navigation item, so there is no page to redirect to after '
                    . 'impersonation.',
                );
            }
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $firstNavigationItem->href,
                httpRequest: $this->context->httpRequest,
            );
        }
        $hasApi = $this->backendContext->actraBackend->actraBackendSettings->hasApi;
        $messages = $this->backendContext->messages->user;
        $common = $this->backendContext->messages->common;
        $dateFormatter = $this->backendContext->route->dateFormatter;
        $replacements = $htmlDocument->replacements;
        $this->addTexts(
            replacements: $replacements,
            texts: [
                'editUserTitle' => $messages->editUserTitle,
                'impersonateButton' => $messages->impersonateButton,
                'deleteButton' => $messages->deleteButton,
                'successLabel' => $common->successLabel,
                'addedMessage' => $messages->addedMessage,
                'changedMessage' => $common->changesSaved,
                'invitedMessage' => $messages->invitedMessage,
                'apiKeyGeneratedMessage' => $common->apiKeyGenerated,
                'apiKeyLabel' => $common->apiKeyLabel,
                'apiKeyValueLabel' => $common->apiKeyValueLabel,
                'noteLabel' => $common->noteLabel,
                'notInvitedNote' => $messages->notInvitedNote,
                'inviteTitle' => $messages->inviteTitle,
                'personalDataHeading' => $messages->personalDataHeading,
                'accessSettingsHeading' => $messages->accessSettingsHeading,
                'registeredLabel' => $messages->registeredLabel,
                'welcomeEmailSentLabel' => $messages->welcomeEmailSentLabel,
                'resendLink' => $messages->resendLink,
                'lastLoginLabel' => $messages->lastLoginLabel,
                'neverLabel' => $messages->neverLabel,
                'accessActiveLabel' => $messages->accessActiveLabel,
                'userGroupsDetailLabel' => $messages->userGroupsDetailLabel,
                'removeLink' => $common->removeLink,
                'noApiKey' => $common->apiKeyNone,
                'generateApiKeyLink' => $common->generateApiKeyLink,
                'apiKeyNeedsIpWhitelist' => $common->apiKeyNeedsIpWhitelist,
                'firstNameLabel' => $common->firstNameLabel,
                'lastNameLabel' => $common->lastNameLabel,
                'emailLabel' => $common->emailLabel,
                'phoneLabel' => $common->phoneLabel,
                'languageLabel' => $common->languageLabel,
                'ipWhitelistLabel' => $common->ipWhitelistLabel,
            ],
        );
        $replacements->addHtmlText(
            identifier: 'deleteConfirm',
            htmlText: HtmlText::fromText(text: MessageTemplate::fill(
                template: $messages->deleteConfirm,
                values: ['name' => $dbAuthUser->renderFullName(messages: $this->backendContext->messages->common)],
            )),
        );
        $replacements->addHtml(
            identifier: 'userModHref',
            html: $this->backendContext->paths->userMod(id: $dbAuthUser->id),
        );
        $replacements->addHtml(
            identifier: 'impersonateHref',
            html: $canImpersonate ? '?' . user::PARAM_IMPERSONATE : '',
        );
        $replacements->addHtml(
            identifier: 'removeHref',
            html: $this->backendContext->paths->userDelete(id: $dbAuthUser->id),
        );
        $replacements->addBool(
            identifier: 'added',
            booleanValue: $this->getInputString(keyName: user::PARAM_ADDED) !== null,
        );
        $replacements->addBool(
            identifier: 'changed',
            booleanValue: $this->getInputString(keyName: user::PARAM_CHANGED) !== null,
        );
        $replacements->addBool(
            identifier: 'invited',
            booleanValue: $this->getInputString(keyName: user::PARAM_INVITED) !== null,
        );
        $replacements->addBool(
            identifier: 'isInvited',
            booleanValue: $dbAuthUser->isInvited(),
        );
        $replacements->addHtml(
            identifier: 'inviteHref',
            html: $this->backendContext->paths->userInvite(id: $dbAuthUser->id),
        );
        $replacements->addText(
            identifier: 'firstName',
            text: $dbAuthUser->firstName,
        );
        $replacements->addText(
            identifier: 'lastName',
            text: $dbAuthUser->lastName,
        );
        $replacements->addText(
            identifier: 'email',
            text: $dbAuthUser->email,
        );
        $replacements->addText(
            identifier: 'phone',
            text: $dbAuthUser->renderPhone(),
        );
        $replacements->addText(
            identifier: 'registered',
            text: $dateFormatter->formatDateTime(dateTime: $dbAuthUser->registered),
        );
        $replacements->addText(
            identifier: 'invitedDate',
            text: $dbAuthUser->invitedDate === null
                ? ''
                : $dateFormatter->formatDateTime(dateTime: $dbAuthUser->invitedDate),
        );
        $replacements->addText(
            identifier: 'lastLogin',
            text: $dbAuthUser->renderLastLogin(dateFormatter: $dateFormatter),
        );
        $replacements->addHtml(
            identifier: 'visitsHref',
            html: $this->backendContext->paths->visits(userId: $dbAuthUser->id),
        );
        $userLanguageOptions = UserLanguageOptions::forContext(context: $this->backendContext);
        $replacements->addBool(
            identifier: 'hasMultipleLanguages',
            booleanValue: $userLanguageOptions->isSelectable(),
        );
        $replacements->addText(
            identifier: 'language',
            text: $userLanguageOptions->render(languageCode: $dbAuthUser->languageCode),
        );
        $replacements->addText(
            identifier: 'active',
            text: $dbAuthUser->isActive ? $messages->yes : $messages->no,
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'userGroups',
            htmlDataObjectCollection: $this->backendContext->repositories->groups()
                ->listByUserId(userId: $dbAuthUser->id)
                ->render(),
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'ipWhitelist',
            htmlDataObjectCollection: $dbAuthUser->renderIpWhitelist(),
        );
        if (!$hasApi) {
            return;
        }
        $replacements->addHtml(
            identifier: 'generatedApiKey',
            html: GeneratedApiKeyFlash::pull(
                session: $this->backendContext->session,
                userId: $dbAuthUser->id,
            ) ?? '',
        );
        $replacements->addHtml(
            identifier: 'apiKey',
            html: $this->backendContext->repositories->apiKeys()->hasByUserId(userId: $dbAuthUser->id) ? '***' : '',
        );
        $replacements->addHtml(
            identifier: 'generateApiKeyHref',
            html: $dbAuthUser->ipWhitelist !== []
                ? $this->backendContext->paths->userGenerateApiKey(id: $dbAuthUser->id)
                : '',
        );
        $replacements->addHtml(
            identifier: 'removeApiKeyHref',
            html: $this->backendContext->paths->userRemoveApiKey(id: $dbAuthUser->id),
        );
        $this->addTexts(
            replacements: $replacements,
            texts: [
                'removeApiKeyConfirm' => $common->removeApiKeyConfirm,
                'generateApiKeyConfirm' => $common->generateApiKeyConfirm,
                'generateApiKeyConfirmLabel' => $common->generateApiKeyConfirmLabel,
            ],
        );
    }
}
