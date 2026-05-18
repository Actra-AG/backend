# Upgrade Guide

This document tracks relevant changes for both frontend and backend developers.

## Backend & API

### May 18, 2026

* **Enhancement:** Added `cc` and `bcc` support to `Mailer::send()`.
* **Enhancement:** Added helper methods `hasOneOfIDs()` and `get()` to `DbAuthGroupCollection`.
* **Enhancement:** Added helper methods `has()` and `get()` to `DbAuthUserCollection`.
* **Logic Change:** The template directory is now configurable via `ActraBackend::init()`. The default remains `__DIR__ . '/view/backend/templates/'` within `ActraBackend`.
* **Logic Change:** Added `legacyBreadcrumbSeparator` to `BackendView` constructor to allow customizing the separator in breadcrumbs (defaults to `' '`).
* **Refactor:** `ActraBackend::renderJavaScriptPaths()` and other methods updated for better code style consistency.
