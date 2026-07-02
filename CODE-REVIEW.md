# InvoiceForge — Code Review & Improvement Plan

A comprehensive review of the InvoiceForge WordPress invoice plugin covering security, architecture, business logic, and frontend assets. Findings are prioritized by severity.

---

## Executive Summary

The codebase demonstrates **strong fundamentals**: consistent `$wpdb->prepare()` usage, proper output escaping, nonce verification on all endpoints, AES-256-GCM encryption for SMTP passwords, `declare(strict_types=1)` everywhere, typed properties, and a clean DI container. The security posture for SQL injection, XSS (server-side), and CSRF is solid.

However, there were **6 critical issues**, several of which cause silent data corruption or broken functionality. The most impactful were: a race condition in invoice numbering that can produce duplicate invoice numbers, tax calculations that ignore line-item discounts, SMTP settings that are saved but never applied, and a missing production dependency (`mpdf`) that silently breaks PDF generation.

**All 6 critical issues have been fixed as of 2026-07-02.**

---

## Critical Issues — ALL FIXED ✅

### C1. Version mismatch breaks the update checker — ✅ FIXED
- **File:** `invoiceforge.php:33`
- Plugin header declares `Version: 1.2.8` but `INVOICEFORGE_VERSION` is defined as `'1.2.5'`. The `UpdateChecker` compares against the constant, so it always thinks the plugin is at 1.2.5 — causing update loops or missed updates.
- **Fix:** Set `INVOICEFORGE_VERSION` to `'1.2.8'` to match the header.
- **Done:** Updated `INVOICEFORGE_VERSION` from `'1.2.5'` to `'1.2.8'`.

### C2. Invoice numbering race condition — duplicate numbers possible — ✅ FIXED
- **File:** `src/Services/NumberingService.php` — `acquireLock()` (~line 310)
- The transient-based lock is not atomic. `set_transient()` overwrites unconditionally, so two concurrent processes can both set and verify the lock, both increment the counter, and produce duplicate invoice numbers. Additionally, `exists()` (which checks for duplicates) is never called during `generate()`.
- **Fix:** Replace the transient lock with an atomic operation. Call `exists()` after generation and retry on collision.
- **Done:** Replaced the non-atomic transient-based lock with MySQL's `GET_LOCK()`/`RELEASE_LOCK()` named locks, which are truly atomic and connection-scoped. Added a uniqueness check in `generate()` that calls `exists()` and retries with incremented numbers (up to 100 attempts) on collision, throwing a `RuntimeException` if no unique number can be found.

### C3. TaxService ignores line-item discounts — totals are wrong — ✅ FIXED
- **File:** `src/Services/TaxService.php` — `calculateItem()`
- The `LineItem` model has `discount_type` and `discount_value` fields, but `TaxService` never applies them. Subtotals, tax, and totals are calculated as if no line-item discount exists.
- **Fix:** Apply the discount (percentage or fixed) to the line subtotal before computing tax in `calculateItem()`.
- **Done:** Updated `calculateItem()` to compute the gross amount, apply the line-item discount (percentage or fixed) via a new `calculateItemDiscount()` helper, then compute tax on the discounted subtotal. The discount is clamped to never be negative or exceed the gross amount.

### C4. SMTP settings saved but never applied — ✅ FIXED
- **Files:** `src/Services/EmailService.php`, `src/Core/Plugin.php`
- Settings include `smtp_enabled`, `smtp_host`, `smtp_port`, etc., but no `phpmailer_init` action hook is registered anywhere. SMTP configuration is dead — `wp_mail()` always uses the default PHP mailer.
- **Fix:** Register a `phpmailer_init` hook that configures `$phpmailer` from the saved SMTP settings when `smtp_enabled` is true.
- **Done:** Added `Encryption` dependency to `EmailService` constructor (for decrypting the stored SMTP password). Added `configurePhpMailer()` method that reads SMTP settings and configures the PHPMailer instance (SMTP mode, host, port, auth, encryption, from address/name). Registered the `phpmailer_init` hook in `Plugin::registerHooks()`. Updated all 3 inline `new EmailService(...)` call sites (InvoiceAjaxHandler x2, WooCommerceIntegration x1) to pass the Encryption argument.

