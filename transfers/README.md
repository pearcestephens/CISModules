# Transfers (Base)

This is the unified transfers module base. Specialized transfer types (stock, juice, in-store) inherit the base patterns and helpers.

- Base helpers in `base/` (see `base/model.php` for unified types and status)
- Stock transfers live in `modules/transfers/stock/`
- Top-level dashboard: https://staff.vapeshed.co.nz/modules/transfers/dashboard.php
- Stock dashboard: https://staff.vapeshed.co.nz/modules/transfers/stock/dashboard.php

Usage: include `modules/_shared/template.php` in views and call base `init.php` when you need base facilities.

See DESIGN.md for the unified model and lifecycle, and the Purchase Orders exception (supplier party).
