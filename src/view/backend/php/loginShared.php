<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\BackendView;
use actra\backend\libs\auth\BackendLogin;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * The hand-over page of the shared login (`ActraBackendSettings::$sharedLogin`), on the login host and on the sites:
 * shows nothing, every request ends with a redirect to the next step (`BackendLogin::handOverSharedLogin()`).
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
        new BackendLogin(context: $this->backendContext)->handOverSharedLogin();
    }
}
