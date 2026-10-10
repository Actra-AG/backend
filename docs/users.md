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

## Send limit of login codes and reset links

The backend sends at most 5 login codes and at most 5 password reset links per user within 15 minutes, so a known
address cannot be flooded with mails. Above the limit, the forms answer as before (they do not reveal whether an
address exists) but send nothing; a code sent before stays valid in the browser session it was requested in until it
expires. Change the limit or turn it off in the settings:

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

API keys can only be generated if an IP whitelist is configured for the user. If an API key exists, the user's IP
whitelist cannot be emptied until the API key has been removed. Generated keys are shown only once and stored as
SHA-256 hash of the random secret (yuf's `SecretTokenHash`, fast enough for every API request); keys generated before
v1.11.0 keep their former hash until they are generated again.

Generating and removing a key run only on POST, through the confirmation pages `userGenerateApiKey-{ID}.html`,
`userRemoveApiKey-{ID}.html`, `profileGenerateApiKey.html` and `profileRemoveApiKey.html` (see "Confirmation Dialog
for Destructive Actions"). After generating, the new key is kept in the session until the user or profile page has
shown it once.

API clients should send the generated key as a bearer token:

```http
Authorization: Bearer api_key_<public-id>_<secret>
```

To validate the bearer token and retrieve the authenticated user ID, pass the request (in a view:
`$this->context->httpRequest`):

```php
$userId = $actraBackend->getRepositories()->apiKeys()->getUserIdForBearerOrThrow(
    httpRequest: $this->context->httpRequest,
);
```

If the bearer token is missing, malformed, unknown, or invalid, an `UnauthorizedException` is thrown.
