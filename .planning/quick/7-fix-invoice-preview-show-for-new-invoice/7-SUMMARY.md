---
phase: quick-7
plan: "01"
subsystem: invoice-editor
tags: [preview, new-invoice, render-mode, email, pdf, toggle]
dependency_graph:
  requires: [quick-6]
  provides: [preview-for-new-invoices, render-mode-toggle]
  affects: [invoice-editor-page]
tech_stack:
  added: []
  patterns: [form-data-only-preview, render-mode-parameter]
key_files:
  created: []
  modified:
    - src/Services/PdfService.php
    - src/Ajax/InvoiceAjaxHandler.php
    - templates/admin/invoice-editor.php
    - assets/admin/js/admin.js
    - assets/admin/css/admin.css
decisions:
  - "invoice_id=0 allowed in preview AJAX: new invoices bypass DB fetch; negative IDs remain invalid"
  - "render_mode defaults to email in both PdfService and AjaxHandler; only 'pdf' overrides"
  - "company_name/email/phone/address included in new-invoice base array so template renders correctly"
metrics:
  duration: "20 minutes"
  completed_date: "2026-03-22"
  tasks_completed: 2
  tasks_total: 3
  files_modified: 5
---

# Quick Task 7: Fix Invoice Preview for New Invoices - Summary

**One-liner:** Live preview panel now works on new (unsaved) invoices using form-only data, with an email/PDF mode toggle in the preview header.

## Objective

Fix the invoice preview panel so it works for new (unsaved) invoices and add an email/PDF preview mode toggle. Previously, the preview was hidden for new invoices at three gates: PHP template (`$is_new`), JS `initInvoicePreview` (`invoiceId <= 0`), and AJAX handler (`invoice_id <= 0` rejection).

## Tasks Completed

| # | Name | Commit | Files |
|---|------|--------|-------|
| 1 | Backend: Support preview for new invoices and render_mode | 9d057c4 | PdfService.php, InvoiceAjaxHandler.php |
| 2 | Frontend: Show preview for new invoices with email/PDF toggle | 1e033f7 | invoice-editor.php, admin.js, admin.css |
| 3 | Human verify | — | checkpoint (awaiting) |

## Changes Made

### PdfService.php
- `renderPreviewHtml` signature extended: `renderPreviewHtml(int $invoice_id, array $form_overrides = [], string $render_mode = 'email'): string`
- When `$invoice_id === 0`: builds complete invoice array from `$form_overrides` without any DB access. Number shows "(Not yet assigned)". Company fields pulled from `invoiceforge_settings`.
- When `$invoice_id > 0`: existing behavior unchanged (fetch from DB, merge overrides).
- `$render_mode` now passed to `getTemplateContext()` instead of hardcoded `'email'`.

### InvoiceAjaxHandler.php
- `invoice_id < 0` is now the invalid check (was `<= 0`). Zero is allowed for new invoices.
- `$render_mode` extracted from `$_POST['render_mode']`: accepts `'pdf'`, defaults to `'email'`.
- Passes `$render_mode` as third argument to `renderPreviewHtml()`.

### templates/admin/invoice-editor.php
- Layout class always `invoiceforge-editor-with-preview` (was conditional on `$is_new`).
- `<?php if (!$is_new) : ?>` gate removed from preview panel — panel always renders.
- Email/PDF toggle buttons added inside preview card header between title and status span.

### assets/admin/js/admin.js
- `initInvoicePreview`: removed `invoiceId <= 0` early return; initializes for all invoices including new ones.
- `initInvoicePreview`: stores `this._previewRenderMode = 'email'` and binds `.invoiceforge-preview-toggle` click handler.
- `loadPreview`: removed `invoiceId <= 0` early return; uses `parseInt(...) || 0` — sends `invoice_id: 0` for new invoices.
- `loadPreview`: `render_mode: this._previewRenderMode || 'email'` added to AJAX data object.
- After save, existing code at line 254 already updates the hidden `invoice_id` field so subsequent previews use the real ID.

### assets/admin/css/admin.css
- Added `.invoiceforge-preview-toggles`, `.invoiceforge-preview-toggle`, `.active`, and `:hover` styles.
- Toggle group is a flex row with pill border, WordPress admin color scheme (blue active state).

## Deviations from Plan

None — plan executed exactly as written.

## Pending

**Task 3 (checkpoint:human-verify):** Human must verify the following on the live WordPress site:
1. Go to InvoiceForge > Add New Invoice — preview panel appears on right (3-column layout)
2. Fill in fields — preview updates live with 500ms debounce
3. Invoice number shows "(Not yet assigned)" in preview
4. Email/PDF toggle buttons in preview header — "Email" active (blue) by default
5. Clicking "PDF" toggle reloads preview in PDF render mode
6. Clicking "Email" toggle switches back
7. Save invoice — preview continues working with real invoice_id
8. Existing invoice preview still works (no regression)
9. At less than 1400px width, preview collapses below form

## Self-Check: PASSED

- SUMMARY.md: FOUND
- Commit 9d057c4 (Task 1 backend): FOUND
- Commit 1e033f7 (Task 2 frontend): FOUND
