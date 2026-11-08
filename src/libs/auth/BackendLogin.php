<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\BackendViewContext;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\LoginHandOver;
use actra\yuf\auth\LoginRequest;
use actra\yuf\auth\SharedLogin;
use actra\yuf\auth\SharedLoginUser;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\LoginRedirect;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\exception\NotFoundException;
use actra\yuf\exception\UnauthorizedException;
use LogicException;

/**
 * The steps of the login of backend users, for the login pages of the backend and of a project on any route with a
 * session (`ActraBackend::createLogin()`): request a login code by email (or with the password), log in with the code,
 * log out. The code is bound to the browser session that requested it. Hosts of the same server share the login
 * (yuf's `SharedLogin`, `checkSharedLogin()`, `redirectAfterLogin()`) or a login host hands it over to sites (yuf's
 * `LoginHandOver`), see `docs/login.md`. Texts for own pages: `$login->context->messages->auth`.
 */
final readonly class BackendLogin
{
    /**
     * @internal Projects use `ActraBackend::createLogin()`
     */
    public function __construct(public BackendViewContext $context) {}

    /**
     * Sends a login code to a user who may log in without password: active, with rights, from the user's IP whitelist,
     * not out tried, without password (a user with password uses `requestCodeWithPassword()`). Every rejection is
     * logged and answered with `SENT` as well, so the answer does not tell whether the address exists. Above the
     * `TokenSendLimit` nothing is sent; the code sent before stays valid.
     */
    public function requestCode(string $email): LoginCodeRequestResultEnum
    {
        $myAuthenticator = new MyAuthenticator(context: $this->context);
        $myAuthUser = $myAuthenticator->findTokenLoginUser(email: $email);
        // The rate limit counts unknown addresses the same, so it does not tell whether one exists
        if ($myAuthenticator->authResult === AuthResultEnum::ERROR_RATE_LIMITED) {
            return LoginCodeRequestResultEnum::RATE_LIMITED;
        }
        if ($myAuthUser !== null) {
            new AuthTokens(context: $this->context)->createAndSend(
                type: AuthTokenTypeEnum::LOGIN,
                dbAuthUser: $myAuthUser->dbAuthUser,
                usedPasswordLogin: false,
            );
        }

        return LoginCodeRequestResultEnum::SENT;
    }

    /**
     * Checks the password and sends a login code (second factor): the login is complete with `logInWithCode()`.
     * Wrong passwords are counted per user (`ActraBackendSettings::$maxAllowedLoginAttempts`) and logged.
     */
    public function requestCodeWithPassword(
        string $email,
        #[\SensitiveParameter]
        string $password,
    ): PasswordLoginResultEnum {
        $result = new MyAuthenticator(context: $this->context)->verifyPassword(
            userName: $email,
            inputPassword: $password,
        );
        if (!$result instanceof MyAuthUser) {
            return $result === AuthResultEnum::ERROR_RATE_LIMITED
                ? PasswordLoginResultEnum::RATE_LIMITED
                : PasswordLoginResultEnum::CREDENTIALS_INVALID;
        }
        new AuthTokens(context: $this->context)->createAndSend(
            type: AuthTokenTypeEnum::LOGIN,
            dbAuthUser: $result->dbAuthUser,
            usedPasswordLogin: true,
        );

        return PasswordLoginResultEnum::CODE_SENT;
    }

    /**
     * Logs in with the code sent to this browser session (`requestCode()`, `requestCodeWithPassword()`). A code is
     * valid once, for its expiration time (`AuthTokenTypeEnum::getExpirationInMinutes()`); after
     * `ActraBackendSettings::$maxAllowedLoginAttempts` wrong codes the session needs a new one.
     */
    public function logInWithCode(#[\SensitiveParameter] string $code): LoginCodeResultEnum
    {
        if (new MyAuthenticator(context: $this->context)->tokenLogin(inputToken: $code) === null) {
            return LoginCodeResultEnum::INVALID;
        }
        $this->storeNewSharedLoginId();

        return LoginCodeResultEnum::LOGGED_IN;
    }

    /**
     * The logged-in user (one query per call, also right after `logInWithCode()`), `null` without login or if the
     * request comes from outside the user's IP whitelist. Check the rights of the page with the user
     * (`hasOneOfRights()`).
     */
    public function getCurrentUser(): ?MyAuthUser
    {
        $context = $this->context;
        $myAuthUser = MyAuthUser::findLoggedIn(
            authSession: $context->authSession,
            repositories: $context->repositories,
            clientData: $context->clientData,
        );
        if ($myAuthUser === null) {
            return null;
        }
        try {
            LoggedInUserCheck::check(context: $context, myAuthUser: $myAuthUser);
        } catch (UnauthorizedException) {
            return null;
        }

        return $myAuthUser;
    }

    /**
     * Ends the login of the session; with a shared login (`ActraBackendSettings::$sharedLogin`) also on the other hosts
     * (they log out at their next request).
     */
    public function logOut(): void
    {
        $sharedLogin = $this->context->actraBackend->actraBackendSettings->sharedLogin?->sharedLogin;
        $sharedLoginId = SharedLoginSession::find(session: $this->context->session);
        if ($sharedLogin !== null && $sharedLoginId !== null && $this->context->authSession->isLoggedIn()) {
            $sharedLogin->end(sharedLoginId: $sharedLoginId);
        }
        $this->context->authSession->logOut();
    }

    /**
     * Site with a shared login (`SharedLoginSettings::$site`), on its login page (GET, without login): checks once per
     * browser session at the login host whether the visitor is logged in there (invisible redirects, the visitor
     * returns logged in to the requested page, or to the login page of the site). Redirects and ends the script if the
     * check is due, returns otherwise (no shared login, logged in, a sent form, or checked already).
     *
     * @param string $defaultReturnPath The page after a login on the login host if the request has no return path
     */
    public function checkSharedLogin(string $defaultReturnPath = '/'): void
    {
        $settings = $this->context->actraBackend->actraBackendSettings->sharedLogin;
        $httpRequest = $this->context->viewContext->httpRequest;
        if (
            $settings === null
            || $settings->site === null
            || $this->context->authSession->isLoggedIn()
            || $httpRequest->getMethod() !== RequestMethodEnum::GET
            || $settings->sharedLogin->isChecked(httpRequest: $httpRequest)
        ) {
            return;
        }
        $this->redirect(
            uri: $settings->sharedLogin->createCheckUrl(
                httpRequest: $httpRequest,
                site: $settings->site,
                returnPath: $this->findReturnPath() ?? $defaultReturnPath,
            ),
            withLaxSessionCookie: false,
        );
    }

    /**
     * The hand-over page of the shared login (backend page `loginShared`, on the login host and on the sites): runs a
     * step of yuf's `SharedLogin` (`receiveOnSite()`, `receiveOnLoginHost()`), logs in its user (`AuthMethodEnum::SSO`,
     * logged; a login of the session before is ended on this host) and redirects to the next step. Refused steps are
     * logged (`ActraBackend::create(logger:)`); on a site they lead to its login page.
     *
     * @throws NotFoundException without `ActraBackendSettings::$sharedLogin`
     * @throws UnauthorizedException on the login host for a bad step (yuf's `receiveOnLoginHost()`) or a user who may
     *     not log in
     */
    public function handOverSharedLogin(): never
    {
        $settings = $this->context->actraBackend->actraBackendSettings->sharedLogin ?? throw new NotFoundException();
        $httpRequest = $this->context->viewContext->httpRequest;
        $site = $settings->site;
        $step = $site === null
            ? $settings->sharedLogin->receiveOnLoginHost(
                httpRequest: $httpRequest,
                allowedHosts: $settings->allowedHosts,
                user: $this->findSharedLoginUser(),
            )
            : $settings->sharedLogin->receiveOnSite(httpRequest: $httpRequest, site: $site);
        if ($step->failure !== null) {
            $this->context->actraBackend->logMessage(message: 'Shared login: ' . $step->failure);
        }
        $redirectUrl = $step->redirectUrl;
        if ($step->logIn !== null && !$this->logInSharedUser(sharedLoginUser: $step->logIn)) {
            if ($site === null) {
                throw new UnauthorizedException();
            }
            // The user may not log in here (e.g. inactive, IP whitelist): the login page, without a new check
            $redirectUrl = $site->loginPath . (str_contains(haystack: $site->loginPath, needle: '?') ? '&' : '?')
                . SharedLogin::CHECKED_PARAMETER . '=1';
        }
        $this->redirect(uri: $redirectUrl, withLaxSessionCookie: true);
    }

    /**
     * The page requested before the login (`?returnTo=` of yuf's login redirect) if it is a local path, `null`
     * otherwise. Forms post to `?<form name>` and drop the query: the forms of the login keep it in a hidden field
     * (`LoginForm::getReturnPath()`).
     */
    public function findReturnPath(): ?string
    {
        return LoginRedirect::findReturnPath(httpRequest: $this->context->viewContext->httpRequest);
    }

    /**
     * Whether this backend is the login host of other sites (`LoginHandOverSettings::$allowedHosts`).
     */
    public function isLoginHost(): bool
    {
        return ($this->context->actraBackend->actraBackendSettings->loginHandOver->allowedHosts ?? []) !== [];
    }

    /**
     * Login host: the valid login request of a site (`?loginRequest=`, yuf's `LoginHandOver::findLoginRequest()`) for
     * one of the allowed hosts (`LoginHandOverSettings::$allowedHosts`), `null` without one (a normal login). The first
     * call binds the request to this browser. The forms of the login keep its ID in a hidden field
     * (`LoginForm::getLoginRequestId()`).
     */
    public function findLoginRequest(): ?LoginRequest
    {
        $settings = $this->context->actraBackend->actraBackendSettings->loginHandOver;
        $httpRequest = $this->context->viewContext->httpRequest;
        $requestId = $httpRequest->getQueryString(name: LoginHandOver::REQUEST_PARAMETER);
        if ($settings === null || $settings->allowedHosts === [] || $requestId === null) {
            return null;
        }

        return $settings->loginHandOver->findLoginRequest(
            httpRequest: $httpRequest,
            requestId: $requestId,
            allowedHosts: $settings->allowedHosts,
        );
    }

    /**
     * Login host, after the login: the URL of the hand-over page of the site that sent the login request, with a ticket
     * for the user (yuf's `LoginHandOver::createHandOverUrl()`, the subject is the user ID); `null` without login
     * request or if it is no longer valid: continue as after a normal login.
     */
    private function findHandOverUrl(MyAuthUser $myAuthUser, ?string $loginRequestId): ?string
    {
        $settings = $this->context->actraBackend->actraBackendSettings->loginHandOver;
        if ($settings === null || $settings->allowedHosts === [] || $loginRequestId === null) {
            return null;
        }
        try {
            return $settings->loginHandOver->createHandOverUrl(
                httpRequest: $this->context->viewContext->httpRequest,
                requestId: $loginRequestId,
                subject: (string) $myAuthUser->id,
                allowedHosts: $settings->allowedHosts,
            );
        } catch (UnauthorizedException) {
            return null;
        }
    }

    /**
     * The redirect of the login pages after the login (also of the project): on a login host to the hand-over page of
     * the site of a login request (yuf's `LoginHandOver`), else to the requested page, else to the first page of the
     * backend navigation. On a site with a shared login, the login is first pushed to the login host (invisible
     * redirects, yuf's `SharedLogin::createPushUrl()`); with a shared login the session cookie is sent as `Lax`, so the
     * hosts see the login on a request that comes from another host. After a form (POST), the way to another host
     * passes the hand-over page of this host (`LoginContinuation`, CSP `form-action`).
     *
     * @param ?string $returnPath The validated page requested before the login (`LoginTokenForm::getReturnPath()`),
     *     e.g. `?? '/'` on a login page of the project; `null`: the first page of the backend navigation
     */
    public function redirectAfterLogin(MyAuthUser $myAuthUser, ?string $loginRequestId, ?string $returnPath): never
    {
        $this->redirectLoggedIn(
            myAuthUser: $myAuthUser,
            loginRequestId: $loginRequestId,
            returnPath: $returnPath,
            pushSharedLogin: true,
        );
    }

    /**
     * The redirect of a login page for a visitor who is logged in already: like `redirectAfterLogin()`, without pushing
     * the login to the login host again.
     */
    public function redirectLoggedInUser(MyAuthUser $myAuthUser, ?string $loginRequestId, ?string $returnPath): never
    {
        $this->redirectLoggedIn(
            myAuthUser: $myAuthUser,
            loginRequestId: $loginRequestId,
            returnPath: $returnPath,
            pushSharedLogin: false,
        );
    }

    private function redirectLoggedIn(
        MyAuthUser $myAuthUser,
        ?string $loginRequestId,
        ?string $returnPath,
        bool $pushSharedLogin,
    ): never {
        $settings = $this->context->actraBackend->actraBackendSettings->sharedLogin;
        $handOverUrl = $this->findHandOverUrl(myAuthUser: $myAuthUser, loginRequestId: $loginRequestId);
        if ($handOverUrl !== null) {
            $this->redirectToOtherHost(url: $handOverUrl, withLaxSessionCookie: $settings !== null);
        }
        $uri = $myAuthUser->getFirstAllowedPage(context: $this->context, returnPath: $returnPath);
        if ($pushSharedLogin && $settings !== null && $settings->site !== null) {
            $pushUrl = $settings->sharedLogin->createPushUrl(
                httpRequest: $this->context->viewContext->httpRequest,
                site: $settings->site,
                user: new SharedLoginUser(
                    subject: (string) $myAuthUser->id,
                    sharedLoginId: SharedLoginSession::find(session: $this->context->session)
                        ?? $this->storeNewSharedLoginId(),
                ),
                returnPath: $uri,
            );
            $this->redirectToOtherHost(url: $pushUrl, withLaxSessionCookie: true);
        }
        $this->redirect(uri: $uri, withLaxSessionCookie: $settings !== null);
    }

    /**
     * A GET redirects at once. The response to a form (POST) redirects to the hand-over page of this host, which
     * continues to the URL: browsers block a redirect to another host after a form (CSP `form-action 'self'`, also for
     * the later redirects of the chain), see `LoginContinuation`.
     */
    private function redirectToOtherHost(string $url, bool $withLaxSessionCookie): never
    {
        $context = $this->context;
        if ($context->viewContext->httpRequest->getMethod() !== RequestMethodEnum::GET) {
            $url = $context->paths->loginShared() . '?' . LoginContinuation::PARAMETER . '='
                . LoginContinuation::store(session: $context->session, url: $url);
        }
        $this->redirect(uri: $url, withLaxSessionCookie: $withLaxSessionCookie);
    }

    /**
     * Site, on the hand-over page (`?loginTicket=`): redeems the ticket of the login host (yuf's
     * `LoginHandOver::redeemTicket()`) and logs its user in (`AuthMethodEnum::SSO`, logged); a login of the session
     * before is ended. Redirect to the returned path with the session cookie as `Lax`
     * (`HttpResponse::redirectAndExit(sameSiteLaxSessionHandler:)`).
     *
     * @return string The local path requested on the site before the login
     *
     * @throws UnauthorizedException if the ticket is missing, unknown, used, expired, for another host or of another
     *     browser, or the user may not log in
     * @throws LogicException without `ActraBackendSettings::$loginHandOver`
     */
    public function logInWithTicket(): string
    {
        $settings = $this->context->actraBackend->actraBackendSettings->loginHandOver ?? throw new LogicException(
            message: 'The hand-over of a login needs ActraBackendSettings::$loginHandOver.',
        );
        $ticket = $settings->loginHandOver->redeemTicket(httpRequest: $this->context->viewContext->httpRequest);
        $this->context->authSession->logOut();
        if (new MyAuthenticator(context: $this->context)->handOverLogin(subject: $ticket->subject) === null) {
            throw new UnauthorizedException();
        }
        $this->storeNewSharedLoginId();

        return $ticket->returnPath;
    }

    /**
     * Login host: its login as user of the shared login, `null` without login (or before the shared login ID is set).
     */
    private function findSharedLoginUser(): ?SharedLoginUser
    {
        $myAuthUser = $this->getCurrentUser();
        $sharedLoginId = SharedLoginSession::find(session: $this->context->session);

        return $myAuthUser === null || $sharedLoginId === null
            ? null
            : new SharedLoginUser(subject: (string) $myAuthUser->id, sharedLoginId: $sharedLoginId);
    }

    /**
     * Logs in the user of a shared login step with its shared login ID; a login of the session before ends on this host
     * only. `false` if the user may not log in (unknown, inactive, IP whitelist, …; logged).
     */
    private function logInSharedUser(SharedLoginUser $sharedLoginUser): bool
    {
        $this->context->authSession->logOut();
        if (new MyAuthenticator(context: $this->context)->handOverLogin(subject: $sharedLoginUser->subject) === null) {
            return false;
        }
        SharedLoginSession::store(session: $this->context->session, sharedLoginId: $sharedLoginUser->sharedLoginId);

        return true;
    }

    /**
     * A login on this host (not handed over) gets a new shared login ID, if the backend has a shared login.
     */
    private function storeNewSharedLoginId(): string
    {
        $sharedLoginId = SharedLogin::createSharedLoginId();
        if ($this->context->actraBackend->actraBackendSettings->sharedLogin !== null) {
            SharedLoginSession::store(session: $this->context->session, sharedLoginId: $sharedLoginId);
        }

        return $sharedLoginId;
    }

    private function redirect(string $uri, bool $withLaxSessionCookie): never
    {
        $viewContext = $this->context->viewContext;
        HttpResponse::redirectAndExit(
            relativeOrAbsoluteUri: $uri,
            httpRequest: $viewContext->httpRequest,
            sameSiteLaxSessionHandler: $withLaxSessionCookie ? $viewContext->sessionHandler : null,
            responseSender: $viewContext->responseSender,
        );
    }

    /**
     * The path of the next step of the login (`/login_code.html`) with the return path and the login request in the
     * query, so the next page finds them (`findReturnPath()`, `findLoginRequest()`).
     */
    public static function createNextStepUri(string $path, ?string $returnPath, ?string $loginRequestId): string
    {
        $uri = $returnPath === null
            ? $path
            : LoginRedirect::createLoginUri(loginPath: $path, returnUri: $returnPath);

        return $loginRequestId === null ? $uri : $uri . (str_contains(haystack: $uri, needle: '?') ? '&' : '?')
            . LoginHandOver::REQUEST_PARAMETER . '=' . rawurlencode(string: $loginRequestId);
    }
}
