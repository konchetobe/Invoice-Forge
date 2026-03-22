---
phase: quick-7
verified: 2026-03-22T00:00:00Z
status: human_needed
score: 6/7 must-haves verified
re_verification: false
human_verification:
  - test: "Open Add New Invoice page in WordPress admin and confirm preview panel appears in 3-column layout without saving"
    expected: "Preview panel renders on the right side immediately, showing form data as fields are filled"
    why_human: "Requires live WordPress environment with the plugin loaded to confirm visual layout and AJAX calls fire correctly"
  - test: "Fill in fields (client, dates, line item) on new invoice and wait 500ms"
    expected: "Preview updates live and invoice number shows '(Not yet assigned)'"
    why_human: "Live AJAX debounce behavior and rendered HTML content cannot be verified statically"
  - test: "Click the PDF toggle button in the preview header"
    expected: "Preview reloads showing PDF render mode output; PDF button turns blue/active"
    why_human: "Toggle behavior and render mode differences require running the UI"
  - test: "Click the Email toggle button"
    expected: "Preview reloads in email mode; Email button turns blue/active"
    why_human: "Toggle state transition requires live UI interaction"
  - test: "Save a new invoice, then verify preview continues working"
    expected: "After save, preview seamlessly continues working using the real invoice_id returned by the server"
    why_human: "Requires live save flow to verify hidden field update and subsequent AJAX preview call"
  - test: "Open an existing invoice and confirm preview works as before"
    expected: "No regression — existing invoice preview behaves identically to before this task"
    why_human: "Regression verification requires live data"
---

# Quick Task 7: Fix Invoice Preview for New Invoices — Verification Report

**Task Goal:** Fix invoice preview: show for new invoices, add email/PDF preview toggle
**Verified:** 2026-03-22
**Status:** human_needed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| #  | Truth                                                                              | Status     | Evidence                                                                                        |
|----|------------------------------------------------------------------------------------|------------|-------------------------------------------------------------------------------------------------|
| 1  | New invoice editor shows live preview panel (not just 'Save first' placeholder)    | VERIFIED   | `invoice-editor.php` line 107: always uses `invoiceforge-editor-with-preview`; panel at line 481 is unconditional |
| 2  | Preview renders from form data alone when invoice_id is 0 (unsaved)               | VERIFIED   | `PdfService::renderPreviewHtml` has explicit `if ($invoice_id === 0)` branch (line 179) building full invoice array from `$form_overrides` with no DB call |
| 3  | After saving a new invoice, preview continues working with the real invoice_id     | VERIFIED   | `admin.js` line 254: `$form.find('[name="invoice_id"]').val(response.data.invoice_id)` updates hidden field after save; `loadPreview()` called immediately after |
| 4  | Email/PDF toggle buttons appear in preview panel header                            | VERIFIED   | `invoice-editor.php` lines 485–491: `.invoiceforge-preview-toggles` div with two `.invoiceforge-preview-toggle` buttons, `data-mode="email"` (active) and `data-mode="pdf"` |
| 5  | Clicking Email toggle renders email-mode HTML (inline styles, no mPDF fonts)       | ? UNCERTAIN | JS toggle handler wired correctly (`_previewRenderMode` updated, `loadPreview()` called, `render_mode` sent to AJAX); backend routes `'email'` mode to `getTemplateContext` with `render_mode='email'`. Cannot verify rendered output without live environment. |
| 6  | Clicking PDF toggle renders pdf-mode HTML (default, current behavior)              | ? UNCERTAIN | Same as truth 5 — wiring is correct but output needs live verification                          |
| 7  | Existing invoice preview continues to work exactly as before                       | VERIFIED   | `PdfService::renderPreviewHtml` `$invoice_id > 0` path (line 216) is unchanged; `InvoiceAjaxHandler::previewInvoiceHtml` only changed `<= 0` to `< 0`; existing `loadPreview` flow unaltered |

**Score:** 5 fully verified + 2 uncertain (wiring verified, output unverifiable without live env) = 6/7 truths at implementation level

### Required Artifacts

| Artifact                              | Expected                                                                | Status     | Details                                                                                         |
|---------------------------------------|-------------------------------------------------------------------------|------------|-------------------------------------------------------------------------------------------------|
| `src/Services/PdfService.php`         | `renderPreviewHtml` supports invoice_id=0; accepts render_mode param    | VERIFIED   | Signature: `renderPreviewHtml(int $invoice_id, array $form_overrides = [], string $render_mode = 'email'): string`; line 179 `if ($invoice_id === 0)` branch; line 316 passes `$render_mode` to `getTemplateContext` |
| `src/Ajax/InvoiceAjaxHandler.php`     | `previewInvoiceHtml` allows invoice_id=0; passes render_mode to PdfService | VERIFIED | Line 921: `if ($invoice_id < 0)` (was `<= 0`); line 927: `$render_mode` extracted from POST; line 992: `renderPreviewHtml($invoice_id, $form_overrides, $render_mode)` |
| `templates/admin/invoice-editor.php`  | Preview panel always rendered; email/pdf toggle buttons                 | VERIFIED   | Line 107: unconditional `invoiceforge-editor-with-preview`; lines 481–492: preview panel with `.invoiceforge-preview-toggles` div, no `$is_new` gate |
| `assets/admin/js/admin.js`            | `initInvoicePreview` works for new invoices; toggle sends render_mode   | VERIFIED   | No `invoiceId <= 0` early return in `loadPreview` or `initInvoicePreview`; `_previewRenderMode` stored; toggle click handler binds; `render_mode` in AJAX data |
| `assets/admin/css/admin.css`          | Toggle button styles in preview header                                  | VERIFIED   | Lines 1476–1507: `.invoiceforge-preview-toggles`, `.invoiceforge-preview-toggle`, `.active`, `:hover` styles present with WordPress admin color scheme |

