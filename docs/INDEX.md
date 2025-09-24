# Modules Documentation Index

Central index for module-level documentation sets.

## Structure & Standards
- Each module should maintain a `docs/` folder with:
  - `REFERENCE_MAP.md` – DOM IDs, CSS classes, JS entry points, AJAX routes
  - `COMPONENT_INDEX.md` – reusable UI components with markup + hooks
  - `KNOWLEDGE_BASE.md` – architecture, flows, conventions, perf/security notes
  - `CHANGELOG.md` – dated changes to the module and/or docs
- Keep docs close to code. Update docs in the same PR as code changes.

## Available Docs
- Stock Transfers: `https://staff.vapeshed.co.nz/modules/transfers/stock/docs/`
  - Reference Map: `https://staff.vapeshed.co.nz/modules/transfers/stock/docs/REFERENCE_MAP.md`
  - Component Index: `https://staff.vapeshed.co.nz/modules/transfers/stock/docs/COMPONENT_INDEX.md`
  - Knowledge Base: `https://staff.vapeshed.co.nz/modules/transfers/stock/docs/KNOWLEDGE_BASE.md`
  - Changelog: `https://staff.vapeshed.co.nz/modules/transfers/stock/docs/CHANGELOG.md`
- Purchase Orders: `https://staff.vapeshed.co.nz/modules/purchase-orders/docs/`
  - Reference Map: `https://staff.vapeshed.co.nz/modules/purchase-orders/docs/REFERENCE_MAP.md`
  - Component Index: `https://staff.vapeshed.co.nz/modules/purchase-orders/docs/COMPONENT_INDEX.md`
  - Knowledge Base: `https://staff.vapeshed.co.nz/modules/purchase-orders/docs/KNOWLEDGE_BASE.md`
  - Changelog: `https://staff.vapeshed.co.nz/modules/purchase-orders/docs/CHANGELOG.md`

## Contributing
- Follow PSR-12 for code; keep CSS/JS under 25KB per file where possible.
- No secrets in docs. Use absolute URLs under `https://staff.vapeshed.co.nz` when referencing assets or views.
- When adding a new module, copy the 4-file doc pattern and link it here.
