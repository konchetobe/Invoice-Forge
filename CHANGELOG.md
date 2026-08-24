# Changelog

All notable changes to InvoiceForge will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [1.3.1] - 2026-07-02

### Fixed
- **Complete native translations for all 10 languages**: The v1.3.0 translation work used English fallback strings for hundreds of entries, leaving most languages only ~20% translated. This release adds real native translations for every user-facing string across all languages (German, Spanish, French, Italian, Dutch, Polish, Portuguese, Romanian, Russian) and completes Bulgarian coverage. Country names remain in English as proper nouns (matching the source country list), and identical-word acronyms like BIC/Bank are correctly unchanged. No empty strings remain in any language file.

---

## [1.3.0] - 2026-07-20

### Added
- **Person/Company toggle for client forms**: New individual-vs-company switch in both the standalone Client editor and the inline client creation form on the Invoice editor. When "Company" is selected, company name becomes required and the Billing Information fields (Tax ID, ID No, Office/Branch, Att To) appear. When "Individual" is selected, first/last name are required and billing fields are hidden.
- **Inline billing fields on invoice editor**: The four missing billing fields (Tax ID / VAT Number, ID No / EIK / BULSTAT, Office / Branch, Att To) are now available when creating a client inline from the Invoice editor. Previously these fields were only accessible from the standalone Client editor and were silently stored as empty strings when creating clients from an invoice.
- **`.pot` translation template**: New `languages/invoiceforge.pot` file generated from the full source code scan, exposing 545 translatable strings. This allows translators to update existing `.po` files without manual string extraction.
- **Complete translation coverage**: All 10 language `.po`/`.mo` files now include every translatable string from the plugin source, preventing "undefined" messages in JavaScript and missing labels in the UI. Bulgarian (`bg_BG`) received 298 fully translated strings covering dashboard, invoice editor, client forms, settings, AJAX responses, and tax/payment/currency labels.
- **Translation generation scripts**: Added `scripts/generate-pot.php` (extracts translatable strings from source) and `scripts/merge-translations.php` (merges new strings into all `.po` files and compiles `.mo` binaries), enabling reproducible translation updates.

### Changed
- **Due Date is now optional**: Removed the `+30 days` default that pre-filled the due date field for new invoices. The field starts empty; users can set a due date if needed. The classic editor meta box and the PDF preview no longer inject a `+30 days` default. The reminder email no longer references "Original due date" when the invoice has no due date.
- **Due date validation relaxed**: The date validation in `admin.js` only warns when both dates are set and the due date precedes the invoice date. Empty due date is silently accepted everywhere (save handler, PDF rendering, email rendering, admin list columns).
- **Expanded JavaScript i18n**: `Assets::getLocalizedData()` now exposes 24 keys covering all user-facing strings previously hardcoded in English in `admin.js`, including `saveError`, `networkError`, `validationError`, `emailSent`, `emailFailed`, `counterResetFailed`, `updating`, `upToDate`, `previewUnavailable`, `fieldLabel`, `left`, `right`, `remove`, etc. Fixed the `dueDateBefore` / `dueDateWarning` key mismatch that prevented the date warning from being translated.

### Fixed
- **Signature fields alignment in Settings → Template**: Moved the signature field repeater inside a `form-table` row so it lines up with the surrounding column-title fields above. Previously the rows were rendered outside the form-table, causing a ~200px leftward shift.
- **Signature field radio name bug**: The radio inputs for Left/Right column selection used `uniqid()` twice per row (once per radio), producing different `name` attributes for empty-label fields and breaking radio grouping. Replaced with a stable index plus `md5(label)` so both radios in a pair always share the same name.
- **Signature field responsive layout**: Added `flex-wrap: wrap` to repeater rows and `flex-shrink: 0` / `white-space: nowrap` to radio labels and remove buttons, preventing the Left/Right labels and buttons from being compressed on narrow screens.
- **JavaScript toast error messages**: Replaced remaining hardcoded English strings in `admin.js` (invoice title required, counter value invalid, email sent/failed, preview status, network errors) with `InvoiceForge.i18n.*` references that fall back to English if the localized string is missing.
- **WordPress.org-compliant constants and IDs**: All 10 new AJAX i18n keys are properly wrapped in `__()` calls so they appear in `.pot` files and are translatable.

### Notes
- **Backwards compatibility**: All changes preserve existing invoice/client data. Existing invoices with a `+30 days` due date retain that value; the change only affects new invoices going forward.
- **Database**: No schema changes. No migration needed.
- **Translation workflow**: Run `php scripts/generate-pot.php` after adding new translatable strings, then `php scripts/merge-translations.php` to propagate them across all 10 language files with English fallback. Translators can then update specific `.po` files with native translations.

---

## [1.2.9] - 2026-07-02

