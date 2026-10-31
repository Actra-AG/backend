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
use actra\backend\libs\common\DateFormatter;
use actra\backend\libs\db\BackendRepositories;
use actra\backend\libs\db\ClientData;
use actra\backend\libs\email\Mailer;
use actra\yuf\auth\AuthSession;
use actra\yuf\core\Language;
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
    /**
     * @param Language $language The language of the request (`ActraBackend::getLanguageOfRequest()`)
     * @param BackendMessages $messages The texts in the language of the request
     * @param DateFormatter $dateFormatter Dates in the locale of the language of the request
     * @param BackendPaths $paths The links to the backend pages
     * @param ?MyAuthUser $currentUser The logged-in user, `null` without login
     */
    public function __construct(
        public ViewContext $viewContext,
        public ActraBackend $actraBackend,
        public Language $language,
        public BackendMessages $messages,
        public DateFormatter $dateFormatter,
        public BackendPaths $paths,
        public BackendRepositories $repositories,
        public Mailer $mailer,
        public Session $session,
        public AuthSession $authSession,
        public ClientData $clientData,
        public ?MyAuthUser $currentUser,
        public UserController $userController,
    ) {}

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
     * The navigation of the request (`ActraBackend::createNavigationProvider()` as yuf's navigation provider).
     *
     * @throws LogicException without navigation provider
     */
    public function getNavigation(): NavigationItemCollection
    {
        return $this->viewContext->getNavigation() ?? throw new LogicException(
            message: 'The backend needs a navigation: configure the App with $actraBackend->configure(), or pass '
                . '$actraBackend->createNavigationProvider() as navigationProvider to Core::prepareHttpResponse().',
        );
    }
}
