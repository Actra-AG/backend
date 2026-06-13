# Upgrade Guide

This document tracks relevant changes for both frontend and backend developers.

## Backend & API

### 0.9.0 – June 13, 2026

* **Feature:** Added API-key management for backend users.
* **Feature:** User detail pages can now generate or replace a user's API key.
* **Feature:** Added bearer-token validation via `DbAuthApiKeyRepository::getUserIDForBearerOrThrow()`.
* **Security:** API keys are shown only once after generation and are stored hashed with a salt.
* **Database:** Added the `auth_api_key` table to store API-key metadata, public IDs, hashed secrets, salts, and
  registration timestamps.
* **Database:** Installations upgrading from an earlier version must apply `db/updates/1.9.0.sql` before using API-key
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