### Fixed
- **Critical: Version mismatch** — `INVOICEFORGE_VERSION` constant was stuck at `1.2.5` while the plugin header declared `1.2.8`, causing the update checker to malfunction. Both now correctly read `1.2.9`.
- **Critical: Invoice numbering race condition** — Replaced the non-atomic transient-based lock with MySQL `GET_LOCK()`/`RELEASE_LOCK()` named locks, preventing concurrent processes from generating duplicate invoice numbers. Added a uniqueness check that calls `exists()` and retries on collision.
- **Critical: Line-item discounts ignored in tax calculation** — `TaxService::calculateItem()` now applies line-item discounts (percentage or fixed) to the subtotal before computing tax, so totals are correct when discounts are used.
- **Critical: SMTP settings never applied** — Added `configurePhpMailer()` method to `EmailService` and registered the `phpmailer_init` hook, so saved SMTP settings (host, port, auth, encryption) are now actually used by `wp_mail()`.
- **Critical: Missing `mpdf/mpdf` in vendor** — Removed a stale `composer.lock` that excluded mpdf, so the release build now correctly installs the PDF library. Added `scripts/build.sh` and `scripts/build.bat` for production builds.
- **Critical: XSS vulnerabilities in admin JavaScript** — Fixed three XSS vectors in `admin.js`: toast messages now use `.text()`, client name `<option>` creation uses DOM API, and media uploader `<img>` creation uses `.attr()` instead of HTML string concatenation.

---

## [1.2.8] - 2026-06-18

### Fixed
- Corrupted property declaration and duplicate `pdfService`/`emailService` assignments in `InvoiceAjaxHandler`.

---

## [1.2.7] - 2026-06-18

### Fixed
- UTF-8 BOM issue in `InvoiceAjaxHandler.php` causing fatal errors on some server configurations.

---

## [1.2.6] - 2026-05-30

### Fixed
- Orphaned AJAX hooks: implemented missing `saveTaxRate` and `deleteTaxRate` handler methods that caused fatal errors when invoked.
- WooCommerce integration TypeError: `LineItemRepository::save()` now receives a proper `LineItem` object instead of a raw array, preventing crashes when generating invoices from orders.
- Tax meta key mismatch: invoice tax is now consistently stored and read as `_invoice_tax_total`, fixing PDF templates showing $0.00 for tax.
- Data leakage: AJAX handlers now log only posted field keys instead of raw `$_POST` values, preventing sensitive data from being written to log files.
- Capability fallback: changed from `edit_posts` (too broad — granted access to all Authors/Editors) to `manage_options` (admin-only) for invoice and client management.
- Clients page capability check: `renderClients()` now correctly checks `canEditClients()` instead of `canEditInvoices()`.
- Defense-in-depth: `createClientFromInvoice()` now verifies caller has invoice edit permission before creating clients.
- Performance: `posts_per_page=-1` in admin dropdown queries limited to 500 to prevent memory exhaustion on sites with large client lists.
- DI container: `PdfService` and `EmailService` now registered in the DI container and injected into `InvoiceAjaxHandler` for improved testability.

---

## [1.1.5] - 2026-03-14

### Fixed
- Added `mpdf/mpdf` as a required composer dependency so it will be bundled inside the release ZIP, fixing the blank screen when exporting PDFs.
- Updated the GitHub Actions release workflow to use `composer update` to pick up the new PDF dependency.
- Fixed the JavaScript AJAX error handler to show the actual server error message (e.g. "Failed to send email") instead of assuming a HTTP 500 status code is always a "Network error".

---

## [1.1.4] - 2026-03-14

### Fixed
- Fixed a fatal error when clicking "Download PDF" by restoring the missing `downloadPdf` and `previewPdf` methods in `InvoiceAjaxHandler.php`.
- Fixed the "Send Email" AJAX network error by correctly routing the method call to `sendInvoice` instead of `sendInvoiceEmail` in the `EmailService`.

---

## [1.1.3] - 2026-03-14

### Fixed
- Fixed the "Send Email" functionality by implementing the missing `invoiceforge_send_email` AJAX handler in `admin.js`.
- Wired up the "Send Email" buttons in the Invoice Editor and Invoices List to actually send the email + PDF to the client.

### Changed
- Renamed the GitHub Actions release artifact to `invoiceforge-plugin-installable.zip` to clearly differentiate it from GitHub's default "Source code (zip)".

---

## [1.1.2] - 2026-03-14

### Added
- Added "Download PDF" and "Send Email" action buttons to the Invoice Editor (for existing custom invoices).
- Added "Download PDF" action icon to the Invoices list table next to Edit and Delete.

---

## [1.1.1] - 2026-03-14

### Fixed
- Fixed critical error on plugin activation caused by missing `UpdateChecker.php`, `PdfService.php`, and `EmailService.php` files.
- Added `plugin-update-checker` to vendor dependencies.
- Added GitHub Actions workflow `release.yml` for automated ZIP builds.

---

## [1.1.0] - 2026-03-14

This is the first public release of InvoiceForge. It covers the complete Phase 1 implementation (1A through 1D) plus WooCommerce integration, GitHub-based auto-updates, and PDF/Email delivery.

