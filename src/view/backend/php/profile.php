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
use actra\backend\libs\db\DbAuthApiKeyRepository;
use actra\backend\libs\form\ProfileForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class profile extends BackendView
{
    public const string PARAM_CHANGED = 'changed';
    public const string PARAM_GENERATE_API_KEY = 'generateApiKey';
    public const string PARAM_REMOVE_API_KEY = 'removeApiKey';

    public function __construct()
    {
        $inputParameterCollection = new InputParameterCollection();
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: profile::PARAM_CHANGED,
                isRequired: false
            )
        );
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: profile::PARAM_GENERATE_API_KEY,
                isRequired: false
            )
        );
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: profile::PARAM_REMOVE_API_KEY,
                isRequired: false
            )
        );
        parent::__construct(
            inputParameterCollection: $inputParameterCollection,
            activeHtmlIdList: [
                'profile',
            ]
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
        return HtmlText::encoded(textContent: 'Mein Profil');
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = MyAuthUser::get()->dbAuthUser;
        $generatedApiKey = '';
        $canGenerateApiKey = $dbAuthUser->ipWhitelist !== [];
        if (
            $canGenerateApiKey
            && $this->getInputString(keyName: profile::PARAM_GENERATE_API_KEY) !== null
        ) {
            $generatedApiKey = DbAuthApiKeyRepository::createForUserID(userID: $dbAuthUser->ID);
        }
        if ($this->getInputString(keyName: profile::PARAM_REMOVE_API_KEY) !== null) {
            DbAuthApiKeyRepository::deleteByUserID(userID: $dbAuthUser->ID);
        }

        $profileForm = new ProfileForm(dbAuthUser: $dbAuthUser);
        if ($profileForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: profile::getPath() . '?' . profile::PARAM_CHANGED
            );
        }
        $replacements = $htmlDocument->replacements;
        $replacements->addBool(
            identifier: 'changed',
            booleanValue: $this->getInputString(keyName: profile::PARAM_CHANGED) !== null
        );
        $replacements->addEncodedText(
            identifier: 'form',
            content: $profileForm->render()
        );
        $replacements->addEncodedText(
            identifier: 'apiKey',
            content: DbAuthApiKeyRepository::hasByUserID(userID: $dbAuthUser->ID) ? '***' : ''
        );
        $replacements->addEncodedText(
            identifier: 'generateApiKeyHref',
            content: $canGenerateApiKey ? '?' . profile::PARAM_GENERATE_API_KEY : ''
        );
        $replacements->addEncodedText(
            identifier: 'removeApiKeyHref',
            content: '?' . profile::PARAM_REMOVE_API_KEY
        );
        $replacements->addEncodedText(
            identifier: 'generatedApiKey',
            content: $generatedApiKey
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::get()->path . 'profile.html';
    }
}