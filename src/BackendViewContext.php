<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend;

use actra\backend\i18n\BackendMessages;
use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\auth\UserController;
use actra\backend\libs\db\BackendRepositories;
use actra\backend\libs\db\ClientData;
use actra\backend\libs\email\Mailer;
use actra\backend\settings\BackendRoute;
use actra\yuf\auth\AuthSession;
use actra\yuf\core\ViewContext;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\layout\NavigationItemCollection;
use actra\yuf\session\Session;
use LogicException;

/**
 * What every view based on `BackendView` receives: the `ViewContext` of yuf and the services of the backend for the
 * request (created by `ActraBackend::createContext()`).
 */
final readonly class BackendViewContext
{
    /** The texts of the backend route of the request */
    public BackendMessages $messages;

    /**
     * @param BackendRoute $route The backend route of the request (its language)
     * @param BackendPaths $paths The links to the backend pages on that route
     * @param ?MyAuthUser $currentUser The logged-in user, `null` without login
     */
    public function __construct(
        public ViewContext $viewContext,
        public ActraBackend $actraBackend,
        public BackendRoute $route,
        public BackendPaths $paths,
        public BackendRepositories $repositories,
        public Mailer $mailer,
        public Session $session,
        public AuthSession $authSession,
        public ClientData $clientData,
        public ?MyAuthUser $currentUser,
        public UserController $userController,
    ) {
        $this->messages = $route->messages;
    }

    /**
     * The logged-in user, for views and forms that require a login.
     *
     * @throws UnauthorizedException without login
     */
    public function getCurrentUser(): MyAuthUser
    {
        return $this->currentUser ?? throw new UnauthorizedException();
    }

    /**
     * The navigation of the request (`ActraBackend::createNavigation()` as yuf's navigation provider).
     *
     * @throws LogicException without navigation provider
     */
    public function getNavigation(): NavigationItemCollection
    {
        return $this->viewContext->getNavigation() ?? throw new LogicException(
            message: 'The backend needs a navigation: pass $actraBackend->createNavigation(...) as navigationProvider '
                . 'to Core::prepareHttpResponse().',
        );
    }
}
