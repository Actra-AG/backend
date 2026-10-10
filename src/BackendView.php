<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend;

use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\common\BreadcrumbItemCollection;
use actra\backend\libs\common\LanguageSwitcher;
use actra\backend\libs\common\SessionBreadcrumbTrail;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\UnauthorizedIpAddressException;
use actra\yuf\core\BaseView;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\LoginRedirect;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlText;
use actra\yuf\security\CsrfTokenSource;
use LogicException;

/**
 * Extension point: the base of the views of the backend and of the project views in the backend layout. Projects
 * create these views through `ActraBackend::createViewFactory()`.
 */
abstract class BackendView extends BaseView
{
    /**
     * The services of the backend; `$this->context` is the `ViewContext` of yuf.
     */
    protected readonly BackendViewContext $backendContext;

    /**
     * @param array<int, string> $activeHtmlIdList
     */
    public function __construct(
        BackendViewContext $context,
        bool $forceLogout = false,
        InputParameterCollection $inputParameterCollection = new InputParameterCollection(),
        string $requiredViewGroupName = ActraBackend::VIEW_GROUP,
        int $maxAllowedPathVars = 0,
        private readonly array $activeHtmlIdList = [],
        private readonly bool $useNavigator = false,
        private readonly bool $resetNavigator = false,
        private readonly string $legacyBreadcrumbSeparator = ' ',
    ) {
        $this->backendContext = $context;
        $actraBackend = $context->actraBackend;
        $authSession = $context->authSession;
        $myAuthUser = $context->currentUser;
        if ($forceLogout) {
            $authSession->logOut();
            $myAuthUser = null;
        }
        if ($myAuthUser !== null) {
            $this->checkLoggedInUser(context: $context, myAuthUser: $myAuthUser);
        }
        parent::__construct(
            context: $context->viewContext,
            requiredViewGroupName: $requiredViewGroupName,
            ipWhitelist: $actraBackend->actraBackendSettings->ipWhitelist,
            authUser: $myAuthUser,
            requiredAccessRights: static::getRequiredAccessRights(),
            inputParameterCollection: $inputParameterCollection,
            maxAllowedPathVars: $maxAllowedPathVars,
        );
        if ($myAuthUser !== null) {
            $context->repositories->sessions()->updateLastAction(id: $authSession->getAuthSessionId());
        }
    }

    /**
     * The checks of a logged-in user in addition to the global IP whitelist (checked by `BaseView`): the request must
     * come from the user's own IP whitelist if the user has one (both lists must match). In an impersonation, the
     * impersonating user must still be active and manage users, and the whitelist of the impersonating user applies.
     */
    private function checkLoggedInUser(BackendViewContext $context, MyAuthUser $myAuthUser): void
    {
        $whitelistUser = $myAuthUser->dbAuthUser;
        $parentSessionId = $myAuthUser->parentSessionId;
        if ($parentSessionId !== null) {
            $parentSession = $context->repositories->sessions()->selectById(id: $parentSessionId);
            $impersonator = $parentSession === null ? null : new MyAuthUser(
                dbAuthUser: $parentSession->dbAuthUser,
                parentSessionId: null,
                repositories: $context->repositories,
                clientData: $context->clientData,
            );
            if ($impersonator === null || !$impersonator->canManageUsers()) {
                $context->authSession->logOut();
                throw new UnauthorizedException();
            }
            $whitelistUser = $impersonator->dbAuthUser;
        }
        $ipAddress = $context->viewContext->httpRequest->getRemoteAddress();
        if (
            $whitelistUser->ipWhitelist !== []
            && !IpValidator::isInWhitelist(whiteList: $whitelistUser->ipWhitelist, ipAddressToCheck: $ipAddress)
        ) {
            throw new UnauthorizedIpAddressException(message: 'Invalid IP address ' . $ipAddress);
        }
    }

    abstract protected static function getRequiredAccessRights(): AccessRightCollection;

    /**
     * The link of an action that runs on GET with one click (no confirmation page), e.g. impersonation: it carries the
     * CSRF token of the session, which the target view checks with `hasValidCsrfLinkToken()`.
     */
    protected function createCsrfLink(string $path): string
    {
        return $path . '?' . CsrfTokenSource::FIELD_NAME . '='
            . rawurlencode(string: $this->getCsrfTokenSource()->getToken());
    }

