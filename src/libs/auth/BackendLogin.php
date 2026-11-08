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
use actra\yuf\core\HttpResponse;
use actra\yuf\core\LoginRedirect;
use actra\yuf\exception\UnauthorizedException;
use LogicException;

/**
 * The steps of the login of backend users, for the login pages of the backend and of a project on any route with a
 * session (`ActraBackend::createLogin()`): request a login code by email (or with the password), log in with the code,
 * log out. The code is bound to the browser session that requested it. On a login host, the login is handed over to
 * sites on other hosts of the same server (yuf's `LoginHandOver`, `docs/login.md`). Texts for own pages:
 * `$login->context->messages->auth`.
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
        return new MyAuthenticator(context: $this->context)->tokenLogin(inputToken: $code) === null
            ? LoginCodeResultEnum::INVALID
            : LoginCodeResultEnum::LOGGED_IN;
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

    public function logOut(): void
    {
        $this->context->authSession->logOut();
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
    public function findHandOverUrl(MyAuthUser $myAuthUser, ?string $loginRequestId): ?string
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
     * The redirect of the login pages of the backend after the login: to the hand-over page of the site of a login
     * request (`findHandOverUrl()`), else to the requested page, else to the first page of the backend navigation.
     */
    public function redirectAfterLogin(MyAuthUser $myAuthUser, ?string $loginRequestId, ?string $returnPath): never
    {
        $handOverUrl = $this->findHandOverUrl(myAuthUser: $myAuthUser, loginRequestId: $loginRequestId);
        if ($handOverUrl !== null) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $handOverUrl,
                httpRequest: $this->context->viewContext->httpRequest,
                responseSender: $this->context->viewContext->responseSender,
            );
        }
        $myAuthUser->redirectToFirstAllowedPage(context: $this->context, returnPath: $returnPath);
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
        $this->logOut();
        if (new MyAuthenticator(context: $this->context)->handOverLogin(subject: $ticket->subject) === null) {
            throw new UnauthorizedException();
        }

        return $ticket->returnPath;
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
