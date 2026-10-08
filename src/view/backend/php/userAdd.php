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
use actra\backend\libs\form\UserAddForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class userAdd extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(
            context: $context,
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
        return HtmlText::fromText(text: ActraBackend::messages()->user->addUserTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $replacements = $htmlDocument->replacements;
        $userAddForm = new UserAddForm(context: $this->backendContext);
        $newUserID = $userAddForm->process();
        if ($newUserID !== null) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: user::getPath(
                    ID: $newUserID,
                ) . '?' . user::PARAM_ADDED,
                httpRequest: $this->context->httpRequest,
            );
        }
        $replacements->addHtml(
            identifier: 'form',
            html: $userAddForm->render(),
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::path() . 'userAdd.html';
    }
}