### C5. `mpdf/mpdf` missing from committed `vendor/` — ✅ FIXED
- **Files:** `composer.json` (declares `mpdf/mpdf: ^8.2`), `vendor/` (absent)
- `PdfService::isAvailable()` checks `class_exists(\Mpdf\Mpdf::class)` and degrades gracefully, but the class is never present. PDF generation silently fails for anyone installing from this repo without running `composer install`.
- **Fix:** Ensure the distribution ZIP includes mpdf. Add a build step that runs `composer install --no-dev` before packaging.
- **Done:** Root cause was a stale `composer.lock` that only contained `yahnis-elsts/plugin-update-checker` — mpdf was declared in `composer.json` but absent from the lock file. Removed the stale `composer.lock` so the next `composer install` resolves mpdf from `composer.json`. Created `scripts/build.sh` and `scripts/build.bat` build scripts that run `composer install --no-dev --optimize-autoloader` and verify mpdf is present. (Note: PHP/Composer were not available in the dev environment, so `composer install` must be run by the user or CI to populate `vendor/mpdf/`.)

### C6. XSS vulnerabilities in admin JavaScript — ✅ FIXED
- **File:** `assets/admin/js/admin.js`
  - `showToast()`: interpolates `message` directly into an HTML template literal. Server error messages could execute scripts.
  - `handleInvoiceSave()`: concatenates raw form input (`new_client_first_name`) into an `<option>` HTML string.
  - `initMediaUploader()`: concatenates `attachment.url` into `<img>` HTML.
- **Fix:** Use `.text()` for dynamic text content and DOM APIs / `.attr()` for attribute values instead of string concatenation into HTML.
- **Done:** Fixed all three: (1) `showToast()` now builds the toast element with an empty message div and sets the message via `.text()`; (2) `handleInvoiceSave()` now creates the `<option>` via `$('<option>').val(...).text(...).appendTo(...)`; (3) `initMediaUploader()` now creates the `<img>` via `$('<img>').attr('src', ...).attr('alt', '')`. Verified JS syntax with `node --check`.

---

## High Severity Issues (Not yet fixed)

### H1. IDOR — missing per-object capability checks in AJAX handlers
- **Files:** `src/Ajax/InvoiceAjaxHandler.php`, `src/Ajax/ClientAjaxHandler.php`
- All handlers check only the primitive capability (`edit_if_invoices`) but never verify the user can access the **specific** invoice/client. The `Capabilities` class has `canEditInvoice(int $post_id)` / `canEditClient(int $post_id)` but they're never called. A user with `edit_if_invoices` (without `edit_others_if_invoices`) can view/edit/delete any invoice by supplying an arbitrary ID.
- **Fix:** Add `current_user_can('edit_if_invoice', $invoice_id)` checks after the general capability check in every handler that takes an invoice/client ID.

### H2. Invoice-level discount applied after tax — tax overstated
- **File:** `src/Ajax/InvoiceAjaxHandler.php` — `saveInvoice()` (~line 230)
- Discount is subtracted from `subtotal + tax`, but `_invoice_tax_total` stores the pre-discount tax. The total becomes `subtotal + tax - discount` instead of `(subtotal - discount) + tax_on_discounted`.
- **Fix:** Apply the discount to the subtotal first, then compute tax on the discounted subtotal.

### H3. No transaction wrapping for line item saves
- **File:** `src/Ajax/InvoiceAjaxHandler.php` — `saveInvoice()`
- The handler calls `deleteByInvoice()` then re-inserts all items in a loop. If an insert fails mid-loop, all line items are lost with no rollback.
- **Fix:** Wrap the delete + re-insert in `$wpdb->query('START TRANSACTION')` / `COMMIT` / `ROLLBACK`.

### H4. No orphan cleanup on invoice deletion
- **Files:** Missing `delete_post` hook; `src/Repositories/LineItemRepository.php`
- `deleteInvoice()` uses `wp_trash_post()` which doesn't trigger line item cleanup. Deleted invoices leave orphaned rows in `invoiceforge_invoice_items`.
- **Fix:** Register a `delete_post` (or `trashed_post`) hook that calls `LineItemRepository::deleteByInvoice($post_id)` when the post type is `if_invoice`.

### H5. Missing `wp_unslash()` in Sanitizer — backslashes stored in data
- **File:** `src/Security/Sanitizer.php` (all methods)
- WordPress magic-quotes `$_POST`/`$_GET`. The Sanitizer calls `sanitize_text_field()` etc. but never `wp_unslash()` first. `sanitize_email()` doesn't unslash internally, so `O'Brien` becomes `O\'Brien`. The `Nonce` class correctly uses `wp_unslash()`, showing the pattern is known.
- **Fix:** Add `wp_unslash()` in each Sanitizer method before the sanitize call.

