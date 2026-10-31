<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\view\backend\php;

use actra\backend\BackendView;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\LanguageSwitch;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

/**
 * The language switcher of the page header (`LanguageSwitcher`): sets the language of the session (yuf's
 * `LanguageSwitch`, also without login) and leads back to the page of `returnTo`. For a logged-in user, a link with the
 * CSRF token of the session (`BackendView::createCsrfLink()`) also saves the language in the profile (during an
 * impersonation in the profile of the impersonating user), because the profile language wins over the session
 * (`ProfileLanguageProvider`).
 *
 * @internal
 */
final class language extends BackendView
{
    #[\Override]
    protected static function getRequiredAccessRights(): AccessRightCollection
    {
        return AccessRightCollection::createEmpty();
    }

    #[\Override]
    protected function getPageTitle(): HtmlText
    {
        return HtmlText::fromText(text: $this->backendContext->messages->layout->languageSwitcherLabel);
    }

    #[\Override]
    protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void
    {
        $context = $this->backendContext;
        $httpRequest = $this->context->httpRequest;
        $languages = $context->actraBackend->languages;
        $returnPath = new LanguageSwitch(
            httpRequest: $httpRequest,
            session: $context->session,
            availableLanguages: $languages,
        )->apply(defaultPath: $context->paths->login());
        $languageCode = $httpRequest->getPostString(name: LanguageSwitch::LANGUAGE_PARAMETER)
            ?? $httpRequest->getQueryString(name: LanguageSwitch::LANGUAGE_PARAMETER);
        $currentUser = $context->currentUser;
        if (
            $currentUser !== null
            && $languageCode !== null
            && $languages->hasLanguage(languageCode: $languageCode)
            && $this->hasValidCsrfLinkToken()
        ) {
            $parentSessionId = $currentUser->parentSessionId;
            $userId = $parentSessionId === null
                ? $currentUser->id
                : $context->repositories->sessions()->selectById(id: $parentSessionId)?->dbAuthUser->id;
            if ($userId !== null) {
                $context->repositories->users()->updateLanguage(id: $userId, languageCode: $languageCode);
            }
        }
        HttpResponse::redirectAndExit(
            relativeOrAbsoluteUri: $returnPath,
            httpRequest: $httpRequest,
            responseSender: $this->context->responseSender,
        );
    }
}
