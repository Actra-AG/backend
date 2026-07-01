# Upgrade Guide

This document tracks relevant changes for both frontend and backend developers.

## HTML & CSS (Frontend)

### v0.10.4 – July 1, 2026

* Refined the default backend CSS assets.
* Reduced the bundled Inter font declarations to the weights used by the backend UI.
* Updated backend font references to use the `/fonts/backend/` public path.
* Added reusable dropdown content styles in `src/assets/css/blocks/_dropdown-content.css`.
* Added reusable delete action styles in `src/assets/css/blocks/_delete.css`.
* Replaced the table control block with the table meta block in `src/assets/css/blocks/_table-meta.css`.
* Improved styling for buttons, icon-only buttons, tables, detail lists, authentication pages, and user dropdowns.
* Projects publishing the default assets should make sure the backend font files are available below `/fonts/backend/`.

### v0.10.3 – June 26, 2026

* Added default assets under `src/assets`.
* Added the default CSS entrypoint at `src/assets/css/backend.css`.
* Added the default JavaScript entrypoint at `src/assets/js/backend.js`.
* Added JavaScript modules for navigation toggles, dialogs, dropdowns, and responsive tables.
* Projects should integrate these assets into their own asset build or publishing process and reference the resulting
  public URLs via `ActraBackend::init()` using `stylesHref` and `javaScriptPaths`.

### v0.10.2 – June 18, 2026

* Added the `nav-user-logout` CSS class to the logout item in the user dropdown.

## Backend & API

### 0.10.0 – June 13, 2026

* **Feature:** Added a profile page for logged-in backend users.
* **Feature:** Logged-in users can update their own first name, last name, phone number, and IP whitelist.
* **Feature:** Logged-in users can generate, replace, or remove their own API key from the profile page.
* **Feature:** Added `ActraBackend::RIGHT_BACKEND_ACCESS` as the default access right for authenticated backend users.
* **Security:** API keys can only be generated if the user has a non-empty IP whitelist.
* **Security:** A user's IP whitelist cannot be emptied while an API key exists. The API key must be removed first.
* **Database:** Added the `backend_access` right to default data and assigned it to all existing groups during upgrade.
* **Database:** Installations upgrading from an earlier version must apply `db/updates/0.10.0.sql` to add the new
  `backend_access` right to all existing groups.

### 0.9.0 – June 13, 2026

* **Feature:** Added API-key management for backend users.
* **Feature:** User detail pages can now generate or replace a user's API key.
* **Feature:** Added bearer-token validation via `DbAuthApiKeyRepository::getUserIDForBearerOrThrow()`.
* **Security:** API keys are shown only once after generation and are stored hashed with a salt.
* **Database:** Added the `auth_api_key` table to store API-key metadata, public IDs, hashed secrets, salts, and
  registration timestamps.
* **Database:** Installations upgrading from an earlier version must apply `db/updates/0.9.0.sql` before using API-key
  functionality.
* **Logic Change:** Deleting a user now also removes that user's API key.

### 0.8.7 – May 18, 2026

* **Enhancement:** Added `cc` and `bcc` support to `Mailer::send()`.
* **Enhancement:** Added helper methods `hasOneOfIDs()` and `get()` to `DbAuthGroupCollection`.
* **Enhancement:** Added helper methods `has()` and `get()` to `DbAuthUserCollection`.
* **Logic Change:** The template directory is now configurable via `ActraBackend::init()`. The default remains
  `__DIR__ . '/view/backend/templates/'` within `ActraBackend`.
* **Logic Change:** Added `legacyBreadcrumbSeparator` to `BackendView` constructor to allow customizing the separator in
  breadcrumbs (defaults to `' '`).
* **Refactor:** `ActraBackend::renderJavaScriptPaths()` and other methods updated for better code style consistency.
