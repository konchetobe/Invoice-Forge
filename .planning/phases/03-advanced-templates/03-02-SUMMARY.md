---
phase: 03-advanced-templates
plan: 02
subsystem: ui
tags: [php, mpdf, pdf, email, invoice-template, wordpress, bulgarian-format]

# Dependency graph
requires:
  - phase: 03-01
    provides: LineItem discount fields, client extended meta fields (_client_id_no, _client_office, _client_att_to), _invoice_payment_method meta, invoiceforge_settings[template] config, company profile fields (id_no, office, att_to, bank_name, iban, bic)
provides:
  - PdfService::generate() with fourth render_mode parameter ('pdf'|'email')
  - PdfService::getTemplateContext() assembling full flat context array for template
  - PdfService::renderEmailBody() convenience wrapper for EmailService
  - templates/pdf/invoice-default.php configurable template with PDF and email render modes
  - Three-column invoice header (BUYER | No+Date | SELLER) matching Bulgarian reference format
  - Conditional discount columns in line items table based on has_discount
  - Bank/IBAN section auto-shown for Bank transfer payment method
  - Configurable section order and visibility via section_order/section_visibility settings
  - Signature block with configurable left/right column fields
  - Email mode: simplified inline-styled HTML with Pay Invoice placeholder button
affects:
  - 03-03 (email rendering calls renderEmailBody())
  - Phase 8 (payment gateway will populate Pay Invoice href via data-invoice-id)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Dual render mode pattern: single template file branches on $render_mode='pdf'|'email'
    - extract(EXTR_SKIP) injection of flat context array before template include
    - mPDF table-layout:fixed applied via WriteHTML HEADER_CSS before main HTML
    - Logo resolved to filesystem path for PDF (mPDF requirement) vs public URL for email
    - Hex color validation via regex with safe fallback before injecting into inline styles

key-files:
  created:
    - templates/pdf/invoice-default.php
    - .planning/phases/03-advanced-templates/03-02-SUMMARY.md
  modified:
    - src/Services/PdfService.php

key-decisions:
  - "Dual render mode in single template file: branch on $render_mode rather than two separate templates, reduces duplication and keeps settings applied identically"
  - "extract(EXTR_SKIP) chosen over passing $invoice array: template reads flat variables ($number, $date etc.) matching WordPress template conventions"
  - "Email mode returns early from generate() before any mPDF instantiation - avoids mPDF dependency for email callers"
  - "table-layout:fixed injected as HEADER_CSS before main WriteHTML to ensure mPDF respects column width percentages"
  - "Logo path vs URL: PDF mPDF requires absolute filesystem path; email clients require public URL - template branches on render_mode for img src"
  - "has_discount computed in getTemplateContext() not template: keeps template logic clean, single boolean controls both column headers and data cells"

patterns-established:
  - "Render mode branching: if ($render_mode === 'pdf') ... elseif ($render_mode === 'email') at top level of template"
  - "Section loop: foreach ($section_order as $section) with switch/case renders sections in user-configured order"
  - "Conditional section: check $section_visibility[$key] before rendering optional sections (notes, signature)"
  - "Payment-conditional section: check $payment_method === 'Bank transfer' inside bank case before rendering"

requirements-completed:
  - TMPL-04
  - TMPL-05

# Metrics
duration: 18min
completed: 2026-03-19
---

# Phase 3 Plan 02: PDF Template and PdfService Dual Render Mode Summary

**mPDF-compatible three-column Bulgarian-format invoice template with configurable section order, accent color, discount columns, bank/IBAN block, signature fields, and a companion inline-styled email mode — all driven by template settings from Plan 01**

## Performance

- **Duration:** 18 min
- **Started:** 2026-03-19T21:23:57Z
- **Completed:** 2026-03-19T21:42:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- PdfService extended with `render_mode` parameter: 'pdf' routes through mPDF as before, 'email' short-circuits and returns raw HTML for EmailService consumption
- New private `getTemplateContext()` builds a single flat associative array combining all invoice data, template settings, company/client extended fields, computed `has_discount`, `currency_symbol`, and `payment_method`
- `templates/pdf/invoice-default.php` (519 lines) is the core deliverable: PDF mode renders all six sections (header, line_items, totals, bank, notes, signature) in configurable order via `$section_order` loop and switch/case
- Discount columns in the line items table appear only when `$has_discount = true` — widths recalculate automatically to keep 100% total
- Bank/IBAN block renders only when `$payment_method === 'Bank transfer'`; signature block renders only when `$section_visibility['signature']` is truthy
- Email mode produces fully inline-styled HTML (no class attributes, no `<style>` blocks) with summary table, line items table, and a "Pay Invoice" button placeholder (`data-invoice-id` for Phase 8)

## Task Commits

Each task was committed atomically:

1. **Task 1: Extend PdfService with render mode and template context** - `d078d8e` (feat)
2. **Task 2: Create configurable PDF/email invoice template** - `8035312` (feat)

## Files Created/Modified

- `src/Services/PdfService.php` - Added render_mode parameter, getTemplateContext(), renderEmailBody(); getInvoiceData() now includes payment_method and client extended fields; mPDF gets table-layout:fixed CSS header
- `templates/pdf/invoice-default.php` - Full configurable template: PDF mode with section loop, email mode with inline styles (519 lines)

## Decisions Made

- Single template file with render_mode branch rather than separate pdf/email templates — keeps settings applied identically to both outputs and avoids duplication
- `extract(EXTR_SKIP)` injects a flat context array before `include`, matching WordPress template conventions and keeping template code clean (`$number` not `$ctx['number']`)
- Email mode returns before any `new \Mpdf\Mpdf()` instantiation — callers that only need email do not require mPDF installed
- `table-layout:fixed` applied via `\Mpdf\HTMLParserMode::HEADER_CSS` before the main `WriteHTML()` call to guarantee mPDF honours percentage column widths
- Logo uses `$logo_path` (filesystem) in PDF mode and `$logo_url` (public URL) in email mode — mPDF cannot fetch remote URLs reliably for `<img src>`
- `has_discount` computed in `getTemplateContext()` from line items array so the template receives a single boolean; avoids PHP loop logic inside the template

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `renderEmailBody(int $invoice_id): string` is ready for Plan 03 (EmailService) to call
- Template settings from Plan 01 (accent_color, section_order, section_visibility, signature_fields, logo) are fully wired and respected
- "Pay Invoice" button in email template has `data-invoice-id` attribute ready for Phase 8 payment gateway integration
- mPDF CSS baseline (table-layout:fixed) ensures consistent column rendering across different mPDF versions

---
*Phase: 03-advanced-templates*
*Completed: 2026-03-19*