    /**
     * Whether the request carries the CSRF token of the session in the query (a link of `createCsrfLink()`).
     */
    protected function hasValidCsrfLinkToken(): bool
    {
        $token = $this->context->httpRequest->getQueryString(name: CsrfTokenSource::FIELD_NAME);

        return $token !== null && $this->getCsrfTokenSource()->isValid(token: $token);
    }

    private function getCsrfTokenSource(): CsrfTokenSource
    {
        return $this->context->formContext->csrfTokenSource
            ?? throw new LogicException(message: 'A link with CSRF token needs the CSRF token of the session.');
    }

    #[\Override]
    public function execute(): void
    {
        $actraBackend = $this->backendContext->actraBackend;
        $actraBackendSettings = $actraBackend->actraBackendSettings;
        $htmlDocument = $this->context->getHtmlDocument();
        $htmlDocument->templateDirectory = $actraBackend->templateDirectory;
        $this->prepareHtmlDocument(htmlDocument: $htmlDocument);
        foreach ($this->activeHtmlIdList as $key => $val) {
            $htmlDocument->setActiveHtmlId(key: $key, val: $val);
        }
        $replacements = $htmlDocument->replacements;
        $this->addLayoutTexts(replacements: $replacements);
        $this->addLanguageSwitcher(replacements: $replacements);
        $replacements->addHtmlText(
            identifier: 'pageTitle',
            htmlText: $this->getPageTitle(),
        );
        $replacements->addHtml(
            identifier: 'backendTitle',
            html: strip_tags(string: $actraBackendSettings->backendName),
        );
        $replacements->addHtml(
            identifier: 'backendName',
            html: $actraBackendSettings->backendName,
        );
        $replacements->addHtml(
            identifier: 'frontendHref',
            html: $actraBackendSettings->frontendHref,
        );
        $replacements->addHtml(
            identifier: 'frontendName',
            html: $actraBackendSettings->frontendName,
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'javaScriptPaths',
            htmlDataObjectCollection: $actraBackend->renderJavaScriptPaths(),
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'stylesPaths',
            htmlDataObjectCollection: $actraBackend->renderStylesPaths(),
        );
        $this->renderBreadcrumb(
            htmlDocument: $htmlDocument,
        );
        if (!$this->backendContext->authSession->isLoggedIn()) {
            $replacements->addHtml(
                identifier: 'firstPageHref',
                html: $this->backendContext->paths->login(),
            );
            $replacements->addHtmlDataObjectCollection(
                identifier: 'mainNavigation',
                htmlDataObjectCollection: null,
            );
            $replacements->addBool(
                identifier: 'isLoggedIn',
                booleanValue: false,
            );
            return;
        }
        $myAuthUser = $this->backendContext->getCurrentUser();
        $accessRightCollection = $myAuthUser->dbAuthUser->accessRightCollection;
        $navigationItemCollection = $this->backendContext->getNavigation();
        $firstNavigationItem = $navigationItemCollection->getFirst(accessRightCollection: $accessRightCollection);
        if ($firstNavigationItem === null) {
            throw new UnauthorizedException();
        }
        $replacements->addHtml(
            identifier: 'firstPageHref',
            html: $firstNavigationItem->href,
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'mainNavigation',
            htmlDataObjectCollection: $navigationItemCollection->prepareForRenderer(
                activeSubNavigationItem: $htmlDocument->isActiveHtmlIdSet(key: 1) ? $htmlDocument->getActiveHtmlId(
                    key: 1,
                ) : '',
                accessRightCollection: $accessRightCollection,
            ),
        );
        $replacements->addBool(
            identifier: 'isLoggedIn',
            booleanValue: true,
        );
        $replacements->addText(
            identifier: 'userName',
            text: $myAuthUser->getUserName(messages: $this->backendContext->messages->common),
        );
        if ($myAuthUser->isSessionChange()) {
            $replacements->addText(
                identifier: 'cancelSessionChangeLink',
                text: $this->createCsrfLink(path: $this->backendContext->paths->userImpersonateEnd()),
            );
        } else {
            $replacements->addHtml(
                identifier: 'cancelSessionChangeLink',
                html: '',
            );
        }
        $replacements->addHtml(
            identifier: 'profileHref',
            html: $this->backendContext->paths->profile(),
        );
        $replacements->addHtml(
            identifier: 'logoutHref',
            html: $this->backendContext->paths->logout(),
        );
        $replacements->addBool(
            identifier: 'hasApi',
            booleanValue: $actraBackendSettings->hasApi,
        );
    }

