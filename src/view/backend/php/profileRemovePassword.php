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
use actra\backend\libs\form\ProfilePasswordForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class profileRemovePassword extends BackendView
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
        return HtmlText::fromText(text: ActraBackend::messages()->profile->removePasswordPageTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $dbAuthUser = MyAuthUser::get()->dbAuthUser;
        if ($dbAuthUser->password === null) {
            throw new NotFoundException();
        }
        $replacements = $htmlDocument->replacements;
        $profilePasswordForm = new ProfilePasswordForm(
            context: $this->backendContext,
            dbAuthUser: $dbAuthUser,
            removePassword: true,
        );
        if ($profilePasswordForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: profile::getPath() . '?' . profile::PARAM_CHANGED,
                httpRequest: $this->context->httpRequest,
            );
        }
        $replacements->addHtml(
            identifier: 'form',
            html: $profilePasswordForm->render(),
        );
    }

    public static function getPath(): string
    {
        return ActraBackend::path() . 'profileRemovePassword.html';
    }
}
