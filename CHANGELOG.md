# Changelog

All notable changes to InvoiceForge will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

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
