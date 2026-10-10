# Users and API keys

## User Deletion Handler

When a backend user is deleted, the library removes its own user-related records first and then deletes the row from
`auth_user`. Projects that store additional foreign-key references to `auth_user.id` can register a delete handler to
remove or update their project-specific records before the user itself is deleted.

In the consuming project, pass the handler in the settings of `ActraBackend::init()`:

```php
use actra\backend\libs\auth\UserDeleteHandler;

final class ProjectUserDeleteHandler implements UserDeleteHandler {
    public function beforeDeleteUser(int $userId): void {
        $this->projectUserProfiles->deleteByUserId(userId: $userId);
        $this->projectUserSettings->deleteByUserId(userId: $userId);
    }
}
new ActraBackendSettings(
    ...
    userDeleteHandler: new ProjectUserDeleteHandler(),
);
```

The delete handler is executed inside the same database transaction as the built-in user cleanup and before `auth_user`
is deleted. If the handler throws an exception, the transaction is rolled back and the user is not deleted.

For simple database relations, projects can alternatively use foreign keys with `ON DELETE CASCADE` or
`ON DELETE SET NULL`, depending on whether related rows should be removed or preserved without the user reference.

## Rights of users who manage users

A user with the right `manage_users` manages only users who have no right that this user lacks: groups with such a
right are not offered, and users with such a right cannot be edited, invited, impersonated, deleted or given an API key
(the pages answer 404). Nobody can deactivate or delete their own account, and the last active user with
`manage_users` keeps it. Deactivating a user ends the user's sessions and deletes the open tokens and the API key;
changing the email address ends the sessions and open tokens.

Impersonation ("Impersonate user") and "Cancel session change" run only on POST, through the confirmation pages
`userImpersonate-{ID}.html` and `userImpersonateEnd.html`. An impersonation ends as soon as the impersonating user may
no longer manage the impersonated user (deactivated, rights removed).

## IP whitelists

The IP whitelist of the settings (`ActraBackendSettings::$ipWhitelist`) applies to every backend page; the whitelist of
a user applies in addition after the login (both must match if both are set). In an impersonation the whitelist of the
impersonating user applies. Behind a reverse proxy, let the web server set the client address (yuf's `docs/setup.md`).

## Login codes, password reset links and lock-out

Login codes have 6 characters and are valid only in the browser session that requested them, password reset links
have 22 letters and digits; both expire after 15 minutes and can be used once. Only their SHA-256 hash is stored, in
the database and in the session; the token log does not show them. A new password (reset or profile) ends the other
sessions and the open tokens of the user.

Wrong passwords are counted per user (`maxAllowedLoginAttempts`, also for the current password in the profile). At the
limit the user is locked until a new password is set with a reset link; a successful login does not reset the counter.

## Send limit of login codes and reset links

The backend sends at most 5 login codes and at most 5 password reset links per user within 15 minutes, so a known
address cannot be flooded with mails (counted and created under a lock of the user row, so parallel requests do not
exceed it). Above the limit, the forms answer as before (they do not reveal whether an address exists) but send
nothing; a code sent before stays valid in the browser session it was requested in until it expires. Change the limit
or turn it off in the settings:

```php
new ActraBackendSettings(
    // …
    tokenSendLimit: new TokenSendLimit(maxTokens: 3, withinMinutes: 30), // null: no limit
);
```

## API Key Authentication

API-key functionality is optional and must be enabled through `ActraBackendSettings`:

```php
new ActraBackendSettings(
    ...
    hasApi: true
);
```

Users with management access can generate, replace, or remove a user's API key on the user detail page. Logged-in users
can also manage their own API key on their profile page.

API keys can only be generated if an IP whitelist is configured for the user, and they work only from an address of
this whitelist. If an API key exists, the user's IP whitelist cannot be emptied until the API key has been removed.
Generated keys are shown only once and stored as SHA-256 hash of the random secret (yuf's `SecretTokenHash`, fast
enough for every API request); keys generated before v1.11.0 keep their former hash until they are generated again.

Generating and removing a key run only on POST, through the confirmation pages `userGenerateApiKey-{ID}.html`,
`userRemoveApiKey-{ID}.html`, `profileGenerateApiKey.html` and `profileRemoveApiKey.html` (see "Confirmation Dialog
for Destructive Actions"). After generating, the new key is kept in the session until the user or profile page has
shown it once.

API clients should send the generated key as a bearer token:

```http
Authorization: Bearer api_key_<public-id>_<secret>
```

To authenticate the request, pass it to the backend (in a view: `$this->context->httpRequest`) and check the rights of
the endpoint with the returned user:

```php
$myAuthUser = $actraBackend->authenticateBearerOrThrow(httpRequest: $this->context->httpRequest);
$orderRights = AccessRightCollection::createFromStringArray(input: ['orders']);
if (!$myAuthUser->hasOneOfRights(accessRightCollection: $orderRights)) {
    throw new UnauthorizedException();
}
```

An `UnauthorizedException` is thrown if the bearer token is missing, malformed, unknown or invalid, the API is off
(`hasApi: false`), the user is inactive or has no right, or the request does not come from the user's IP whitelist.
