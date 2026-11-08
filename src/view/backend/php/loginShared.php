<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\BackendView;
use actra\backend\libs\auth\BackendLogin;
use actra\backend\libs\auth\LoginContinuation;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * The hand-over page of the shared login (`ActraBackendSettings::$sharedLogin`), on the login host and on the sites:
 * shows nothing, every request ends with a redirect to the next step (`BackendLogin::handOverSharedLogin()`). After a
 * login form whose next step is on another host (`?loginContinue=`, `LoginContinuation`), it shows a short page that
 * continues there with a refresh (also with the one-way hand-over, `ActraBackendSettings::$loginHandOver`).
 *
 * @internal
 */
final class loginShared extends BackendView
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
        $continueUrl = LoginContinuation::take(
            session: $this->backendContext->session,
            id: $this->context->httpRequest->getQueryString(name: LoginContinuation::PARAMETER),
        );
        if ($continueUrl === null) {
            new BackendLogin(context: $this->backendContext)->handOverSharedLogin();
        }
        // A new navigation, not a redirect of the form: the CSP `form-action` of the form does not apply to it
        $htmlDocument->templateName = 'authentication';
        // The markup of the logout page: heading, status and the link (if the browser does not refresh)
        $htmlDocument->useContentFile(filePath: __DIR__ . '/../html/logout.html');
        $messages = $this->backendContext->messages->auth;
        $replacements = $htmlDocument->replacements;
        $this->addTexts(replacements: $replacements, texts: [
            'heading' => $messages->loginContinueHeading,
            'statusText' => $messages->loginContinueStatus,
            'loginText' => $messages->loginContinueLink,
        ]);
        $replacements->addText(identifier: 'loginHref', text: $continueUrl);
        $replacements->addText(identifier: 'refreshUrl', text: $continueUrl);
    }
}