### H6. Triple-duplicated business logic
- `formatCurrency()` — identical in `InvoicePostType`, `AdminController`, `InvoicesPage` (and again in `admin.js` and `PdfService` with divergent currency maps).
- `getClientInvoiceCount()` — identical SQL in `ClientPostType`, `ClientAjaxHandler`, `ClientsPage`.
- `getInvoiceData()` — 3 copies with divergent field shapes in `InvoiceAjaxHandler`, `InvoicesPage`, `PdfService`.
- Client meta-saving logic — duplicated 4x across `ClientPostType`, `ClientAjaxHandler`, `WooCommerceIntegration`.
- **Fix:** Extract a `CurrencyFormatter` utility, a `ClientRepository`, and a shared `InvoiceDataAssembler` service. Consolidate client meta saving into one method.

---

## Medium Severity Issues (Not yet fixed)

### M1. Integrations tab settings never registered
- **File:** `src/Admin/Pages/SettingsPage.php`
- `TAB_FIELDS['integrations']` lists WooCommerce fields, but `register()` never calls an `addIntegrationsFields()` method. These settings can't be properly saved.

### M2. No status transition validation
- Any status can be set to any other (e.g., `cancelled → paid`, `paid → draft`). No state machine exists.

### M3. N+1 query in PDF template
- **File:** `templates/pdf/invoice-default.php` (~line 265)
- Executes a direct `$wpdb->get_var()` per line item to look up tax rates. Pre-load rates in `PdfService::getTemplateContext()` and pass them to the template.

### M4. Dead schema — `invoiceforge_payments` table
- **File:** `src/Database/Schema.php`
- The payments table is created on activation but has no repository, model, or any code reference. Dead schema wasting space.

### M5. Schema migrations never executed
- `Schema::migrate()` and `needsUpgrade()` exist but are never called from any hook. The `admin_init` hook doesn't check for upgrades.

### M6. Uninstall misses `invoiceforge_last_invoice_month`
- **File:** `uninstall.php`
- Deletes `invoiceforge_last_invoice_number` and `_year` but not `_month` (added in v1.4.0). The option persists after uninstall.

### M7. Nonce action inconsistency
- PDF endpoints (`downloadPdf`, `previewPdf`) verify against `invoiceforge_ajax`; all other endpoints use `invoiceforge_admin` (via the `Nonce` class which prefixes it). The WooCommerce order-page PDF link creates a nonce with `invoiceforge_admin` but the handler checks `invoiceforge_ajax` — so that link **always fails**. Standardize on one action.

### M8. No circular dependency detection in Container
- **File:** `src/Core/Container.php:88`
- `resolve()` passes `$this` to factories with no depth tracking. A cycle (A→B→A) causes a stack overflow.

### M9. Dead code
- `NumberingService::format()`, `parse()` — never called.
- `Schema::migrate()`, `dropTables()`, `getTableStatus()`, `needsUpgrade()`, `tablesExist()` — never called.
- `InvoiceAjaxHandler` receives `PdfService`/`EmailService` via constructor but creates `new` instances inline instead of using them.
- ~25 unused accessor methods across `Loader`, `Container`, `Plugin`, `Capabilities`, `Assets`, page classes, `Logger`.

### M10. Dev dependencies committed to production `vendor/`
- `vendor/` contains PHPUnit, PHPStan, PHPCS, WPCS and all transitive deps (~14 packages). Only `yahnis-elsts/plugin-update-checker` is a real production dependency. Use `composer install --no-dev` for distribution builds.

### M11. No test suite
- `phpunit` and `phpstan` are dev dependencies, but no `tests/` directory, no `phpunit.xml`, no `phpstan.neon` exist. The `composer.json` `autoload-dev` references a non-existent `tests/` directory.

### M12. God class — `SettingsPage` (~700+ lines)
- Contains settings registration, 10+ field render methods, sanitization, template handling, defaults, and tabs in one class. Split into field-renderer traits or sub-classes per tab.

### M13. DI container bypassed for WooCommerceIntegration and UpdateChecker
- **File:** `src/Core/Plugin.php` — `boot()`
- Both are instantiated with `new` and manual dependency wiring instead of being registered in the container.

### M14. Missing i18n keys in JavaScript
- **File:** `assets/admin/js/admin.js`
- 6 keys referenced in JS (`saveError`, `networkError`, `validationError`, `dueDateWarning`, `confirmEmail`, `resetCounterConfirm`) are never defined in `wp_localize_script`. They fall back to hardcoded English and can't be translated.

