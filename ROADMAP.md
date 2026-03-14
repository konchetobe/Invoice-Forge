# InvoiceForge Development Roadmap

This document outlines the complete implementation plan for InvoiceForge, organized into phases with trackable checkboxes.

## Phase Overview

| Phase | Name | Status | Description |
|-------|------|--------|-------------|
| 1A | Foundation & Core Architecture | ✅ Complete | Plugin skeleton, CPTs, admin UI |
| 1B | Line Items & Calculations | 🔲 Pending | Custom tables, line items, tax calculations |
| 1C | PDF Generation & Email | 🔲 Pending | mPDF integration, email service |
| 1D | Dashboard & Analytics | 🔲 Pending | Overview dashboard, basic stats |
| 2 | Payment Gateways | 🔲 Pending | Stripe, PayPal, Revolut, MyPos |
| 3 | Client Portal | 🔲 Pending | Client login, invoice viewing, payments |
| 4 | Multi-Currency | 🔲 Pending | Currency support, exchange rates |
| 5 | Payment Plans | 🔲 Pending | Partial payments, deposits, installments |
| 6 | SMS Notifications | 🔲 Pending | Twilio integration, SMS templates |
| 7 | Advanced Templates | 🔲 Pending | Template system, PDF customization |
| 8 | Tax Compliance | 🔲 Pending | EU VAT, UK, US tax handling |
| 9 | Reports & Exports | 🔲 Pending | Financial reports, CSV/Excel export |
| 10 | Recurring Invoices | 🔲 Pending | WP Cron, scheduling, automation |

---

## Phase 1A: Foundation & Core Architecture ✅

**Goal**: Establish the plugin foundation with proper architecture, security, and basic CRUD for invoices and clients.

### Bug Fixes & Improvements (March 2026)

#### Critical Bug Fixes (Round 2 - March 5, 2026)
- [x] **Client AJAX Error**: Fixed missing `isValidEmail()` method in `Validator.php` - was causing fatal PHP error
- [x] **Capability Checks**: Updated AJAX handlers to accept both custom capabilities (`edit_if_clients`) AND standard (`edit_posts`) capabilities
- [x] **Error Logging**: Added comprehensive error logging to `ClientAjaxHandler.php` and `InvoiceAjaxHandler.php` with try-catch blocks
- [x] **Inline Client Creation**: Invoice editor now supports creating invoices with NEW clients (inline form) without pre-creating client records

#### New Features (March 5, 2026)
- [x] **Invoice Creation Modes**: Two modes in invoice editor - "Select Existing Client" dropdown OR "Add New Client" inline form
- [x] **Country List Expanded**: Comprehensive list of 100+ countries sorted alphabetically (Bulgaria included prominently)
- [x] **Language Selection**: Added language setting in Settings > General with support for Bulgarian (bg_BG) and other languages
- [x] **Bulgarian Translations**: Created `languages/invoiceforge-bg_BG.po` and `.mo` files with key translations

#### Critical Bug Fixes (Round 1)
- [x] **Invoice Meta Saving**: Fixed nonce field name mismatch in `InvoicePostType.php` - meta data now saves correctly
- [x] **Client Meta Saving**: Fixed nonce field name mismatch in `ClientPostType.php` - meta data now saves correctly
- [x] **Settings Tabs**: Fixed settings overwriting issue in `SettingsPage.php` - tabs now properly merge settings instead of overwriting

#### Client Model Updates
- [x] Updated ClientPostType to support individuals (person name primary, company optional)
- [x] Added `first_name` and `last_name` fields
- [x] Made `company` field optional for individual clients
- [x] Added `getDisplayName()` static method for formatted client names

#### UI/UX Modernization
- [x] Implemented custom admin pages replacing default WordPress CPT screens
- [x] Modern SaaS-style design with CSS variables and design tokens
- [x] Toast notifications for save/delete feedback
- [x] AJAX-based form submissions with real-time validation
- [x] Responsive grid layouts and improved table designs
- [x] Status badges with color coding
- [x] Empty state illustrations and helpful prompts