    private function addLayoutTexts(HtmlReplacementCollection $replacements): void
    {
        $messages = $this->backendContext->messages;
        $layoutMessages = $messages->layout;
        $texts = [
            'skipLink' => $layoutMessages->skipLink,
            'openMenu' => $layoutMessages->openMenu,
            'closeMenu' => $layoutMessages->closeMenu,
            'languageSwitcherLabel' => $layoutMessages->languageSwitcherLabel,
            'cancelSessionChange' => $layoutMessages->cancelSessionChange,
            'myProfile' => $layoutMessages->myProfile,
            'logout' => $layoutMessages->logout,
            'deleteConfirmation' => $layoutMessages->deleteConfirmation,
            'dialogCancel' => $layoutMessages->dialogCancel,
            'dialogConfirmDelete' => $layoutMessages->dialogConfirmDelete,
        ];
        $this->addTexts(replacements: $replacements, texts: $texts);
    }

    private function addLanguageSwitcher(HtmlReplacementCollection $replacements): void
    {
        $languageSwitcher = new LanguageSwitcher(
            backendRouteCollection: $this->backendContext->actraBackend->backendRouteCollection,
            currentRoute: $this->backendContext->route,
            currentUri: $this->context->httpRequest->getUri(),
        );
        $replacements->addBool(identifier: 'hasLanguageSwitcher', booleanValue: $languageSwitcher->isAvailable());
        $replacements->addHtmlDataObjectCollection(
            identifier: 'languageSwitcher',
            htmlDataObjectCollection: $languageSwitcher->render(),
        );
    }

    /**
     * Adds plain texts (e.g. messages) as template replacements; they are encoded when rendered.
     *
     * @param array<string, string> $texts The texts by replacement identifier
     */
    protected function addTexts(HtmlReplacementCollection $replacements, array $texts): void
    {
        foreach ($texts as $identifier => $text) {
            $replacements->addHtmlText(
                identifier: $identifier,
                htmlText: HtmlText::fromText(text: $text),
            );
        }
    }

    abstract protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void;

    /**
     * The path of the next login step with the page requested before the login (`?returnTo=`, see `RouteCollection`
     * with `loginPath:`), so the user gets there after the login.
     */
    protected function keepReturnPath(string $path, ?string $returnPath): string
    {
        return $returnPath === null ? $path : LoginRedirect::createLoginUri(loginPath: $path, returnUri: $returnPath);
    }

    abstract protected function getPageTitle(): HtmlText;

    /**
     * The parent pages of this view for the breadcrumb, from the top level down to the direct parent; `null` (default)
     * uses the trail of the visited pages (`useNavigator: true`). Called after `prepareHtmlDocument()`, so the view can
     * use the data it has loaded there. With parents, the trail of the visited pages restarts at this page.
     */
    protected function getBreadcrumbParents(): ?BreadcrumbItemCollection
    {
        return null;
    }

    private function renderBreadcrumb(HtmlDocument $htmlDocument): void
    {
        $breadcrumb = null;
        $breadcrumbParents = $this->getBreadcrumbParents();
        $currentTitleHtml = $this->getPageTitle()->render();
        if ($this->useNavigator) {
            $trail = new SessionBreadcrumbTrail(
                session: $this->backendContext->session,
                httpRequest: $this->context->httpRequest,
                pathVars: $this->context->pathVars->list(),
                navigationLevels: $htmlDocument->listActiveHtmlIds(),
                separator: $this->legacyBreadcrumbSeparator,
            );
            if ($this->resetNavigator || $breadcrumbParents !== null) {
                $trail->reset();
            }
            $trail->add(titleHtml: $currentTitleHtml);
            foreach ($trail->listNavigationLevels() as $key => $val) {
                $htmlDocument->setActiveHtmlId(key: $key, val: $val);
            }
            $breadcrumb = $trail->render();
        }
        if ($breadcrumbParents !== null) {
            $breadcrumb = $breadcrumbParents->render(
                currentTitleHtml: $currentTitleHtml,
                separator: $this->legacyBreadcrumbSeparator,
            );
        }
        $htmlDocument->replacements->addHtml(
            identifier: 'breadcrumb',
            html: $breadcrumb,
        );
    }
}
