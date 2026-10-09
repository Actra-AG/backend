# Analysis: stale breadcrumb after a direct jump between detail pages

Status: fixed in v1.10.0 (2026-10-08) with `BackendView::getBreadcrumbParents()` (section 4, decision: overridable
method). The session trail stays for views without parents.

## 1. Symptom

With `useNavigator: true`, open a detail page (`subscription-42.html`, title "Anmeldung #42") and then open another
detail page directly by bookmark or typed URL (`subscriptionConfirm-44.html`). The breadcrumb shows
"Anmeldung #42 › Bestätigung #44": the first entry belongs to a different item than the current page.

## 2. Cause

`actra\backend\libs\common\OldNavigator` (not yuf) keeps the trail in `$_SESSION['sess_breadcrumb']`:

- The key of an entry is the page name (`pathVars[0]`, e.g. `subscription`), not the full link. The link
  (`subscription-42`) and title are stored as value.
- `addBreadcrumb()` overwrites the entry of the current page or appends it at the end.
- `getBreadcrumb()` shows every entry before the current page as link and removes the entries after it.

The trail therefore models "pages visited in this order" without checking whether an earlier entry is a parent of the
current page. A request that does not come from the last trail entry (bookmark, typed URL, link from an email or
another tab) is appended to the old trail, so the earlier entries of another item stay visible. The server cannot tell
these requests apart from a click inside the backend, because the trail does not know the hierarchy of the pages.

Related: a second browser tab shares the session and therefore the same trail.

## 3. Why no quick fix

- Comparing IDs (reset if the previous entry has a different path variable) breaks valid trails such as
  `event-7.html` → `subscription-42.html` (subscription 42 of event 7).
- Using the `Referer` header (reset if it is missing or not the last trail entry) fixes bookmarks and typed URLs, but
  removes all breadcrumbs for projects or browsers that send no referrer (`Referrer-Policy: no-referrer`, privacy
  extensions).

## 4. Proposal (next minor version)

Let views declare their parent instead of deriving the trail from the visit history:

1. New optional `BackendView` constructor argument (e.g. `breadcrumbParent: ?BreadcrumbParent`) or an overridable
   method `getBreadcrumbParents(): list<BreadcrumbItem>` (readonly value object: title, href). The view builds the
   trail from its own data (`subscriptionConfirm-44` → event of subscription 44 → subscription 44).
2. Views that provide it ignore the session trail; views that do not keep the current `OldNavigator` behaviour, so
   the change is not breaking.
3. Later (major version): remove `OldNavigator` and the session trail.

Workaround for projects until then: add `?reset` to links that enter a detail page from outside the trail (e.g. links
in emails), or set `resetNavigator: true` on views that are entry points. Neither helps for bookmarks.