### Core Architecture
- [x] Plugin skeleton with PSR-4 autoloading
- [x] Composer configuration (PHP 8.1+)
- [x] Main plugin file with bootstrap
- [x] Plugin.php - Singleton orchestrator
- [x] Activator.php - Activation hooks
- [x] Deactivator.php - Deactivation hooks
- [x] Loader.php - Hook manager
- [x] Container.php - Simple DI container

### Security Layer
- [x] Nonce.php - Nonce generation/verification
- [x] Capabilities.php - Role and capability management
- [x] Sanitizer.php - Input sanitization
- [x] Validator.php - Input validation
- [x] Encryption.php - API key encryption

### Custom Post Types
- [x] InvoicePostType.php - Invoice CPT with meta boxes
- [x] ClientPostType.php - Client CPT with meta boxes

### Admin Interface
- [x] AdminController.php - Menu registration
- [x] Assets.php - CSS/JS enqueueing
- [x] InvoicesPage.php - Invoice list/editor
- [x] ClientsPage.php - Client list/editor
- [x] SettingsPage.php - Settings with tabs

### Templates
- [x] invoice-list.php - Invoice table view
- [x] invoice-editor.php - Invoice form
- [x] client-list.php - Client table view
- [x] client-editor.php - Client form
- [x] settings.php - Settings interface

### Services & Utilities
- [x] NumberingService.php - Sequential invoice numbers
- [x] Logger.php - File-based logging
- [x] Schema.php - Database schema (for Phase 1B)

### AJAX Handlers (Added)
- [x] InvoiceAjaxHandler.php - Invoice CRUD via AJAX
- [x] ClientAjaxHandler.php - Client CRUD via AJAX

### Assets
- [x] admin.css - Modern SaaS-style admin styles (updated)
- [x] admin.js - Admin scripts with AJAX forms & notifications (updated)

### Documentation
- [x] README.md - Plugin overview
- [x] ROADMAP.md - This file
- [x] AGENTS.md - AI development guide

---

## Phase 1B: Line Items & Calculations ✅

**Goal**: Add custom database tables for line items, implement tax calculations, and enhance invoice editing.

### Database
- [x] Create `invoiceforge_invoice_items` table
- [x] Create `invoiceforge_tax_rates` table
- [x] Migration system for schema updates
- [x] Repository pattern for data access

### Line Items
- [x] LineItem model class
- [x] LineItemRepository for CRUD operations
- [x] Dynamic line item rows in invoice editor
- [x] Auto-calculation of subtotal, tax, total
- [x] Support for quantity, unit price, tax rate

### Tax Calculations
- [x] TaxService for tax calculations
- [x] Tax rate management interface
- [x] Per-item tax rate selection
- [x] Tax summary display

### Invoice Enhancements
- [x] Terms and conditions field
- [x] Payment instructions field
- [x] Discount field (percentage/fixed)
- [x] Internal notes vs customer notes

### AJAX Operations
- [x] AJAX save for invoices
- [x] Real-time calculation updates
- [x] Auto-save draft functionality

---

## Phase 1C: PDF Generation & Email ✅

**Goal**: Generate professional PDF invoices and send via email.

### PDF Generation
- [x] Integrate mPDF library via Composer
- [x] PdfService for PDF rendering
- [x] Default invoice template (HTML/CSS)
- [x] PDF preview functionality
- [x] Download invoice as PDF
- [x] Batch PDF generation

### Email Service
- [x] EmailService class
- [x] Email template system
- [x] Invoice email template (HTML)
- [x] Payment reminder template
- [x] Send invoice via email button
- [x] SMTP configuration support
- [x] Email logging

### Templates
- [x] pdf/invoice-default.php - Default PDF template
- [x] email/invoice-sent.php - Invoice email template
- [x] email/payment-reminder.php - Reminder template

---

## Phase 1D: Dashboard & Analytics ✅

**Goal**: Create an overview dashboard with key metrics and recent activity.

