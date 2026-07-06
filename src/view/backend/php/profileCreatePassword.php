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
use actra\backend\libs\form\ProfilePasswordForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class profileCreatePassword extends BackendView
{
    public function __construct()
    {
        parent::__construct(
            activeHtmlIdList: [
                'profile',
            ],
            useNavigator: true
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
        return HtmlText::encoded(textContent: 'Passwort erstellen');
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = MyAuthUser::get()->dbAuthUser;
        if ($dbAuthUser->password !== null) {
            throw new NotFoundException();
        }
        $replacements = $htmlDocument->replacements;
        $profilePasswordForm = new ProfilePasswordForm(
            dbAuthUser: $dbAuthUser,
            removePassword: false
        );
        if ($profilePasswordForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: profile::getPath() . '?' . profile::PARAM_CHANGED
            );
        }
        $replacements->addEncodedText(
            identifier: 'form',
            content: $profilePasswordForm->render()
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::get()->path . 'profileCreatePassword.html';
    }
}