### M15. Accessibility gaps
- Modals: no `role="dialog"`, no focus trapping, no Escape key, no focus restoration.
- Toasts: no `aria-live` / `role="alert"`, unlabeled close button.
- Filter tabs: no ARIA tablist semantics.
- Drag-and-drop section editor: no keyboard alternative.
- Forms: search inputs lack `<label>`, missing `aria-required` / `aria-invalid`.

### M16. Inline JavaScript in templates
- `templates/admin/invoice-editor.php` has ~200 lines of inline `<script>` with critical line-item logic. `settings.php` has a smaller inline script. Extract to `.js` files and enqueue properly.

### M17. Predictable encryption key fallback
- **File:** `src/Security/Encryption.php:57`
- If `AUTH_KEY`/`SECURE_AUTH_KEY` are undefined, falls back to `'invoiceforge-default-key-' . ABSPATH` — predictable and publicly known. Should refuse to encrypt instead.

### M18. Raw exception message exposed to client
- **File:** `src/Ajax/InvoiceAjaxHandler.php` — `generateFromOrder()` (~line 640)
- Returns `$e->getMessage()` directly in the JSON response, leaking internal paths/DB errors. All other handlers return a generic message.

### M19. Log `.htaccess` uses deprecated Apache 2.2 syntax
- **File:** `src/Utilities/Logger.php:233`
- `Order deny,allow\nDeny from all` is deprecated in Apache 2.4+ and ineffective on nginx/LiteSpeed. Use `Require all denied`. Consider storing logs outside the web root.

### M20. Validation gaps
- No due-date-vs-invoice-date validation (due date can precede invoice date).
- `Sanitizer::money()` allows negative values; line item unit prices aren't clamped to ≥ 0.
- `LineItem::fromArray()` doesn't validate `quantity > 0` or non-empty description.
- No currency validation against allowed list in `saveInvoice()`.
- No client existence/type validation in `saveInvoice()`.

### M21. `generateFromOrder` duplicate check incomplete
- **File:** `src/Ajax/InvoiceAjaxHandler.php`
- `handleOrderStatusChange()` checks for existing invoices, but the AJAX `generateFromOrder()` endpoint doesn't — repeated clicks create duplicate invoices from the same order.

### M22. No automatic overdue marking
- The `overdue` status exists but is never set automatically. No cron job or scheduled event checks due dates.

### M23. Currency formatting ignores zero-decimal currencies
- `formatCurrency()` hardcodes 2 decimal places. JPY, KRW, etc. display incorrectly.

### M24. Preview vs. save calculation mismatch
- **File:** `src/Services/PdfService.php` — `renderPreviewHtml()`
- Preview applies line-item discounts as flat amounts (ignoring `discount_type`), while `TaxService` ignores them entirely and the AJAX handler applies invoice-level discounts after tax. Three different discount strategies produce three different totals.

---

## Low Severity Issues (Not yet fixed)

- **L1.** No template override mechanism — PDF/admin templates loaded directly from plugin dir, no `locate_template()` or filter.
- **L2.** Duplicate CSS rule `.invoiceforge-btn-sm` defined twice in `admin.css`.
- **L3.** `!important` on `.invoiceforge-table-empty` padding.
- **L4.** Extensive inline `style="..."` attributes in PHP templates undermine the CSS architecture.
- **L5.** jQuery/vanilla JS inconsistency across files and within `admin.js`.
- **L6.** No event namespacing in `admin.js` — handlers delegated to `$(document)` without `.invoiceforge` namespace.
- **L7.** Redundant `@keyframes spin` injection in `handleSendEmail()` — already defined in `admin.css`.
- **L8.** Client/server precision mismatch — client uses 2-decimal, server uses 4-decimal for line items; edge cases cause ±0.01 discrepancies.
- **L9.** Missing return types on `Plugin::__clone()` and `__wakeup()` (should be `: void`).
- **L10.** Misplaced docblock in `PdfService` — `getFallbackHtml()` docblock placed before `getClientDisplayName()`.
- **L11.** `Activator::flushRewriteRules()` bypasses the DI container, manually wiring dependencies with `new`.
- **L12.** Contradictory post type config — `rewrite` slug set but `publicly_queryable => false` (dead rewrite rules).
- **L13.** `STATUSES` constant has untranslated labels (only `getStatuses()` translates them).
- **L14.** Temp file collision risk in `EmailService` — filename uses `time()`, collisions possible within the same second. Use `wp_tempnam()`.
- **L15.** Missing HTTP status code in `setInvoiceCounter` error response (defaults to 200 OK).
- **L16.** `Assets::getLocalizedData()` creates nonce directly via `wp_create_nonce()` instead of the `Nonce` service.
- **L17.** No `readonly` properties or enums used despite PHP 8.1+ target.
- **L18.** No foreign keys on custom tables — `invoice_id` references posts but no FK constraint (orphan accumulation, see H4).

