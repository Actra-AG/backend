<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\db\DbAuthTokenRepository;
use actra\backend\libs\form\PasswordResetForm;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthSession;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

class passwordReset extends BackendView
{
    public function __construct()
    {
        parent::__construct(
            maxAllowedPathVars: 1
        );
    }

    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    protected function getPageTitle(): HtmlText
    {
        return HtmlText::unencoded(textContent: ActraBackend::messages()->auth->passwordResetPageTitle);
    }

    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        AuthSession::logOut();
        $inputToken = $this->getPathVar(nr: 1);
        if ($inputToken === null) {
            throw new NotFoundException();
        }
        $dbAuthToken = DbAuthTokenRepository::getClaimable(
            authTokenType: AuthTokenTypeEnum::PASSWORD,
            token: $inputToken
        );
        if ($dbAuthToken === null) {
            throw new NotFoundException();
        }
        $htmlDocument->templateName = 'authentication';
        $messages = ActraBackend::messages()->auth;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'introText',
            htmlText: HtmlText::unencoded(textContent: $messages->passwordResetIntro)
        );
        $passwordResetForm = new PasswordResetForm(dbAuthToken: $dbAuthToken);
        if ($passwordResetForm->validateAndUpdatePassword()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: passwordResetRes::getPath()
            );
        }
        $replacements->addEncodedText(
            identifier: 'form',
            content: $passwordResetForm->render()
        );
    }

    public static function getPath(string $token): string
    {
        return ActraBackend::path() . 'passwordReset-' . $token . '.html';
    }
}