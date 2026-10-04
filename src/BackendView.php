<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend;

use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\common\LanguageSwitcher;
use actra\backend\libs\common\OldNavigator;
use actra\backend\libs\db\DbAuthSessionRepository;
use actra\backend\view\backend\php\login;
use actra\backend\view\backend\php\logout;
use actra\backend\view\backend\php\profile;
use actra\backend\view\backend\php\user;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\AuthSession;
use actra\yuf\auth\UnauthorizedAccessRightException;
use actra\yuf\core\BaseView;
use actra\yuf\core\ContentHandler;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\InputParameter;
use actra\yuf\core\InputParameterCollection;
use actra\yuf\core\RequestHandler;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlText;

abstract class BackendView extends BaseView
{
    public const string PARAM_FROM_LOGIN = 'fromLogin';
    public const string PARAM_CANCEL_SESSION_CHANGE = 'cancelSessionChange';

    /**
     * @param array<int, string> $activeHtmlIdList
     */
    public function __construct(
        bool $forceLogout = false,
        InputParameterCollection $inputParameterCollection = new InputParameterCollection(),
        string $requiredViewGroupName = ActraBackend::viewGroup,
        int $maxAllowedPathVars = 0,
        private readonly array $activeHtmlIdList = [],
        private readonly bool $useNavigator = false,
        private readonly bool $resetNavigator = false,
        private readonly string $legacyBreadcrumbSeparator = ' '
    ) {
        // Texts and links of this request follow the language of its route
        ActraBackend::get()->activateRoute(route: RequestHandler::get()->route);
        if ($forceLogout) {
            AuthSession::logOut();
        }
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: BackendView::PARAM_FROM_LOGIN,
                isRequired: false
            )
        );
        $inputParameterCollection->add(
            inputParameter: new InputParameter(
                name: BackendView::PARAM_CANCEL_SESSION_CHANGE,
                isRequired: false
            )
        );
        $ipWhitelist = ActraBackend::get()->actraBackendSettings->ipWhitelist;
        if (AuthSession::isLoggedIn()) {
            try {
                $myAuthUser = MyAuthUser::get();
            } catch (UnauthorizedException) {
                $myAuthUser = null;
                AuthSession::logOut();
            }
        } else {
            $myAuthUser = null;
        }
        if ($myAuthUser !== null) {
            $parentSessionID = $myAuthUser->parentSessionID;
            if ($parentSessionID !== null) {
                $parentSession = DbAuthSessionRepository::selectByID(ID: $parentSessionID);
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
                    haystack: $ipWhitelist
                )) {
                    $ipWhitelist[] = $ipAddress;
                }
            }
        }
        try {
            parent::__construct(
                requiredViewGroupName: $requiredViewGroupName,
                ipWhitelist: $ipWhitelist,
                authUser: $myAuthUser,
                requiredAccessRights: static::getRequiredAccessRights(),
                inputParameterCollection: $inputParameterCollection,
                maxAllowedPathVars: $maxAllowedPathVars
            );
        } catch (UnauthorizedAccessRightException $unauthorizedAccessRightException) {
            if (
                $myAuthUser === null
                && $this->getInputString(keyName: BackendView::PARAM_FROM_LOGIN) === null
                && ContentHandler::get()->getContentType()->isHtml()
            ) {
                MyAuthUser::setRequestedPageAfterLogin(path: HttpRequest::getURI());
                HttpResponse::redirectAndExit(relativeOrAbsoluteUri: login::getPath());
            }
            throw $unauthorizedAccessRightException;
        }
        if ($myAuthUser !== null) {
            DbAuthSessionRepository::updateLastAction(ID: AuthSession::getAuthSessionID());
        }
    }

    abstract protected static function getRequiredAccessRights(): AccessRightCollection;

    public function execute(): void
    {
        if (AuthSession::isLoggedIn()) {
            $myAuthUser = MyAuthUser::get();
            $parentSessionID = $myAuthUser->parentSessionID;
            if (
                $parentSessionID !== null
                && $this->getInputString(keyName: BackendView::PARAM_CANCEL_SESSION_CHANGE) !== null
            ) {
                $impersonatedUserID = $myAuthUser->ID;
                AuthSession::logIn(authSessionID: $parentSessionID);
                HttpResponse::redirectAndExit(relativeOrAbsoluteUri: user::getPath(ID: $impersonatedUserID));
            }
        }
        $actraBackend = ActraBackend::get();
        $actraBackendSettings = $actraBackend->actraBackendSettings;
        $htmlDocument = HtmlDocument::get();
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
            htmlText: $this->getPageTitle()
        );
        $replacements->addEncodedText(
            identifier: 'backendTitle',
            content: strip_tags(string: $actraBackendSettings->backendName)
        );
        $replacements->addEncodedText(
            identifier: 'backendName',
            content: $actraBackendSettings->backendName
        );
        $replacements->addEncodedText(
            identifier: 'frontendHref',
            content: $actraBackendSettings->frontendHref
        );
        $replacements->addEncodedText(
            identifier: 'frontendName',
            content: $actraBackendSettings->frontendName
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'javaScriptPaths',
            htmlDataObjectCollection: $actraBackend->renderJavaScriptPaths()
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'stylesPaths',
            htmlDataObjectCollection: $actraBackend->renderStylesPaths()
        );
        $this->renderLegacyBreadcrumb(
            htmlDocument: $htmlDocument
        );
        if (!AuthSession::isLoggedIn()) {
            $replacements->addEncodedText(
                identifier: 'firstPageHref',
                content: login::getPath()
            );
            $replacements->addHtmlDataObjectCollection(
                identifier: 'mainNavigation',
                htmlDataObjectCollection: null
            );
            $replacements->addBool(
                identifier: 'isLoggedIn',
                booleanValue: false
            );
            return;
        }
        $myAuthUser = MyAuthUser::get();
        $accessRightCollection = $myAuthUser->dbAuthUser->accessRightCollection;
        $navigationItemCollection = $actraBackend->navigationItemCollection;
        $firstNavigationItem = $navigationItemCollection->getFirst(accessRightCollection: $accessRightCollection);
        if ($firstNavigationItem === null) {
            throw new UnauthorizedException();
        }
        $replacements->addEncodedText(
            identifier: 'firstPageHref',
            content: $firstNavigationItem->href
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'mainNavigation',
            htmlDataObjectCollection: $navigationItemCollection->prepareForRenderer(
                activeSubNavigationItem: $htmlDocument->isActiveHtmlIdSet(key: 1) ? $htmlDocument->getActiveHtmlId(
                    key: 1
                ) : '',
                accessRightCollection: $accessRightCollection
            )
        );
        $replacements->addBool(
            identifier: 'isLoggedIn',
            booleanValue: true
        );
        $replacements->addUnencodedText(
            identifier: 'userName',
            content: $myAuthUser->getUserName()
        );
        if ($myAuthUser->isSessionChange()) {
            $replacements->addEncodedText(
                identifier: 'cancelSessionChangeLink',
                content: '?' . BackendView::PARAM_CANCEL_SESSION_CHANGE
            );
        } else {
            $replacements->addEncodedText(
                identifier: 'cancelSessionChangeLink',
                content: ''
            );
        }
        $replacements->addEncodedText(
            identifier: 'profileHref',
            content: profile::getPath()
        );
        $replacements->addEncodedText(
            identifier: 'logoutHref',
            content: logout::getPath()
        );
        $replacements->addBool(
            identifier: 'hasApi',
            booleanValue: $actraBackendSettings->hasApi
        );
    }

    private function addLayoutTexts(HtmlReplacementCollection $replacements): void
    {
        $messages = ActraBackend::messages();
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
            backendRouteCollection: ActraBackend::get()->backendRouteCollection,
            currentRoute: ActraBackend::get()->currentRoute,
            currentUri: HttpRequest::getURI()
        );
        $replacements->addBool(identifier: 'hasLanguageSwitcher', booleanValue: $languageSwitcher->isAvailable());
        $replacements->addHtmlDataObjectCollection(
            identifier: 'languageSwitcher',
            htmlDataObjectCollection: $languageSwitcher->render()
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
                htmlText: HtmlText::unencoded(textContent: $text)
            );
        }
    }

    abstract protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void;

    abstract protected function getPageTitle(): HtmlText;

    private function renderLegacyBreadcrumb(HtmlDocument $htmlDocument): void
    {
        if ($this->useNavigator) {
            $oldNavigator = new OldNavigator(
                pathVars: RequestHandler::get()->pathVars,
                navigationLevels: $htmlDocument->listActiveHtmlIds(),
                separator: $this->legacyBreadcrumbSeparator
            );
            if ($this->resetNavigator) {
                $oldNavigator->resetBreadcrumb();
            }
            $oldNavigator->addBreadcrumb(title: $this->getPageTitle()->render());
            foreach ($oldNavigator->setNavistufe() as $key => $val) {
                if (is_int(value: $key) && is_string(value: $val)) {
                    $htmlDocument->setActiveHtmlId(key: $key, val: $val);
                }
            }
            $breadcrumb = $oldNavigator->getBreadcrumb();
        } else {
            $breadcrumb = null;
        }
        $htmlDocument->replacements->addEncodedText(
            identifier: 'breadcrumb',
            content: $breadcrumb
        );
    }
}