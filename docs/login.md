# Login on a page of the project

The backend users can log in on pages of the project instead of the backend login page (`/backend/login.html`), e.g.
in the layout of the frontend: `ActraBackend::createLogin()` gives the steps of the login as `BackendLogin` for any
route with a session. The login is the same as on the backend pages (they use it too): login code by email, rate limit,
send limit, logged attempts, the code bound to the browser session that requested it.

| `BackendLogin::…`                            | Result                                                          |
|:---------------------------------------------|:----------------------------------------------------------------|
| `requestCode(email:)`                        | `SENT` (also for unknown or rejected addresses), `RATE_LIMITED` |
| `requestCodeWithPassword(email:, password:)` | `CODE_SENT`, `CREDENTIALS_INVALID`, `RATE_LIMITED`              |
| `logInWithCode(code:)`                       | `LOGGED_IN`, `INVALID` (wrong, expired, used, other session)    |
| `getCurrentUser()`                           | `?MyAuthUser` (`null` also outside the user's IP whitelist)     |
| `logOut()`                                   | Ends the login of the session                                   |
| `findReturnPath()`                           | The validated local path of `?returnTo=`, `null` without one    |

A user with a password gets no code with `requestCode()` (answered with `SENT`, logged as `ERROR_NO_PASSWORD`): log
in with `requestCodeWithPassword()`, which sends the code as second factor, then `logInWithCode()`. The texts of the
backend in the language of the request are in `$login->context->messages` (`auth`, `common`).
`BackendLogin::createNextStepUri(path:, returnPath:, loginRequestId:)` adds the return path (and the login request of a
site, see below) to the path of the next step.

## Login target

Send visitors without login (yuf's `UnauthorizedAccessRightException`, also on backend pages) to the page of the
project; they come back to the requested page afterwards:

```php
$actraBackend->configure(app: $app, loginTarget: LoginTarget::local(path: '/login.html'))->run();
```

Without `loginTarget:` it is the login page of the backend, which stays available either way. With `Core`: pass it as
`RouteCollection(loginTarget:)`.

## One login host for several sites

Sites on one server can share the login page of one host (yuf's `LoginHandOver`, "Login on another host" in yuf's
`docs/session-and-login.md`). The login host and the sites share a directory, outside the document roots and writable
only by the user of the web server: whoever can write it can log in on every site. The URLs carry only random IDs; the
requested page stays on the site. The subject of the hand-over is the user ID, so all hosts share the user database
(`ActraBackend::create(dbSettings:)`).

```php
$loginHandOver = new LoginHandOver(directory: '/var/www/shared/login-hand-over');

// The login host: the sites it logs in
new ActraBackendSettings(/* … */, loginHandOver: new LoginHandOverSettings(
    loginHandOver: $loginHandOver,
    allowedHosts: ['site-a.example.com', 'www.example.net'],
));

// Every site: no allowed hosts, visitors without login go to the login host
new ActraBackendSettings(/* … */, loginHandOver: new LoginHandOverSettings(
    loginHandOver: $loginHandOver,
    allowedHosts: [],
));
$actraBackend->configure(
    app: $app,
    loginTarget: $actraBackend->createRemoteLoginTarget(url: 'https://login.example.net/backend/login.html'),
)->run();
```

1. **Site:** a visitor without login is redirected to the login page with `?loginRequest=<ID>`.
2. **Login host:** the backend login pages carry the request through all steps (password, code) in a hidden field
   (only on a login host; checked with `BackendLogin::findLoginRequest()`). After the login they redirect to the
   hand-over page of the site (`findHandOverUrl()`); a user who is logged in on the login host already is sent there at
   once. Without a valid request (unknown, expired, used, other browser, host not allowed) the login stays local.
3. **Site:** the hand-over page of the backend (`/backend/loginTicket.html`, `BackendPaths::loginTicket()`) redeems the
   ticket (`BackendLogin::logInWithTicket()`, logged as `SUCCESSFUL_SSO_LOGIN`) and redirects to the requested page,
   with the session cookie as `SameSite=Lax` for this response (the browser comes from another site). Without a valid
   ticket it shows a link to the login page of the backend on the site, no redirect.

A login page of the project on the login host does the same with `BackendLogin`: `LoginForm` and `LoginTokenForm` keep
the request (`getLoginRequestId()`), `BackendLogin::createNextStepUri()` passes it to the next step, and after the login
`$login->findHandOverUrl(myAuthUser:, loginRequestId:)` gives the URL to redirect to (`null`: a normal login). The
global IP whitelist of the backend applies to the hand-over page, as to every backend page.

## Example: email page and code page

`LoginForm` and `LoginTokenForm` render yuf's form markup (`form`, `dl`, `div.form-control`) with the texts of the
backend and keep the requested page in a hidden field (forms post to `?<form name>`, which drops the query). The views
get the `ActraBackend` through yuf's `ClassNameViewFactory` (`create:` closure, yuf's `docs/views.md`).

```php
// app/view/frontend/php/login.php: the email address
final class login extends BaseView
{
    public function __construct(ViewContext $context, private readonly ActraBackend $actraBackend)
    {
        parent::__construct(
            context: $context,
            requiredViewGroupName: 'frontend',
            ipWhitelist: [],
            authUser: null,
            requiredAccessRights: AccessRightCollection::createEmpty(),
            inputParameterCollection: new InputParameterCollection(),
        );
    }

    public function execute(): void
    {
        $login = $this->actraBackend->createLogin(viewContext: $this->context);
        if ($login->getCurrentUser() !== null) {
            $this->redirect(uri: $login->findReturnPath() ?? '/');
        }
        $loginForm = new LoginForm(login: $login);
        if ($loginForm->process()) {
            $this->redirect(uri: BackendLogin::createNextStepUri(
                path: '/login_code.html',
                returnPath: $loginForm->getReturnPath(),
                loginRequestId: $loginForm->getLoginRequestId(),
            ));
        }
        // yuf renders the content file login.html in the layout of the project
        $this->getHtmlDocument()->replacements->addHtml(identifier: 'form', html: $loginForm->render());
    }

    private function redirect(string $uri): never
    {
        HttpResponse::redirectAndExit(
            relativeOrAbsoluteUri: $uri,
            httpRequest: $this->context->httpRequest,
            responseSender: $this->context->responseSender,
        );
    }
}
```

```php
// app/view/frontend/php/login_code.php: the code; same constructor and redirect()
public function execute(): void
{
    $loginTokenForm = new LoginTokenForm(login: $this->actraBackend->createLogin(viewContext: $this->context));
    if ($loginTokenForm->process() !== null) {
        // Validated local path: a frontend or a backend page
        $this->redirect(uri: $loginTokenForm->getReturnPath() ?? '/');
    }
    $this->getHtmlDocument()->replacements->addHtml(identifier: 'form', html: $loginTokenForm->render());
}
```

Never redirect to the raw `returnTo` value: use `getReturnPath()` or `findReturnPath()` (open redirect).

Pages of the project that need a login pass the user to `BaseView` (`authUser: $login->getCurrentUser()`) with their
required rights; without login they redirect to the login path.

With own form markup, call the steps of `BackendLogin` directly and keep the requested page yourself: a hidden field
`returnTo`, checked with yuf's `LoginRedirect::isLocalPath()`. Answer `SENT` and `RATE_LIMITED` with different texts
only; never tell whether an address exists.
