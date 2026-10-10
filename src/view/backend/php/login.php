<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\BackendView;
use actra\backend\libs\form\LoginForm;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * @internal
 */
final class login extends BackendView
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
        if ($this->backendContext->authSession->isLoggedIn()) {
            $this->backendContext->getCurrentUser()->redirectToFirstAllowedPage(context: $this->backendContext);
        }
        $htmlDocument->templateName = 'authentication';
        $messages = $this->backendContext->messages->auth;
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'introText',
            htmlText: HtmlText::fromText(text: $messages->loginIntro),
        );
        $loginForm = new LoginForm(context: $this->backendContext);
        if ($loginForm->process()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $this->keepReturnPath(path: $this->backendContext->paths->loginToken()),
                httpRequest: $this->context->httpRequest,
            );
        }
        $replacements->addHtml(
            identifier: 'form',
            html: $loginForm->render(),
        );
    }
}
