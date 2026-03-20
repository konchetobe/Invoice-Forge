---
phase: quick-4
plan: 01
subsystem: client-management, invoicing, pdf
tags: [billing, client, ajax, pdf, i18n, validation]
dependency_graph:
  requires: []
  provides: [client-billing-fields-save-load, invoice-full-address, no-mandatory-client-fields]
  affects: [ClientAjaxHandler, ClientsPage, ClientPostType, PdfService, invoice-default-template, admin-js, client-editor-template]
tech_stack:
  added: []
  patterns: [WordPress post_meta, AJAX form data, PHP optional field validation, mPDF template variables]
key_files:
  created: []
  modified:
    - assets/admin/js/admin.js
    - src/Ajax/ClientAjaxHandler.php
    - src/Admin/Pages/ClientsPage.php
    - src/PostTypes/ClientPostType.php
    - templates/admin/client-editor.php
    - src/Services/PdfService.php
    - templates/pdf/invoice-default.php
decisions:
  - "All client fields are optional; email format is still validated when provided but no field is blocked"
  - "Company clients use company name as post_title directly (no parentheses); individual clients use first+last"
  - "PdfService uses private getClientDisplayName() helper for clean name resolution independent of post_title"
  - "Address line 2 rendered as 'ZIP CITY, STATE, COUNTRY' combining available components"
metrics:
  duration: ~15 minutes
  completed_date: "2026-03-20"
  tasks_completed: 2
  files_modified: 7
---

# Quick Task 4: Fix Client Billing Details Not Saving

**One-liner:** Full billing field save/load cycle (id_no, office, att_to, city, zip, state, country) with no mandatory fields at any layer (HTML, JS, PHP) and correct invoice address rendering.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Fix billing field save/load, remove mandatory constraints, fix client name and i18n | 5538f1d | admin.js, ClientAjaxHandler.php, ClientsPage.php, ClientPostType.php, client-editor.php |
| 2 | Fix invoice template to show full client address | 4227c16 | PdfService.php, invoice-default.php |

## What Was Fixed

### Task 1 - Billing Save/Load and Validation

**Problem:** Only `tax_id` was saved from the billing section. Fields `id_no`, `office`, `att_to` were silently dropped at every layer: missing from JS formData, not parsed in PHP, not saved to post_meta, not returned by `getClientData`. Additionally: all three core fields (first_name, last_name, email) were enforced as required via HTML `required` attributes, JS validation blocking, and PHP validation errors. Client post titles were constructed as "First Last (Company)".

**Fixes applied:**

1. `assets/admin/js/admin.js` - Added `id_no`, `office`, `att_to` to the formData object in the client AJAX submit handler.
2. `assets/admin/js/admin.js` - Removed the `if (!firstName || !lastName || !email)` block from inline invoice client creation. Email format is still validated conditionally (`if (email && !this.isValidEmail(email))`).
3. `src/Ajax/ClientAjaxHandler.php` - `saveClient()`: Removed `$errors[]` required validation for first_name/last_name/email. Added parsing and `update_post_meta` for `id_no`, `office`, `att_to`. Fixed title construction: company name used directly for company clients (no parentheses).
4. `src/Ajax/ClientAjaxHandler.php` - `createClientFromInvoice()`: Removed required field gate. Added parsing for `tax_id`, `id_no`, `office`, `att_to` (tax_id was entirely missing before - silent data loss). Added `update_post_meta` for all four billing fields. Fixed title construction.
5. `src/Ajax/ClientAjaxHandler.php` - `getClientData()`: Added `id_no`, `office`, `att_to` to returned array.
6. `src/Admin/Pages/ClientsPage.php` - `getClientData()`: Added `id_no`, `office`, `att_to` to returned array (required for editor pre-population on page load).
7. `src/PostTypes/ClientPostType.php` - `getDisplayName()`: Changed to return company name directly for company clients instead of `"$name ($company)"`.
8. `templates/admin/client-editor.php` - Removed `<span class="required">*</span>` and `required` attribute from first_name, last_name, email inputs. Changed `'Attention To'` msgid to `'Att To'` to match .po translation entries. Added `<p class="description">` help text below att_to field.

### Task 2 - Invoice Full Address

**Problem:** Invoice PDF/email only showed the street address. City, postal code, state, and country were never included in `getInvoiceData()` and thus never passed to the template. Also, `client_name` was sourced from `post_title` which for company clients would show "(Company)" suffix (now fixed by Task 1 too, but PdfService needed its own clean resolution independent of DB state).

**Fixes applied:**

1. `src/Services/PdfService.php` - `getInvoiceData()`: Added `client_city`, `client_state`, `client_zip`, `client_country` to the returned array. Changed `client_name` from `$client->post_title` to `$this->getClientDisplayName($client_id)`.
2. `src/Services/PdfService.php` - Added private helper `getClientDisplayName(int $client_id): string` that reads `_client_company`, `_client_first_name`, `_client_last_name` meta and returns company name for companies or first+last for individuals.
3. `templates/pdf/invoice-default.php` - Added city/zip/state/country rendering block in the buyer column immediately after the street address, building a combined `$addr_line2` string from available components. Updated docblock to document the four new template variables.

## Deviations from Plan

### Auto-fixed Issues

None - plan executed exactly as written.

### Silent Bug Found (Rule 2 - Missing Critical Functionality)

**`tax_id` was entirely absent from `createClientFromInvoice()`** - When clients were created inline from the invoice form, their tax_id (EIK/VAT number) was never saved. This was discovered while implementing the billing field additions and fixed as part of the planned work on that method.

## Self-Check: PASSED

All 7 modified files confirmed present on disk. Both task commits (5538f1d, 4227c16) confirmed in git log.
