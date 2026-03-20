---
phase: 03-advanced-templates
plan: 03
subsystem: email
tags: [wordpress, php, email, html-email, pdf-attachment, ajax, media-library]

# Dependency graph
requires:
  - 03-01 (template settings, data model extensions)
  - 03-02 (PdfService renderEmailBody, dual render mode)
provides:
  - EmailService sends HTML email body via PdfService::renderEmailBody()
  - sendReminder() converted to text/html content type
  - Fallback HTML body when renderEmailBody returns empty
  - payment_method persists via AJAX (InvoiceAjaxHandler + admin.js formData)
  - Logo upload uses WP media library (not raw file input)
  - Signature block supports configurable left/right column titles
  - payment_method returned in getInvoiceData AJAX response
affects:
  - Phase 4 (client portal email delivery inherits HTML email pattern)
  - Phase 8 (Pay Invoice button placeholder wired here, gateway URL fills it)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - PdfService::renderEmailBody() called from EmailService for HTML body generation
    - Content-Type text/html for both sendInvoice and sendReminder
    - Fallback inline HTML body if renderEmailBody returns empty string
    - WP media library attachment ID stored in settings, URL resolved via wp_get_attachment_url()
    - payment_method field included in AJAX formData and InvoiceAjaxHandler save logic

key-files:
  created:
    - .planning/phases/03-advanced-templates/03-03-SUMMARY.md
  modified:
    - src/Services/EmailService.php
    - templates/email/invoice-sent.php
    - assets/admin/js/admin.js
    - src/Ajax/InvoiceAjaxHandler.php
    - src/Admin/Pages/SettingsPage.php
    - src/Services/PdfService.php
    - templates/admin/settings.php
    - templates/pdf/invoice-default.php

key-decisions:
  - "EmailService HTML body sourced from PdfService::renderEmailBody() not a separate email template; invoice-sent.php is now superseded"
  - "Fallback HTML body keeps text/html content type even when renderEmailBody fails — avoids mixed content-type across emails"
  - "Logo upload migrated to WP media library to reuse existing WordPress attachment management rather than custom upload handling"
  - "payment_method added to AJAX formData and getInvoiceData response to close the round-trip for invoice editor persistence"
  - "Signature left/right column titles are configurable per-field in settings so different signature blocks can have different headings"

requirements-completed:
  - TMPL-06

# Metrics
duration: ~35min (Task 1 automated + checkpoint review + bug fix iteration)
completed: 2026-03-20
---

# Phase 3 Plan 03: Email HTML Body and End-to-End Verification Summary

**Wired PdfService::renderEmailBody() into EmailService replacing plain-text email, converted sendReminder() to HTML, then fixed three post-checkpoint bugs: payment_method not persisting, logo upload not using WP media library, and signature block lacking configurable column titles**

## Performance

- **Duration:** ~35 min
- **Completed:** 2026-03-20
- **Tasks:** 2 (1 auto + 1 checkpoint with bug-fix iteration)
- **Files modified:** 8

## Accomplishments

- EmailService::sendInvoice() now calls PdfService::renderEmailBody() and sends HTML body with Content-Type text/html
- Fallback HTML body generated inline if renderEmailBody() returns empty, keeping text/html throughout
- sendReminder() body wrapped in minimal HTML tags and switched to Content-Type text/html
- PDF attachment and wp_mail() flow left intact
- Post-checkpoint review identified and fixed three regressions:
  - payment_method field was missing from AJAX formData in admin.js and from the InvoiceAjaxHandler save path; both patched so the value persists correctly
  - Logo upload used a raw file input; migrated to WP media library (attachment ID stored, URL resolved via wp_get_attachment_url())
  - Signature block column headings were hard-coded; settings UI and PDF template updated to support configurable left/right column titles per-field
- getInvoiceData AJAX response now returns payment_method so the invoice editor can pre-select the correct value on load

## Task Commits

1. **Task 1: Wire HTML email body into EmailService** - `bc77b50` (feat)
2. **Task 2 post-checkpoint bug fixes** - `79cf968` (fix)
   - payment_method persistence (AJAX handler + admin.js formData)
   - Logo upload via WP media library
   - Configurable signature left/right column titles
   - payment_method returned in getInvoiceData response

## Files Created/Modified

