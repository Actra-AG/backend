<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\BackendView;
use actra\backend\libs\form\LoginPasswordForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class loginPassword extends BackendView
{
    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: $this->backendContext->messages->auth->loginPageTitle);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $this->backendContext->authSession->logOut();
        $htmlDocument->templateName = 'authentication';
        $messages = $this->backendContext->messages->auth;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'introText',
            htmlText: HtmlText::fromText(text: $messages->loginPasswordIntro),
        );
        $loginPasswordForm = new LoginPasswordForm(context: $this->backendContext);
        if ($loginPasswordForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $this->keepReturnPath(
                    path: $this->backendContext->paths->loginPasswordToken(),
                    returnPath: $loginPasswordForm->getReturnPath(),
                ),
                httpRequest: $this->context->httpRequest,
                responseSender: $this->context->responseSender,
            );
        }
        $replacements->addHtml(
            identifier: 'form',
            html: $loginPasswordForm->render(),
        );
    }
}
