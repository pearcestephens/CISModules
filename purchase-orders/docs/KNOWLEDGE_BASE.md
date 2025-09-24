# Knowledge Base – Purchase Orders

Architecture, conventions, and operational notes for the Purchase Orders module.

## Overview
- Purpose: Receive Purchase Orders efficiently, with partial and final submit flows, and provide an Admin dashboard for receipts, events, queue monitoring, and evidence management.
- Tech: PHP 8.x, Bootstrap views, vanilla JS modules (`receive.*.js`, `admin.dashboard.js`), AJAX POST endpoints with CSRF and auth.

## Entry Points
- User landing (via CIS Template router): https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=index
- Receive (works without specifying a PO initially): https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=receive&po_id={id}
- Admin: https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=admin
- AJAX (POST only): https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php

## Security & Session
- All POST actions require: logged-in user, CSRF token verified (po_verify_csrf()).
- Admin dashboard injects CSRF via `.po-admin[data-csrf]` for JS to include in requests.
- Server logs correlated by request ID `$__PO_REQ_ID` (set in tools.php).

## Data Flow
- Receive screen boot:
  1) Load PO via `po.get_po` → render rows
  2) Scan or search → `po.search_products` or direct match
  3) Update quantities → local state → `po.save_progress`
  4) Submit partial/final → `po.submit_partial` or `po.submit_final`
  5) Optional live stock sync per item → `po.update_live_stock`
- Admin dashboard:
  - Tab loaders call admin.* endpoints with pagination and optional filters (PO ID, status, outlet).

## Error Handling
- All endpoints return envelopes `{ success, data|error, request_id? }`.
- UI shows toasts/alerts; final submit paths require confirmation and surface receipt IDs on success.

## Performance
- Table rendering uses lightweight DOM updates (batch insert or innerHTML string assembly).
- Paginated admin lists; avoid large payloads.

## Conventions
- IDs and classes scoped to `.po-receive` and `.po-admin`.
- AJAX actions prefixed with `po.` for user flows and `admin.` for admin lists.
- Absolute asset URLs under https://staff.vapeshed.co.nz/ to match global includes.

## Maintenance
- Add new endpoints by mapping in `ajax/handler.php`.
- Keep docs updated when IDs/JS change. Mirror the 4-file structure used in Stock Transfers for consistency.

## FAQ
- Q: Why POST-only?
  A: Enforces CSRF validation and keeps parameters out of URL logs for sensitive actions.
- Q: How to test locally?
  A: Use the module router with a known `po_id`. Verify CSRF token is present in `.po-admin` for admin actions.
