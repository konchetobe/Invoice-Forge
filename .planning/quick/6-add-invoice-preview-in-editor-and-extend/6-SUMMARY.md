---
phase: quick-6
plan: 01
subsystem: invoicing
tags: [preview, live-update, numbering, settings, ajax, ui]
dependency_graph:
  requires: []
  provides: [live-invoice-preview, extended-invoice-numbering]
  affects: [NumberingService, PdfService, InvoiceAjaxHandler, SettingsPage, invoice-editor, admin.js, admin.css]
tech_stack:
  added: []
  patterns: [debounced-ajax-preview, form-data-override-render, xhrabort-pattern]
key_files:
  created: []
  modified:
    - src/Services/NumberingService.php
    - src/Admin/Pages/SettingsPage.php
    - src/Services/PdfService.php
    - src/Ajax/InvoiceAjaxHandler.php
    - templates/admin/invoice-editor.php
    - assets/admin/js/admin.js
    - assets/admin/css/admin.css
    - templates/admin/settings.php
decisions:
  - "renderPreviewHtml merges form overrides over saved DB data; line item totals are recalculated client-side for preview accuracy"
  - "getTemplateContext accepts optional invoice_data_override to avoid duplicating template-context logic"
  - "Preview panel only renders for existing invoices (invoice_id > 0); new invoices show placeholder text"
  - "legacy format() method preserved for backward compatibility; new formatCustom() carries the full config"
  - "parse() uses lenient regex that handles both old PREFIX-YEAR-NUMBER and new flexible formats"
metrics:
  duration: ~40min
  completed_date: "2026-03-21"
  tasks_completed: 3
  files_modified: 8
---

# Quick-6 Summary: Live Invoice Preview Panel and Extended Invoice Numbering

**One-liner:** Live split-view invoice editor with 500ms debounced HTML preview plus fully customizable invoice numbering (prefix, suffix, date patterns, counter reset modes, padding).

## What Was Built

### Task 1: Extended NumberingService and Settings

**NumberingService.php:**
- Added `getNumberingConfig(): array` reading 6 settings: `prefix`, `suffix`, `date_pattern`, `counter_reset`, `start_number`, `padding`
- Added `formatCustom(array $config, int $year, int $month, int $number): string` producing segments joined by `-`, supporting date patterns `Y` / `Ym` / `Y-m` / `none`, configurable zero-padding, and optional suffix
- Updated `generate()` to use config-driven counter reset modes: `yearly` (existing behavior), `monthly` (new `OPTION_LAST_MONTH`), `never`
- Updated `preview()` to use new config and respect all reset modes
- Updated `parse()` with lenient segment-based regex that handles both old and new formats
- Updated `reset()` to also clear the month option
- Kept legacy `format(string $prefix, int $year, int $number)` for backward compatibility

**SettingsPage.php (Advanced tab):**
- Added 5 fields to `TAB_FIELDS['advanced']`: `invoice_suffix`, `invoice_date_pattern`, `invoice_counter_reset`, `invoice_start_number`, `invoice_number_padding`
- Registered all 5 via `addAdvancedFields()` using existing field renderers
- Added sanitization cases in `sanitizeField()`: suffix strips non-alphanumeric, patterns validated against allowlists, numbers clamped to valid ranges
- Added defaults in `getDefaults()` matching previous behavior: empty suffix, `Y` pattern, `yearly` reset, start 1, padding 4

**templates/admin/settings.php:**
- Preview box now shows next invoice number AND a format hint (e.g., `{PREFIX}-{YEAR}-{0000}`)

### Task 2: Live Invoice Preview Panel

**PdfService.php:**
- Added `renderPreviewHtml(int $invoice_id, array $form_overrides = []): string` — merges posted form fields over saved DB data, recalculates line item totals from posted items, renders the invoice template HTML, returns string without persisting
- Updated `getTemplateContext()` signature with optional `?array $invoice_data_override` — when provided, reads client and payment fields from the override array instead of the DB to avoid double-fetching

**InvoiceAjaxHandler.php:**
- Registered `wp_ajax_invoiceforge_preview_invoice_html` action
- Added `previewInvoiceHtml()`: nonce + capability check, sanitizes all posted form fields, collects line items array, delegates to `PdfService::renderPreviewHtml()`, returns `wp_send_json_success(['html' => $html])`

**templates/admin/invoice-editor.php:**
- Layout div class: `invoiceforge-editor-with-preview` for existing invoices, `invoiceforge-editor-layout` for new invoices
- Added `invoiceforge-preview-panel` div after sidebar (only for existing invoices)

**assets/admin/js/admin.js:**
- Added `initInvoicePreview()` to `init()` call chain
- `initInvoicePreview()`: bails if no preview frame or new invoice, stores `debouncedLoadPreview` (500ms), binds input/change/select events on form and line items tbody, loads initial preview immediately
- `loadPreview()`: aborts pending XHR, collects all current form values including line items, POSTs to `invoiceforge_preview_invoice_html`, injects response HTML into `#invoiceforge-preview-frame`, shows "Up to date" status that fades after 2s
- `handleInvoiceSave` success callback calls `loadPreview()` after save

**assets/admin/css/admin.css:**
- `.invoiceforge-editor-with-preview`: 3-column grid (`1fr 320px 480px`)
- Responsive: collapses preview to full-width below 1400px, single column below 1024px
- `.invoiceforge-preview-panel`: sticky top:32px
- `.invoiceforge-preview-frame`: max-height 80vh, scrollable
- Status, placeholder, and loading text styles

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | 2503e24 | feat(quick-6): extend NumberingService and Settings for invoice number customization |
| 2 | 8434867 | feat(quick-6): add live invoice preview panel with real-time debounced updates |

## Deviations from Plan

None - plan executed exactly as written.

## Verification (Task 3 — checkpoint:human-verify): APPROVED

Human verification completed on 2026-03-21. All of the following were confirmed on the live WordPress site:

1. Settings > Advanced tab shows 5 new fields: Invoice Suffix, Date Pattern, Counter Reset, Start Number, Number Padding
2. Changing prefix/suffix/date pattern updates the "Next Invoice Number" preview and format hint
3. Settings persist after save and page reload
4. Invoice editor for an existing invoice shows a 3-column split view (form | sidebar | preview)
5. Preview loads on page load
6. Changing any field updates preview within ~1 second (no save required)
7. Changing client dropdown updates preview with new client details
8. Editing line items updates preview totals
9. Clicking Save refreshes preview again
10. New invoice form shows "Save first to see a preview" placeholder (no preview panel)
11. At < 1400px width: preview moves below the form; at < 1024px: single column

## Self-Check: PASSED

- `src/Services/NumberingService.php` — exists, PHP syntax OK (2503e24)
- `src/Admin/Pages/SettingsPage.php` — exists, PHP syntax OK (2503e24)
- `templates/admin/settings.php` — exists, PHP syntax OK (2503e24)
- `src/Services/PdfService.php` — exists, PHP syntax OK (8434867)
- `src/Ajax/InvoiceAjaxHandler.php` — exists, PHP syntax OK (8434867)
- `templates/admin/invoice-editor.php` — exists, PHP syntax OK (8434867)
- `assets/admin/js/admin.js` — exists, JS readable (8434867)
- `assets/admin/css/admin.css` — exists (8434867)