### Key Link Verification

| From                              | To                                | Via                                                    | Status  | Details                                                                                  |
|-----------------------------------|-----------------------------------|--------------------------------------------------------|---------|------------------------------------------------------------------------------------------|
| `assets/admin/js/admin.js`        | `src/Ajax/InvoiceAjaxHandler.php` | AJAX `invoiceforge_preview_invoice_html` with render_mode | WIRED | `admin.js` line 885/888: `action: 'invoiceforge_preview_invoice_html'` + `render_mode: this._previewRenderMode`; handler registered line 155 of AjaxHandler |
| `src/Ajax/InvoiceAjaxHandler.php` | `src/Services/PdfService.php`     | `renderPreviewHtml($invoice_id, $form_overrides, $render_mode)` | WIRED | AjaxHandler line 992: `$pdfService->renderPreviewHtml($invoice_id, $form_overrides, $render_mode)` with three-argument call |
| `templates/admin/invoice-editor.php` | `assets/admin/js/admin.js`     | Preview panel HTML always present; JS initializes for both new and existing | WIRED | `invoiceforge-preview-panel` div always rendered; `initInvoicePreview` checks only for `#invoiceforge-preview-frame` existence (not invoice_id) |

### Requirements Coverage

| Requirement | Source Plan | Description                                              | Status      | Evidence                                                     |
|-------------|-------------|----------------------------------------------------------|-------------|--------------------------------------------------------------|
| QUICK-7     | 7-PLAN.md   | Fix invoice preview: show for new invoices; email/PDF toggle | SATISFIED | All five artifacts modified as specified; both backend and frontend wiring verified |

### Anti-Patterns Found

No stubs, placeholders, empty implementations, or TODO/FIXME comments found in any of the five modified files. No `return null`/`return []` stubs in relevant methods.

### Human Verification Required

The automated checks confirm that all code paths, wiring, and logic are correctly implemented. The following items require a live WordPress environment to fully confirm:

#### 1. New Invoice Preview Renders Live

**Test:** Navigate to InvoiceForge > Add New Invoice. Confirm the preview panel appears in the 3-column layout immediately (without saving).
**Expected:** Preview panel visible on the right; fills with invoice template HTML as form fields are populated.
**Why human:** Cannot confirm AJAX fires, WordPress nonce validates, and PHP template renders without a running site.

#### 2. Invoice Number Shows "(Not yet assigned)"

**Test:** On the new invoice page, leave the invoice number field empty and observe the preview.
**Expected:** The preview HTML shows the translated string "(Not yet assigned)" in the invoice number position.
**Why human:** Requires rendered HTML output from live AJAX call.

#### 3. Email/PDF Toggle Switches Render Mode

**Test:** Click the PDF button in the preview header, then the Email button.
**Expected:** PDF click reloads preview with mPDF-style output (no inline styles optimized for email); Email click returns to email-optimized HTML. Active button turns blue.
**Why human:** Render mode differences are visual and template-level; cannot diff without live output.

#### 4. Post-Save Preview Continuity

**Test:** Fill in a new invoice, save it, then edit a field and observe the preview updating.
**Expected:** Preview loads correctly with the new invoice_id after save; no errors in browser console.
**Why human:** Requires full save flow to trigger hidden-field update and confirm AJAX preview chain.

#### 5. Existing Invoice No Regression

**Test:** Open any existing saved invoice and confirm the preview panel still loads and updates as it did before this change.
**Expected:** Identical behavior to pre-task state.
**Why human:** Regression confirmation requires live data and visual comparison.

### Gaps Summary

No gaps found in the implementation. All must-have artifacts exist with substantive implementations, all key links are wired. The two "uncertain" truths (#5 and #6 regarding rendered output of email vs PDF mode) are uncertain only because verifying rendered template output requires a live environment — the code paths routing to each mode are correctly implemented and connected.

The remaining unverified items are all visual/behavioral and belong to the pending Task 3 (checkpoint:human-verify) which was always planned as a human gate.

---

_Verified: 2026-03-22_
_Verifier: Claude (gsd-verifier)_