### Dashboard
- [x] Main dashboard page
- [x] Revenue summary widget
- [x] Outstanding invoices widget
- [x] Recent invoices list
- [x] Recent payments list
- [x] Quick action buttons

### Analytics
- [x] Monthly revenue chart
- [x] Invoice status breakdown
- [x] Top clients by revenue
- [x] Payment trends

---

## Phase 2: WooCommerce Integration ✅

**Goal**: Automatically generate invoices from WooCommerce orders and provide a dedicated UI tab to manage them separately.

### Integration Core
- [x] WooCommerce Integration class (`src/Integrations/WooCommerce/WooCommerceIntegration.php`)
- [x] Order to Invoice mapping logic (Line items, taxes, shipping)
- [x] Hook listener for configurable Order Status changes (multi-select in settings)
- [x] Client sync: finds existing client by email or creates new from billing data

### Admin UI
- [x] Settings tab for "Integrations" (Enable/disable, trigger statuses, number format, prefix, auto-email)
- [x] Tabbed Invoices list (All Invoices | Custom Invoices | WooCommerce Orders) with counts
- [x] Manual generation button on WooCommerce Order page (meta box)

### Document Delivery
- [x] Auto-send PDF email on invoice generation (if enabled in settings)
- [x] Manual "Generate Invoice" button available on order page for retroactive generation

---

## Phase 3: Payment Gateways

**Goal**: Integrate multiple payment gateways for online invoice payment.

### Gateway Architecture
- [ ] PaymentGatewayInterface
- [ ] AbstractPaymentGateway base class
- [ ] Payment model and repository
- [ ] Gateway registration system

### Stripe Integration
- [ ] StripeGateway class
- [ ] Stripe checkout flow
- [ ] Webhook handling
- [ ] Payment confirmation

### PayPal Integration
- [ ] PayPalGateway class
- [ ] PayPal checkout flow
- [ ] IPN/webhook handling
- [ ] Payment confirmation

### Revolut Integration
- [ ] RevolutGateway class
- [ ] Revolut checkout flow
- [ ] Webhook handling

### MyPos Integration
- [ ] MyPosGateway class
- [ ] MyPos checkout flow
- [ ] Callback handling

### Payment Management
- [ ] Payments list in admin
- [ ] Payment details view
- [ ] Refund functionality
- [ ] Payment receipts

---

## Phase 3: Client Portal

**Goal**: Allow clients to view invoices, make payments, and manage their account.

### Authentication
- [ ] Client login system
- [ ] Password-protected access
- [ ] Magic link login option
- [ ] Session management

### Portal Pages
- [ ] Client dashboard
- [ ] Invoice list (client view)
- [ ] Invoice detail (client view)
- [ ] Payment history
- [ ] Profile management

### Portal Features
- [ ] Pay invoice online
- [ ] Download PDF invoice
- [ ] View payment history
- [ ] Update contact info
- [ ] Communication history

### Frontend Assets
- [ ] portal.css - Portal styles
- [ ] portal.js - Portal scripts

---

## Phase 4: Multi-Currency

**Goal**: Support multiple currencies with automatic exchange rate updates.

### Currency System
- [ ] CurrencyService class
- [ ] Currency list (ISO 4217)
- [ ] Default currency setting
- [ ] Per-invoice currency selection

### Exchange Rates
- [ ] ExchangeRateService class
- [ ] exchangerate-api.io integration
- [ ] Rate caching (daily update)
- [ ] Manual rate override

### Reporting
- [ ] Convert reports to base currency
- [ ] Currency breakdown in analytics

---

## Phase 5: Payment Plans

**Goal**: Support partial payments, deposits, and installment plans.

### Payment Plans
- [ ] PaymentPlan model
- [ ] Payment schedule creation
- [ ] Deposit percentage/amount
- [ ] Installment calculation

### Partial Payments
- [ ] Accept partial payments
- [ ] Remaining balance tracking
- [ ] Payment allocation

### Reminders
- [ ] Installment due reminders
- [ ] Overdue payment alerts
- [ ] Payment plan summary

---

## Phase 6: SMS Notifications

**Goal**: Send SMS notifications for key events using Twilio.

