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
use actra\yuf\auth\UnauthorizedAccessRightException;
use actra\yuf\core\BaseView;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\InputSourceEnum;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlText;

/**
 * Extension point: the base of the views of the backend and of the project views in the backend layout. Projects
 * create these views through `ActraBackend::createViewFactory()`.
 */
abstract class BackendView extends BaseView
{
    public const string PARAM_FROM_LOGIN = 'fromLogin';
    public const string PARAM_CANCEL_SESSION_CHANGE = 'cancelSessionChange';

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
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                source: InputSourceEnum::QUERY,
                name: BackendView::PARAM_FROM_LOGIN,
                isRequired: false,
            ),
        );
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                source: InputSourceEnum::QUERY,
                name: BackendView::PARAM_CANCEL_SESSION_CHANGE,
                isRequired: false,
            ),
        );
        $ipWhitelist = $actraBackend->actraBackendSettings->ipWhitelist;
        if ($myAuthUser !== null) {
            $parentSessionID = $myAuthUser->parentSessionID;
            if ($parentSessionID !== null) {
                $parentSession = $this->backendContext->repositories->sessions()->selectByID(ID: $parentSessionID);
                if ($parentSession === null) {
                    throw new UnauthorizedException();
                }
                $userIpWhitelist = $parentSession->dbAuthUser->ipWhitelist;
            } else {
                $userIpWhitelist = $myAuthUser->dbAuthUser->ipWhitelist;
            }
            foreach ($userIpWhitelist as $ipAddress) {
                if (!in_array(
                    needle: $ipAddress,
                    haystack: $ipWhitelist,
                    strict: true,
                )) {
                    $ipWhitelist[] = $ipAddress;
                }
            }
        }
        try {
            parent::__construct(
                context: $context->viewContext,
                requiredViewGroupName: $requiredViewGroupName,
                ipWhitelist: $ipWhitelist,
                authUser: $myAuthUser,
                requiredAccessRights: static::getRequiredAccessRights(),
                inputParameterCollection: $inputParameterCollection,
                maxAllowedPathVars: $maxAllowedPathVars,
            );
        } catch (UnauthorizedAccessRightException $unauthorizedAccessRightException) {
            if (
                $myAuthUser === null
                && $this->getInputString(keyName: BackendView::PARAM_FROM_LOGIN) === null
                && $context->viewContext->content->getContentType()->isHtml()
            ) {
                MyAuthUser::setRequestedPageAfterLogin(
                    session: $context->session,
                    path: $context->viewContext->httpRequest->getUri(),
                );
                HttpResponse::redirectAndExit(
                    relativeOrAbsoluteUri: $this->backendContext->paths->login(),
                    httpRequest: $context->viewContext->httpRequest,
                );
            }
            throw $unauthorizedAccessRightException;
        }
        if ($myAuthUser !== null) {
            $context->repositories->sessions()->updateLastAction(ID: $authSession->getAuthSessionId());
        }
    }

    abstract protected static function getRequiredAccessRights(): AccessRightCollection;

    #[\Override]
    public function execute(): void
    {
        if ($this->backendContext->authSession->isLoggedIn()) {
            $myAuthUser = $this->backendContext->getCurrentUser();
            $parentSessionID = $myAuthUser->parentSessionID;
            if (
                $parentSessionID !== null
                && $this->getInputString(keyName: BackendView::PARAM_CANCEL_SESSION_CHANGE) !== null
            ) {
                $impersonatedUserID = $myAuthUser->id;
                $this->backendContext->authSession->logIn(authSessionId: $parentSessionID);
                HttpResponse::redirectAndExit(
                    relativeOrAbsoluteUri: $this->backendContext->paths->user(ID: $impersonatedUserID),
                    httpRequest: $this->context->httpRequest,
                );
            }
        }
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
        $navigationItemCollection = $actraBackend->navigationItemCollection;
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
            $replacements->addHtml(
                identifier: 'cancelSessionChangeLink',
                html: '?' . BackendView::PARAM_CANCEL_SESSION_CHANGE,
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
     * The path variables of the request as list (`subscription-42.html` → `['subscription', '42']`).
     *
     * @return list<string>
     */
    private function listPathVars(): array
    {
        $pathVars = [];
        for ($nr = 0; ($value = $this->context->pathVars->get(nr: $nr)) !== null; $nr++) {
            $pathVars[] = $value;
        }

        return $pathVars;
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
                pathVars: $this->listPathVars(),
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
