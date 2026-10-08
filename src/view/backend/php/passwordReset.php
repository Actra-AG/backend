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
use actra\backend\libs\db\DbAuthTokenRepository;
use actra\backend\libs\form\PasswordResetForm;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class passwordReset extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(
            context: $context,
            maxAllowedPathVars: 1,
        );
    }

    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: ActraBackend::messages()->auth->passwordResetPageTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $this->backendContext->actraBackend->getAuthSession()->logOut();
        $dbAuthToken = DbAuthTokenRepository::getClaimable(
            authTokenType: AuthTokenTypeEnum::PASSWORD,
            token: $this->getRequiredPathVarAsString(nr: 1),
        );
        if ($dbAuthToken === null) {
            throw new NotFoundException();
        }
        $htmlDocument->templateName = 'authentication';
        $messages = ActraBackend::messages()->auth;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'introText',
            htmlText: HtmlText::fromText(text: $messages->passwordResetIntro),
        );
        $passwordResetForm = new PasswordResetForm(context: $this->backendContext, dbAuthToken: $dbAuthToken);
        if ($passwordResetForm->validateAndUpdatePassword()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: passwordResetRes::getPath(),
                httpRequest: $this->context->httpRequest,
            );
        }
        $replacements->addHtml(
            identifier: 'form',
            html: $passwordResetForm->render(),
        );
    }

    public static function getPath(string $token): string
    {
        return ActraBackend::path() . 'passwordReset-' . $token . '.html';
    }
}