### Core Architecture (Phase 1A)
- Plugin skeleton with PSR-4 autoloading via Composer (PHP 8.1+, WordPress 6.0+)
- Singleton `Plugin.php` orchestrator with DI container (`Container.php`)
- Hook management via `Loader.php`
- Activation/deactivation hooks (`Activator.php`, `Deactivator.php`)
- Full security layer: `Nonce.php`, `Capabilities.php`, `Sanitizer.php`, `Validator.php`, `Encryption.php`
- File-based logging via `Logger.php`
- Sequential invoice numbering via `NumberingService.php`
- Custom database schema (`Schema.php`) — invoice items and tax rates tables

### Custom Post Types
- **Invoice CPT** (`if_invoice`) — with full meta box support, admin columns, sortable columns
- **Client CPT** (`if_client`) — supports both individuals (first/last name) and companies

### Modern Admin Interface
- Custom admin pages replacing default WordPress CPT screens (SaaS-style UI)
- CSS design system with variables (dark-mode ready, responsive grids)
- Invoice list with status filtering, search, and bulk actions
- Invoice editor with AJAX form submission
- Client list and client editor
- Toast notification system
- Empty state illustrations
- Inline client creation from the invoice editor (create a new client without leaving the invoice form)

### Line Items & Calculations (Phase 1B)
- Dynamic line item rows with add/remove
- Auto-calculation of subtotals, per-item tax, and grand total in real time
- Tax rate management interface (Settings → Tax Rates tab) with AJAX CRUD
- `LineItemRepository` and `TaxRateRepository` for database access
- `TaxService` for calculation logic
- Payment instructions field on invoices
- Terms & conditions and internal notes fields
- Discount field (percentage or fixed amount)
- Auto-save drafts every 60 seconds with timestamp indicator

### PDF Generation & Email (Phase 1C)
- `PdfService` with mPDF integration — single invoice PDF and batch generation
- PDF preview (inline in browser) and download with proper Content-Disposition headers
- `EmailService` using `wp_mail` with optional SMTP configuration
- PDF invoice attachment on outgoing emails
- Email logging per invoice
- Payment reminder emails
- Professional A4 PDF template (`templates/pdf/invoice-default.php`)
- Branded invoice email template (`templates/email/invoice-sent.php`)
- Payment reminder email template (`templates/email/payment-reminder.php`)
- AJAX endpoints: `downloadPdf`, `previewPdf`, `sendInvoiceEmail`, `sendReminder`

### Dashboard & Analytics (Phase 1D)
- Overview dashboard with revenue, paid/outstanding/overdue totals
- Monthly revenue bar chart (last 12 months, Chart.js)
- Invoice status breakdown doughnut chart
- Recent payments list (last 5 paid invoices)
- Top clients by revenue (top 5)

### WooCommerce Integration (Phase 2)
- `WooCommerceIntegration` class — hooks into any configured WooCommerce order status
- Full order-to-invoice mapping: line items, shipping, taxes → InvoiceForge line items
- Client sync: matches existing client by email or creates a new one from billing data
- Bidirectional link: WC order stores the invoice ID; invoice stores the order ID
- Automatic PDF generation and email delivery on invoice creation (configurable)
- Manual "Generate Invoice" button on the WooCommerce Order edit page (meta box)
- **Settings → Integrations tab:**
  - Enable/disable toggle
  - Multi-select trigger order statuses
  - Invoice number format: InvoiceForge sequential OR WooCommerce order number with custom prefix
  - Auto-email toggle
- **Invoices list source tabs:** All Invoices | Custom Invoices | WooCommerce Orders (with counts)
- Gracefully disabled/hidden when WooCommerce is not active

### Bug Fixes
- Fixed nonce field name mismatch in `InvoicePostType` and `ClientPostType` (meta data not saving)
- Fixed settings tabs overwriting each other instead of merging
- Fixed missing `isValidEmail()` method in `Validator.php` causing fatal errors
- Fixed AJAX capability checks to accept both custom and standard WordPress capabilities
- Added comprehensive try/catch error logging in AJAX handlers

### Multilingual
- Bulgarian translations (`languages/invoiceforge-bg_BG.po` / `.mo`)
- Language selection setting in Settings → General
- All user-facing strings wrapped in WordPress i18n functions

### GitHub Auto-Updates
- `UpdateChecker` class using `yahnis-elsts/plugin-update-checker` v5
- Points at `konchetobe/Invoice-Forge` GitHub releases
- WordPress admin shows update notification when a new release is published
- "View Changelog" link added to the WordPress Plugins page row
- GitHub Actions workflow (`.github/workflows/release.yml`): pushes a `vX.Y.Z` tag → auto-builds a clean production ZIP → attaches to the GitHub Release

### Requirements
- PHP 8.1+
- WordPress 6.0+
- WooCommerce (optional, for WooCommerce integration features)
- mPDF (optional, run `composer require mpdf/mpdf` for PDF generation)
