<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend;

use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\common\OldNavigator;
use actra\backend\libs\db\DbAuthSessionRepository;
use actra\backend\view\backend\php\login;
use actra\backend\view\backend\php\logout;
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
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlText;

abstract class BackendView extends BaseView
{
    public const string PARAM_FROM_LOGIN = 'fromLogin';
    public const string PARAM_CANCEL_SESSION_CHANGE = 'cancelSessionChange';

    public function __construct(
        bool $forceLogout = false,
        InputParameterCollection $inputParameterCollection = new InputParameterCollection(),
        string $requiredViewGroupName = ActraBackend::viewGroup,
        int $maxAllowedPathVars = 0,
        private readonly array $activeHtmlIdList = [],
        private readonly bool $useNavigator = false,
        private readonly bool $resetNavigator = false
    ) {
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
        $myAuthUser = AuthSession::isLoggedIn() ? MyAuthUser::get() : null;
        try {
            parent::__construct(
                requiredViewGroupName: $requiredViewGroupName,
                ipWhitelist: ActraBackend::get()->ipWhitelist,
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

    public function execute(): void
    {
        if (AuthSession::isLoggedIn()) {
            $myAuthUser = MyAuthUser::get();
            if (
                $myAuthUser->isSessionChange()
                && $this->getInputString(keyName: BackendView::PARAM_CANCEL_SESSION_CHANGE) !== null
            ) {
                $impersonatedUserID = $myAuthUser->ID;
                AuthSession::logIn(authSessionID: $myAuthUser->parentSessionID);
                HttpResponse::redirectAndExit(relativeOrAbsoluteUri: user::getPath(ID: $impersonatedUserID));
            }
        }
        $htmlDocument = HtmlDocument::get();
        $htmlDocument->templateDirectory = __DIR__.'/view/backend/templates/';
        $this->prepareHtmlDocument(htmlDocument: $htmlDocument);
        foreach ($this->activeHtmlIdList as $key => $val) {
            $htmlDocument->setActiveHtmlId(key: $key, val: $val);
        }
        $replacements = $htmlDocument->replacements;
        $replacements->addHtmlText(
            identifier: 'pageTitle',
            htmlText: $this->getPageTitle()
        );
        $actraBackend = ActraBackend::get();
        $replacements->addEncodedText(
            identifier: 'siteName',
            content: $actraBackend->siteName
        );
        $replacements->addEncodedText(
            identifier: 'scriptsHref',
            content: $actraBackend->scriptsHref
        );
        $replacements->addEncodedText(
            identifier: 'stylesHref',
            content: $actraBackend->stylesHref
        );
        if (!AuthSession::isLoggedIn()) {
            return;
        }
        $myAuthUser = MyAuthUser::get();
        $navigationItemCollection = $actraBackend->navigationItemCollection;
        $replacements->addEncodedText(
            identifier: 'firstPageHref',
            content: $navigationItemCollection->getFirst(
                authUser: $myAuthUser
            )->href
        );
        $replacements->addHtmlDataObjectCollection(
            identifier: 'mainNavigation',
            htmlDataObjectCollection: $navigationItemCollection->prepareForRenderer(
                activeSubNavigationItem: array_key_exists(
                    key: 1,
                    array: $this->activeHtmlIdList
                ) ? $this->activeHtmlIdList[1] : '',
                authUser: $myAuthUser
            )
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
            identifier: 'logoutHref',
            content: logout::getPath()
        );
        if ($this->useNavigator) {
            $oldNavigator = new OldNavigator(
                pathVars: RequestHandler::get()->pathVars,
                navigationLevels: $this->activeHtmlIdList
            );
            if ($this->resetNavigator) {
                $oldNavigator->resetBreadcrumb();
            }
            $oldNavigator->addBreadcrumb(title: $this->getPageTitle()->render());
            foreach ($oldNavigator->setNavistufe() as $key => $val) {
                $htmlDocument->setActiveHtmlId(key: $key, val: $val);
            }
            $breadcrumb = $oldNavigator->getBreadcrumb();
        } else {
            $breadcrumb = null;
        }
        $replacements->addEncodedText(
            identifier: 'breadcrumb',
            content: $breadcrumb
        );
    }

    abstract protected static function getRequiredAccessRights(): AccessRightCollection;

    abstract protected function getPageTitle(): HtmlText;

    abstract protected function prepareHtmlDocument(HtmlDocument $htmlDocument): void;
}