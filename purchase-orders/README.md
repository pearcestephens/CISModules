# Purchase Orders (PO) Module

This module implements Purchase Orders as a first-class workflow separate from Stock Transfers.

Authoritative links:
- Receive (UI): https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=receive
- Index (UI): https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=index
- Admin dashboard (UI): https://staff.vapeshed.co.nz/modules/module.php?module=purchase-orders&view=admin
- AJAX base (internal staff): https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php (POST only)
- API base (partner/supplier): https://staff.vapeshed.co.nz/modules/purchase-orders/api/v1/ (planned)

Key goals:
- Robust PO lifecycle (Draft → Sent → Partial/Received → Closed/Cancelled)
- Idempotent, auditable operations with queue-backed side effects (Vend/Lightspeed, Xero)
- Partial receipts, backorders, and 3-way match (PO, GRN, invoice)
- Pluggable supplier connectors (Email+CSV, SFTP, EDI/AS2, Vendor APIs)

See detailed specs:
- Design: https://staff.vapeshed.co.nz/modules/purchase-orders/DESIGN.md
- API Spec: https://staff.vapeshed.co.nz/modules/purchase-orders/API_SPEC.md
