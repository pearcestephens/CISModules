# CHANGELOG – Purchase Orders

All notable changes to the Purchase Orders module docs.

## 2025-09-24
- Corrected authoritative links to use the CIS Template router for index/receive/admin views.
- Clarified that the receive view can open without an initial PO ID and will prompt for selection.
- Emphasized absolute URLs under https://staff.vapeshed.co.nz and POST-only AJAX base.

## 2025-09-22
- System-wide telemetry routing added: page view + page performance (via CIS template) now sink into audit log under entity_type 'purchase_order.page' (minimal Logger)
- Added initial doc set:
  - REFERENCE_MAP.md
  - COMPONENT_INDEX.md
  - KNOWLEDGE_BASE.md
  - CHANGELOG.md
- Captured DOM IDs, admin tabs, AJAX routes, and data contracts at a high level.
