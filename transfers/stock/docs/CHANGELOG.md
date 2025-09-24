# Changelog – Stock Transfers Dashboard

## 2025-09-22
- Centralized action logging: every AJAX action recorded in transfer_logs + transfer_audit_log
- Added audit before/after snapshots for key actions (set_status, cancel, finalize_pack, receive_partial/final, delete)
- System-wide page view logging via CIS template; page performance profiling (duration + peak mem)
- Slow SQL audit (PDO statement profiler) for Transfers AJAX
- Added outlet name enrichment (server) and displayed names in tables; later switched UI to name-only (IDs in tooltips)
- Compact row action dropdowns; per-row View button shrunk
- Relative timestamps with absolute hover tooltips; activity feed improved
- From/To typeahead with keyboard support; jitter fixes
- Fixed-height tables (500px) with sticky headers; pagination improved including 0-of-0 state
- Empty-state messages for Open and Search tables
- KPI shine/glare subtle animation
- Filters resized to fit on one line; Status select compact
- Label-embedded clear chips for From/To

## 2025-09-21
- Initial dashboard layout: KPI, Open Transfers, Latest Activity, and Search All Transfers
