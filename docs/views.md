# Project views, tables and search forms

Project views based on `BackendView` receive a `BackendViewContext`: the `ViewContext` of yuf
(`$this->context`) and the services of the backend (`$this->backendContext`). The routes of these views use the view
factory of the backend; other views on the same route keep getting the yuf `ViewContext`:

```php
use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\BackendViewContext;

new Route(
    path: '/de/orders/',
    viewDirectory: $core->viewDirectory,
    viewGroup: 'orders',
    viewFactory: $actraBackend->createViewFactory(), // $actraBackend from ActraBackend::init()
);

final class orders extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(context: $context, requiredViewGroupName: 'orders');
    }
}
```

Tables based on `AbstractTable` and search forms based on `AbstractSearchForm` get the same context as first
argument; the database of a table defaults to the database of the backend:

```php
new OrderTable(context: $this->backendContext);
new OrderSearchForm(context: $this->backendContext, name: 'OrderSearch');

// in OrderTable::__construct(BackendViewContext $context)
parent::__construct(context: $context, identifier: 'OrderTable', dbQuery: $dbQuery);
```

`BackendViewContext` (`$this->backendContext` in views, tables and search forms) holds the services of the request:

| Property / method | Content |
|:--|:--|
| `messages`, `route`, `paths` | Texts, backend route and links of the request language (`$paths->user(id: 5)`) |
| `repositories` | The repositories (`->users()`, `->groups()`, …, `->db()` for the database of the backend) |
| `currentUser`, `getCurrentUser()` | The logged-in `MyAuthUser` (`null` / `UnauthorizedException` without login) |
| `mailer` | Sends emails with the mailer of the `MailerSettings` (`->sendTextMail()`) |
| `getNavigation()` | The navigation of the request (`ActraBackend::createNavigation()`) |
| `session`, `authSession`, `viewContext` | yuf's session objects and `ViewContext` |
| `userController` | `->deleteUser(userId:)` |

Outside a request (CLI scripts), use `$actraBackend->getRepositories()` and
`$actraBackend->createMailer(responseSender: new NativeResponseSender())`.

The values of a search form come from the posted form (`reset` and `find` from the query string), and tables and
search forms keep their state in the session. New services of the backend are added to `BackendViewContext`, so these
constructors do not change again.

## Breadcrumb

With `useNavigator: true`, a view shows the pages visited before it as breadcrumb (kept in the session; `?reset` in a
link or `resetNavigator: true` restarts it). A page reached directly (bookmark, link in an email) then shows the trail
of whatever was visited before. A view that knows its parents declares them instead; they replace the trail, and the
trail restarts at this page for the views that follow:

```php
use actra\backend\libs\common\BreadcrumbItem;
use actra\backend\libs\common\BreadcrumbItemCollection;

protected function getBreadcrumbParents(): ?BreadcrumbItemCollection
{
    // called when the page is rendered, after prepareHtmlDocument() has loaded the subscription
    return new BreadcrumbItemCollection(
        new BreadcrumbItem(title: $this->event->title, href: $this->eventPath($this->event->id)),
        new BreadcrumbItem(
            title: '#' . $this->subscription->id,
            href: $this->subscriptionPath($this->subscription->id),
        ),
    );
}
```

Titles and links are plain text and escaped; the current page is the page title of the view.

## Confirmation Dialog for Destructive Actions

Destructive actions run only on POST. The backend JavaScript (`initDialog()`, `src/assets/js/modules/dialog.js`)
enhances a link to a server-side confirmation page: without JavaScript the link opens the confirmation page with the
POST form; with JavaScript the `#dialog` modal opens, and on confirm the script fetches that page, submits its form
by POST and follows the redirect of the server. No form is needed on the page with the link.

```html
<!-- href = confirmation page, data-form = CSS selector of its POST form -->
<a href="/backend/userDelete-5.html" class="btn btn-danger" data-action="confirm-deletion"
   data-form="main form" data-confirm="Really delete Jane Doe?">Delete</a>
```

The confirmation page (`userDelete-5.html`) shows the message and a normal (yuf) POST form with CSRF token and a
submit button. Its processing redirects after success.

- The script submits the fields of the form, including the CSRF token and the name of the first named submit button.
- Success is a redirect of the server. Any other result (no form found, network error, a re-rendered page with an
  error) navigates to the confirmation page, where the user sees the result.
- Without `data-form`, the previous behaviour stays: a confirmation navigates to the `href` (GET, legacy).
- The `#dialog` element (`.dialog-message`, `[data-action="modal-submit"]`, `[data-action="modal-cancel"]`) is part of
  the default page template. `data-confirm` sets the message.
- `data-confirm-label` (optional) sets the text of the confirm button while the dialog is open (as plain text, no
  HTML); on close or cancel the button gets its template text back ("Yes, delete" / "Ja, löschen"). Use it for actions
  that are not a deletion:

```html
<a href="/backend/subscriptionCancel-42.html" class="btn btn-danger" data-action="confirm-deletion"
   data-form="main form" data-confirm="Really cancel the subscription?"
   data-confirm-label="Yes, cancel">Cancel subscription</a>
```

The same pattern also protects state-changing actions that are not deletions, e.g. generating an API key
(`userGenerateApiKey-5.html`, `profileGenerateApiKey.html`).