### Twilio Integration
- [ ] SmsService class
- [ ] Twilio API integration
- [ ] Phone number validation

### SMS Templates
- [ ] Invoice sent SMS
- [ ] Payment received SMS
- [ ] Payment reminder SMS
- [ ] Custom SMS template editor

### Settings
- [ ] SMS enable/disable
- [ ] SMS template customization
- [ ] Send test SMS

---

## Phase 7: Advanced Templates

**Goal**: Customizable invoice and email templates for different regions/styles.

### Template System
- [ ] TemplateManager class
- [ ] Template registration API
- [ ] Template preview

### Invoice Templates
- [ ] EU-compliant template
- [ ] US-compliant template
- [ ] UK-compliant template
- [ ] Minimal template
- [ ] Detailed template

### Template Editor
- [ ] Visual template customizer
- [ ] Logo placement
- [ ] Color scheme
- [ ] Custom fields

---

## Phase 8: Tax Compliance

**Goal**: Handle tax requirements for different regions (EU VAT, UK, US).

### EU VAT
- [ ] VAT number validation (VIES)
- [ ] Reverse charge mechanism
- [ ] EU VAT MOSS support
- [ ] Intra-community supplies

### UK Tax
- [ ] UK VAT rates
- [ ] Making Tax Digital (MTD) ready
- [ ] VAT summary report

### US Tax
- [ ] Sales tax support
- [ ] State tax rates
- [ ] Tax exempt handling

---

## Phase 9: Reports & Exports

**Goal**: Comprehensive financial reports with export capabilities.

### Reports
- [ ] Revenue report
- [ ] Outstanding invoices report
- [ ] Client report
- [ ] Tax report
- [ ] Aging report
- [ ] Custom date ranges

### Exports
- [ ] Export to CSV
- [ ] Export to Excel (PhpSpreadsheet)
- [ ] Export to PDF
- [ ] Scheduled exports

### Charts
- [ ] Chart.js integration
- [ ] Interactive charts
- [ ] Printable reports

---

## Phase 10: Recurring Invoices

**Goal**: Automate invoice generation with recurring schedules.

### Recurring System
- [ ] RecurringInvoice model
- [ ] Schedule configuration (daily, weekly, monthly, yearly)
- [ ] WP Cron integration
- [ ] Next invoice date calculation

### Management
- [ ] Recurring invoices list
- [ ] Create recurring invoice
- [ ] Edit schedule
- [ ] Pause/resume
- [ ] View generated invoices

### Automation
- [ ] Auto-generate invoices
- [ ] Auto-send to client
- [ ] Failure handling and retry
- [ ] Notification on generation

---

## Future Considerations

### Potential Phase 11+
- [ ] Estimates/Quotes system
- [ ] Time tracking integration
- [ ] Project management integration
- [ ] Expense tracking
- [ ] Inventory management
- [ ] Multi-user/team support
- [ ] API for external integrations
- [ ] Mobile app (React Native)
- [ ] WooCommerce integration
- [ ] QuickBooks/Xero sync

---

## Version History

| Version | Phase | Release Date | Notes |
|---------|-------|--------------|-------|
| 1.0.0 | 1A | TBD | Foundation release |
| 1.1.0 | 1B | TBD | Line items & calculations |
| 1.2.0 | 1C | TBD | PDF & Email |
| 1.3.0 | 1D | TBD | Dashboard |
| 2.0.0 | 2 | TBD | Payment gateways |
| 3.0.0 | 3 | TBD | Client portal |
| 4.0.0 | 4-5 | TBD | Multi-currency & payment plans |
| 5.0.0 | 6-8 | TBD | SMS, templates, compliance |
| 6.0.0 | 9-10 | TBD | Reports & recurring |

---

## Contributing

When contributing to InvoiceForge, please:

1. Check this roadmap for planned features
2. Open an issue before starting major work
3. Follow the coding standards in AGENTS.md
4. Write tests for new functionality
5. Update documentation as needed

See [AGENTS.md](AGENTS.md) for detailed development instructions.
