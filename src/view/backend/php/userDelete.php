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
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\form\UserDeleteForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * Server-side confirmation page for the deletion of a user (without JavaScript the link opens it; with JavaScript the dialog
 * fetches it and submits its form).
 */
class userDelete extends BackendView
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

    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createFromStringArray(input: [
            ActraBackend::RIGHT_MANAGE_USERS,
        ]);
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::unencoded(textContent: ActraBackend::messages()->user->deleteButton);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = DbAuthUserRepository::selectByID(ID: $this->getRequiredPathVarAsInt(nr: 1));
        if ($dbAuthUser === null) {
            throw new NotFoundException();
        }
        $userDeleteForm = new UserDeleteForm(dbAuthUser: $dbAuthUser);
        if ($userDeleteForm->process()) {
            HttpResponse::redirectAndExit(relativeOrAbsoluteUri: users::getPath() . '?' . users::PARAM_REMOVED);
        }
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'deleteConfirm',
            htmlText: HtmlText::unencoded(textContent: MessageTemplate::fill(
                template: ActraBackend::messages()->user->deleteConfirm,
                values: ['name' => $dbAuthUser->firstName . ' ' . $dbAuthUser->lastName],
            )),
        );
        $replacements->addEncodedText(
            identifier: 'form',
            content: $userDeleteForm->render(),
        );
    }

    public static function getPath(int $ID): string
    {
        return ActraBackend::path() . 'userDelete-' . $ID . '.html';
    }
}
