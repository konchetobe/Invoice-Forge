---
phase: quick-8
plan: 01
subsystem: invoice-counter-management
tags: [ajax, settings, counter, numbering, admin-ui]
dependency_graph:
  requires: [NumberingService, InvoiceAjaxHandler, admin.js, settings.php]
  provides: [invoiceforge_reset_invoice_counter AJAX, invoiceforge_set_invoice_counter AJAX, Danger Zone counter UI]
  affects: [invoice numbering sequence, Settings > Advanced page]
tech_stack:
  added: []
  patterns: [WordPress AJAX with nonce + capability check, jQuery AJAX with toast feedback, absint() sanitization]
key_files:
  created: []
  modified:
    - src/Ajax/InvoiceAjaxHandler.php
    - assets/admin/js/admin.js
    - templates/admin/settings.php
decisions:
  - Remove data-confirm attribute from reset button so handleResetCounter owns its confirm dialog, avoiding double-confirm with generic handleConfirm handler
  - Use get_option('invoiceforge_last_invoice_number') directly in template to display current counter, avoiding DI container complexity
  - Use e.stopImmediatePropagation() in handleResetCounter to prevent the generic [data-confirm] handler from also firing
metrics:
  duration: ~8 minutes
  completed: 2026-03-24T12:11:40Z
  tasks_completed: 2
  files_modified: 3
---

# Quick Task 8: Fix Reset Invoice Counter Bug and Add Manual Counter Adjustment Summary

**One-liner:** Working reset button with AJAX backend + new Set Counter input field that lets users set the invoice sequence to any value, both nonce-protected.

## What Was Built

### Task 1: AJAX Endpoints (src/Ajax/InvoiceAjaxHandler.php)

Added two new AJAX methods to `InvoiceAjaxHandler`:

- **`resetInvoiceCounter()`** — registered as `wp_ajax_invoiceforge_reset_invoice_counter`. Reads `start_number` from `NumberingService::getNumberingConfig()`, calls `reset(start_number - 1)` so the next `generate()` produces exactly `start_number`. Returns the new preview and counter in the success JSON.

- **`setInvoiceCounter()`** — registered as `wp_ajax_invoiceforge_set_invoice_counter`. Reads `counter_value` from `$_POST` via `absint()`, validates it is >= 1, calls `reset(value - 1)`, returns preview and the set value in success JSON.

Both methods apply the same security gate: nonce check (`invoiceforge_admin`) then `manage_options` capability.

### Task 2: UI and JavaScript (assets/admin/js/admin.js + templates/admin/settings.php)

**settings.php Danger Zone changes:**
- Removed `data-confirm` attribute from the reset button (handler owns the confirm now)
- Added a "Set Invoice Counter" sub-section below the reset button with a `min=1` number input and "Set Counter" button
- Displays current counter from `get_option('invoiceforge_last_invoice_number', 0)` for immediate reference

**admin.js changes:**
- Bound `#reset-invoice-counter` to `handleResetCounter` and `#set-invoice-counter` to `handleSetCounter` in `bindEvents`
- `handleResetCounter`: calls `e.stopImmediatePropagation()` to prevent double-confirm with generic handler, shows confirm dialog, POSTs to reset endpoint, updates `#counter-current-info` on success
- `handleSetCounter`: validates input >= 1, shows confirm with the chosen value, POSTs to set endpoint, clears input and updates `#counter-current-info` on success
- Both handlers disable the button during request and re-enable in `complete`, show error toast on network failure

## Commits

| Task | Commit | Message |
|------|--------|---------|
| 1 | a549e51 | feat(quick-8): add resetInvoiceCounter and setInvoiceCounter AJAX endpoints |
| 2 | cf8c13d | feat(quick-8): add JS counter handlers and Set Counter UI in Danger Zone |

## Deviations from Plan

None - plan executed exactly as written. The plan's own decision note (remove `data-confirm` vs. `stopImmediatePropagation`) was resolved in favour of `stopImmediatePropagation` to keep the reset button markup clean while also removing `data-confirm` per the final "Decision:" line in the plan.

## Self-Check: PASSED

- FOUND: src/Ajax/InvoiceAjaxHandler.php
- FOUND: assets/admin/js/admin.js
- FOUND: templates/admin/settings.php
- FOUND: commit a549e51 (Task 1)
- FOUND: commit cf8c13d (Task 2)