---

## Recommended Improvement Phases

### Phase 1 — Critical fixes (data integrity & security) — ✅ COMPLETE
1. ✅ Fix version constant (C1)
2. ✅ Fix NumberingService race condition + call `exists()` (C2)
3. ✅ Fix TaxService discount calculation (C3)
4. ✅ Wire up SMTP `phpmailer_init` hook (C4)
5. ✅ Ensure `mpdf/mpdf` is in the distribution (C5)
6. ✅ Fix XSS in `admin.js` (C6)

### Phase 2 — Correctness & consistency (HIGH severity)
1. Add per-object capability checks to AJAX handlers (H1)
2. Fix discount-after-tax calculation (H2)
3. Add transaction wrapping for line item saves (H3)
4. Add orphan cleanup hook (H4)
5. Add `wp_unslash()` to Sanitizer (H5)
6. Extract duplicated business logic (H6)
7. Register integrations tab settings (M1)
8. Standardize nonce actions (M7)
9. Fix `generateFromOrder` duplicate check (M21)
10. Add validation: due dates, negative amounts, line items, currency, client existence (M20)
11. Fix uninstall to remove `_month` option (M6)
12. Fix raw exception exposure (M18)
13. Fix encryption key fallback (M17)
14. Update log `.htaccess` to Apache 2.4 syntax (M19)
15. Wire up schema migrations on `admin_init` (M5)
16. Add missing JS i18n keys (M14)

### Phase 3 — Architecture & deduplication
1. Remove dead code (M9)
2. Remove dev deps from production `vendor/` (M10)
3. Register WooCommerceIntegration/UpdateChecker in container (M13)
4. Add circular dependency detection to Container (M8)
5. Split `SettingsPage` god class (M12)
6. Extract inline JS to enqueued files (M16)
7. Add template override mechanism (L1)

### Phase 4 — Quality & polish
1. Set up PHPUnit + PHPStan with configs and initial tests (M11)
2. Accessibility: ARIA roles, focus management, keyboard nav (M15)
3. CSS cleanup: dedupe rules, remove `!important`, extract inline styles (L2–L4)
4. Standardize JS paradigm and add event namespacing (L5–L6)
5. Add status transition state machine (M2)
6. Add automatic overdue marking via cron (M22)
7. Support zero-decimal currencies (M23)
8. Align preview and save calculations (M24)
9. Apply `readonly` properties and enums where appropriate (L17)

---

## Files Changed in Phase 1 (Critical Fixes)

| File | Change |
|------|--------|
| `invoiceforge.php` | C1: `INVOICEFORGE_VERSION` updated from `1.2.5` to `1.2.8` |
| `src/Services/NumberingService.php` | C2: Replaced transient lock with MySQL `GET_LOCK()`/`RELEASE_LOCK()`; added uniqueness check with `exists()` + retry loop |
| `src/Services/TaxService.php` | C3: `calculateItem()` now applies line-item discounts before tax; added `calculateItemDiscount()` helper |
| `src/Services/EmailService.php` | C4: Added `Encryption` dependency; added `configurePhpMailer()` method for `phpmailer_init` hook |
| `src/Core/Plugin.php` | C4: Updated `email_service` container registration; registered `phpmailer_init` hook |
| `src/Ajax/InvoiceAjaxHandler.php` | C4: Updated 2 inline `new EmailService(...)` calls to pass `Encryption` argument |
| `src/Integrations/WooCommerce/WooCommerceIntegration.php` | C4: Updated inline `new EmailService(...)` call to pass `Encryption` argument |
| `assets/admin/js/admin.js` | C6: Fixed XSS in `showToast()`, `handleInvoiceSave()`, `initMediaUploader()` |
| `composer.lock` | C5: Deleted stale lock file (missing mpdf) |
| `scripts/build.sh` | C5: New build script for production dependency installation |
| `scripts/build.bat` | C5: New Windows build script for production dependency installation |