- `src/Services/EmailService.php` - sendInvoice() uses renderEmailBody(); sendReminder() wrapped in HTML; Content-Type text/html for both
- `templates/email/invoice-sent.php` - Superseded by PdfService email mode rendering; file retained but no longer the active email body source
- `assets/admin/js/admin.js` - payment_method added to saveInvoice formData
- `src/Ajax/InvoiceAjaxHandler.php` - payment_method saved in handler; payment_method included in getInvoiceData response
- `src/Admin/Pages/SettingsPage.php` - Logo field migrated to WP media library attachment ID; signature field schema extended with left/right title properties
- `src/Services/PdfService.php` - getInvoiceData returns payment_method; template context wired for signature column titles
- `templates/admin/settings.php` - Logo upload uses WP media library button; signature field rows show left/right title inputs
- `templates/pdf/invoice-default.php` - Signature block renders configurable left/right column headings

## Decisions Made

- HTML body sourced from PdfService::renderEmailBody() rather than the existing invoice-sent.php template; the PHP template is now bypassed but kept for reference
- Fallback body always uses text/html so there is no content-type inconsistency between successful and failed renders
- WP media library chosen for logo upload because it reuses WordPress's existing attachment management, cropping, and URL resolution rather than duplicating wp_handle_upload logic from SettingsPage
- payment_method gap was a missing link in the AJAX round-trip (formData -> handler -> DB -> getInvoiceData response); all four points patched together

## Deviations from Plan

### Auto-fixed Issues (post-checkpoint)

**1. [Rule 1 - Bug] payment_method not persisting through AJAX save**
- **Found during:** Task 2 checkpoint review
- **Issue:** admin.js did not include payment_method in the saveInvoice formData object; InvoiceAjaxHandler did not read or save it; getInvoiceData did not return it
- **Fix:** Added payment_method to formData serialization in admin.js; added save logic to InvoiceAjaxHandler; added field to getInvoiceData response array
- **Files modified:** assets/admin/js/admin.js, src/Ajax/InvoiceAjaxHandler.php, src/Services/PdfService.php
- **Commit:** 79cf968

**2. [Rule 1 - Bug] Logo upload used raw file input instead of WP media library**
- **Found during:** Task 2 checkpoint review
- **Issue:** Settings template rendered a plain `<input type="file">` for the logo; WP media library integration (wp.media) was absent, bypassing WordPress attachment management
- **Fix:** Replaced file input with media library button and hidden attachment-ID input in settings.php; SettingsPage.php updated to store attachment ID and resolve URL via wp_get_attachment_url()
- **Files modified:** src/Admin/Pages/SettingsPage.php, templates/admin/settings.php
- **Commit:** 79cf968

**3. [Rule 2 - Missing functionality] Signature block lacked configurable left/right column titles**
- **Found during:** Task 2 checkpoint review
- **Issue:** Signature fields in settings had label text only; no way to set the heading displayed above each column in the signature block on the PDF
- **Fix:** Extended signature field schema with left_title and right_title properties; settings.php UI shows two additional inputs per field; PDF template reads and renders these titles as column headings
- **Files modified:** src/Admin/Pages/SettingsPage.php, templates/admin/settings.php, templates/pdf/invoice-default.php
- **Commit:** 79cf968

## Issues Encountered

None beyond the post-checkpoint bugs documented above, which were resolved in a single fix commit.

## User Setup Required

None - all features work with existing WordPress installation.

## Phase 3 Completion

Plan 03-03 is the final plan in Phase 3. All six Phase 3 requirements (TMPL-01 through TMPL-06) are now complete:

- **TMPL-01** (Data model extensions) — completed in 03-01
- **TMPL-02** (Template settings tab) — completed in 03-01
- **TMPL-03** (Invoice editor payment method dropdown) — completed in 03-01
- **TMPL-04** (PDF template — Bulgarian business format) — completed in 03-02
- **TMPL-05** (PdfService dual render mode) — completed in 03-02
- **TMPL-06** (Email HTML body) — completed in 03-03

## Next Phase Readiness

- Phase 4 (Client Portal) can use the HTML email pattern established here for client-facing emails
- Phase 8 (Payment Gateways) can wire a real URL into the "Pay Invoice" button placeholder rendered in email mode
- Template settings (logo, accent color, sections) are fully functional and will apply to any future template additions

---
*Phase: 03-advanced-templates*
*Completed: 2026-03-20*
