# Codebase Structure

**Analysis Date:** 2026-03-14

## Directory Layout

```
invoiceforge/                        # WordPress plugin root
├── invoiceforge.php                 # Plugin entry point (bootstrap, constants, hooks)
├── uninstall.php                    # Cleanup on plugin deletion
├── composer.json                    # PHP dependencies and autoload config
├── composer.lock                    # Locked dependency versions
├── composer.phar                    # Bundled Composer binary
├── src/                             # All PHP source code (PSR-4: InvoiceForge\)
│   ├── Admin/                       # WordPress admin UI controllers and assets
│   │   ├── AdminController.php      # Menu registration, settings API
│   │   ├── Assets.php               # Script/style enqueueing
│   │   └── Pages/                   # Per-page controller classes
│   │       ├── InvoicesPage.php
│   │       ├── ClientsPage.php
│   │       └── SettingsPage.php
│   ├── Ajax/                        # wp_ajax_* request handlers
│   │   ├── InvoiceAjaxHandler.php
│   │   └── ClientAjaxHandler.php
│   ├── Core/                        # Plugin lifecycle and infrastructure
│   │   ├── Plugin.php               # Singleton orchestrator, DI wiring
│   │   ├── Container.php            # Dependency injection container
│   │   ├── Loader.php               # Deferred WordPress hook registry
│   │   ├── Activator.php            # Activation tasks (DB, caps, dirs)
│   │   ├── Deactivator.php          # Deactivation cleanup
│   │   └── UpdateChecker.php        # GitHub-based update checker
│   ├── Database/                    # Custom table schema and migrations
│   │   └── Schema.php
│   ├── Integrations/                # Optional third-party integrations
│   │   └── WooCommerce/
│   │       └── WooCommerceIntegration.php
│   ├── Models/                      # Plain data objects (DB row / POST hydration)
│   │   ├── LineItem.php
│   │   └── TaxRate.php
│   ├── PostTypes/                   # CPT registration and meta box handlers
│   │   ├── InvoicePostType.php      # CPT: if_invoice
│   │   └── ClientPostType.php       # CPT: if_client
│   ├── Repositories/                # $wpdb data access for custom tables
│   │   ├── LineItemRepository.php
│   │   └── TaxRateRepository.php
│   ├── Security/                    # CSRF, capabilities, sanitisation, validation
│   │   ├── Nonce.php
│   │   ├── Capabilities.php
│   │   ├── Sanitizer.php
│   │   ├── Validator.php
│   │   └── Encryption.php
│   ├── Services/                    # Business logic services
│   │   ├── NumberingService.php     # Sequential invoice number generation
│   │   ├── TaxService.php           # Tax calculation per line item
│   │   ├── PdfService.php           # mPDF-backed PDF generation
│   │   └── EmailService.php         # wp_mail invoice/reminder emails
│   └── Utilities/                   # Infrastructure utilities
│       └── Logger.php               # File-based logger (4 levels)
├── templates/                       # PHP view templates
│   └── admin/                       # Admin page shells (rendered by Pages classes)
│       ├── dashboard.php
│       ├── invoice-list.php
│       ├── invoice-editor.php
│       ├── client-list.php
│       ├── client-editor.php
│       └── settings.php
│   # Note: templates/pdf/invoice-default.php expected by PdfService
│   #        (not yet committed; PdfService falls back to inline HTML)
├── assets/
│   └── admin/
│       ├── css/
│       │   └── admin.css            # Admin UI styles
│       └── js/
│           └── admin.js             # Admin UI JS (AJAX, form handling)
├── languages/                       # .pot / .po / .mo translation files
├── vendor/                          # Composer-managed dependencies (not committed to dev)
└── .planning/                       # GSD planning artifacts (not shipped)
    ├── codebase/                    # Codebase analysis documents
    ├── phases/                      # Phase implementation plans
    └── research/                    # Research notes
```

## Directory Purposes

**`src/Core/`:**
- Purpose: Plugin orchestration infrastructure — bootstrapping, DI, hook management, lifecycle
- Key files: `Plugin.php` (singleton, service registration, hook wiring), `Container.php` (DI), `Loader.php` (hook collector)
- All services are registered as closures in `Plugin::registerServices()` and resolved lazily

**`src/Admin/`:**
- Purpose: Everything rendered inside `wp-admin` — menus, settings pages, asset loading
- Key files: `AdminController.php` (registers admin menus and WordPress Settings API), `Assets.php` (enqueues `admin.css` and `admin.js`)
- Pages are plain PHP classes that delegate rendering to `templates/admin/` files

**`src/Ajax/`:**
- Purpose: All `wp_ajax_invoiceforge_*` action handlers; entry points for browser JS
- Each handler method: verifies nonce, checks capability, sanitises input, performs operation, returns JSON
- `InvoiceAjaxHandler` handles invoices, line items, tax rates, PDF, email, WooCommerce order import
- `ClientAjaxHandler` handles client CRUD; also exposes `createClientFromInvoice()` for inline client creation

**`src/PostTypes/`:**
- Purpose: Register CPTs and their meta boxes; also define constants (post type slug, status values) used across the codebase
- `InvoicePostType::POST_TYPE = 'if_invoice'`, `InvoicePostType::STATUSES` — used in AJAX handlers and queries
- `ClientPostType::POST_TYPE = 'if_client'`

**`src/Services/`:**
- Purpose: Domain logic with no direct HTTP coupling
- `NumberingService`: generates sequential numbers like `INV-2026-001`, reads/writes `wp_options`
- `TaxService`: calculates tax per line item from `TaxRateRepository`; returns `['items', 'subtotal', 'tax', 'total']`
- `PdfService`: uses mPDF to render `templates/pdf/invoice-default.php` to PDF bytes
- `EmailService`: composes HTML email, optionally attaches PDF via `PdfService`, sends via `wp_mail`

**`src/Repositories/`:**
- Purpose: Data access for custom `$wpdb` tables; returns typed model objects
- Table names always retrieved from `Database\Schema::getXxxTable()` to respect DB prefix
- `LineItemRepository`: full CRUD on `{prefix}invoiceforge_invoice_items`
- `TaxRateRepository`: full CRUD on `{prefix}invoiceforge_tax_rates` plus `seedDefaults()`

**`src/Models/`:**
- Purpose: Plain PHP objects representing database rows; no active record, no WP coupling
- `LineItem`: factory methods `fromRow(object)` and `fromArray(array)`, plus `toArray()` for JSON responses
- `TaxRate`: same pattern

**`src/Database/`:**
- Purpose: Single source of truth for table DDL and versioning
- `Schema::createTables()` called on activation; `Schema::migrate()` for future version upgrades
- Three tables defined: `invoiceforge_invoice_items`, `invoiceforge_payments`, `invoiceforge_tax_rates`

**`src/Security/`:**
- Purpose: All security primitives — injected into AJAX handlers and controllers
- `Nonce`: wraps `wp_create_nonce` / `check_ajax_referer` with plugin prefix
- `Sanitizer`: typed sanitisation methods (`text()`, `email()`, `money()`, `date()`, `absint()`, `option()`, `textarea()`)
- `Validator`: validation rules returning error arrays
- `Capabilities`: wraps `current_user_can()` for custom plugin capabilities
- `Encryption`: wraps `openssl_encrypt` for sensitive settings (e.g. SMTP password)

**`src/Utilities/`:**
- Purpose: Cross-cutting infrastructure not specific to any domain
- `Logger`: writes to `wp-content/uploads/invoiceforge-logs/invoiceforge-YYYY-MM-DD.log`; levels DEBUG/INFO/WARNING/ERROR; auto-rotates; directory protected by `.htaccess`

**`src/Integrations/`:**
- Purpose: Optional integrations behind availability guards
- `WooCommerceIntegration::isAvailable()` checks for WooCommerce before any WC calls
- Responds to `woocommerce_order_status_*` hooks; can also be triggered manually via AJAX

**`templates/admin/`:**
- Purpose: PHP view files for admin pages — included by `Pages\*` classes or `AdminController`
- These are shells; all data is loaded asynchronously via AJAX

**`assets/admin/`:**
- Purpose: Compiled/static frontend assets for admin UI only
- `admin.js`: handles all AJAX calls, form submissions, UI state for invoice/client management
- `admin.css`: admin UI styles

## Key File Locations

**Entry Points:**
- `invoiceforge.php`: Plugin bootstrap, constants, activation/deactivation hooks
- `src/Core/Plugin.php`: Full DI wiring and hook registration

**Configuration:**
- `composer.json`: PHP runtime dependencies, PSR-4 autoload map, dev tooling
- `src/Database/Schema.php`: Database table names and DDL — single source of truth

**Core Logic:**
- `src/Services/TaxService.php`: Tax calculation engine
- `src/Services/NumberingService.php`: Invoice number generation
- `src/Services/PdfService.php`: PDF export
- `src/Services/EmailService.php`: Email sending
- `src/Ajax/InvoiceAjaxHandler.php`: Primary invoice operation handler (largest file)

**Data Layer:**
- `src/Database/Schema.php`: Table DDL and name helpers
- `src/Repositories/LineItemRepository.php`: Line item CRUD
- `src/Repositories/TaxRateRepository.php`: Tax rate CRUD + defaults seeding

**Testing:**
- `tests/` (autoload-dev registered in `composer.json` at `InvoiceForge\Tests\`)
- PHPUnit configured via `composer.json` scripts (`composer test`)

## Naming Conventions

**PHP Files:**
- PascalCase class names matching filenames: `InvoiceAjaxHandler.php`, `TaxService.php`
- One class per file; namespace mirrors directory structure under `src/`

**PHP Classes:**
- PascalCase: `LineItemRepository`, `WooCommerceIntegration`
- Suffixes denote layer: `*Repository`, `*Service`, `*Handler`, `*PostType`, `*Controller`

**PHP Methods:**
- camelCase: `saveInvoice()`, `findByInvoice()`, `calculateInvoice()`
- Private helpers prefixed descriptively: `getInvoiceData()`, `canEditInvoices()`

**WordPress Hooks:**
- All plugin hook names prefixed `invoiceforge_`: `invoiceforge_save_invoice`, `invoiceforge_booted`
- AJAX actions follow pattern `invoiceforge_{verb}_{noun}`: `invoiceforge_save_invoice`, `invoiceforge_download_pdf`

**Post Meta Keys:**
- All prefixed `_invoice_` or `_client_`: `_invoice_number`, `_invoice_status`, `_client_email`

**WordPress Options:**
- Plugin options prefixed `invoiceforge_`: `invoiceforge_settings`, `invoiceforge_last_invoice_number`

**Database Tables:**
- Prefixed with WordPress DB prefix then `invoiceforge_`: `{wp_}invoiceforge_invoice_items`

**Post Type Slugs:**
- Prefixed `if_`: `if_invoice`, `if_client`

**Custom Capabilities:**
- Follow WordPress pattern with `if_` object prefix: `edit_if_invoices`, `delete_if_invoice`

**Template Files:**
- kebab-case: `invoice-editor.php`, `client-list.php`

**JavaScript/CSS:**
- Single files per context: `admin.js`, `admin.css`

## Where to Add New Code

**New AJAX endpoint:**
- Add `add_action('wp_ajax_invoiceforge_{action}', [$this, '{method}'])` in the relevant handler's `register()` method
- Handler: `src/Ajax/InvoiceAjaxHandler.php` (invoice/tax/PDF operations) or `src/Ajax/ClientAjaxHandler.php` (client operations)
- New handler class: `src/Ajax/{Domain}AjaxHandler.php`, register in `Plugin::registerServices()` and `Plugin::registerHooks()`

**New service (business logic):**
- Implementation: `src/Services/{Name}Service.php`, namespace `InvoiceForge\Services`
- Register in `Plugin::registerServices()`: `$this->container->register('service_key', fn() => new {Name}Service(...))`
- Inject via container in handlers that need it

**New repository (custom table):**
- Implementation: `src/Repositories/{Entity}Repository.php`, namespace `InvoiceForge\Repositories`
- Add table constant and getter to `src/Database/Schema.php`
- Add CREATE TABLE SQL to `Schema::createTables()` and `Schema::getTableStatus()`
- Register in `Plugin::registerServices()`

**New model:**
- Implementation: `src/Models/{Entity}.php`, namespace `InvoiceForge\Models`
- Implement `fromRow(object)`, `fromArray(array)`, `toArray()` pattern

**New admin page:**
- Page controller: `src/Admin/Pages/{Name}Page.php`
- Template: `templates/admin/{name}.php`
- Register submenu in `AdminController::registerMenus()`

**New integration:**
- Implementation: `src/Integrations/{Provider}/{Provider}Integration.php`
- Add `isAvailable()` static method guarding against missing dependency
- Instantiate in `Plugin::boot()` after availability check

**New security utility:**
- Implementation: `src/Security/{Name}.php`, namespace `InvoiceForge\Security`
- Register in `Plugin::registerServices()`

**Utilities:**
- Shared helpers: `src/Utilities/` — only for truly cross-cutting infrastructure (logging, caching, etc.)

## Special Directories

**`vendor/`:**
- Purpose: Composer-managed PHP dependencies (mPDF, plugin update checker)
- Generated: Yes (via `composer install`)
- Committed: Yes in release ZIPs; may be excluded from development repo depending on `.gitignore`

**`languages/`:**
- Purpose: Translation files (`.pot`, `.po`, `.mo`) for i18n; text domain `invoiceforge`
- Generated: Partially (`.pot` from source scan); `.mo` compiled from `.po`
- Committed: Yes

**`.planning/`:**
- Purpose: GSD planning artifacts — codebase analysis, phase plans, research
- Generated: No (manually maintained)
- Committed: Yes (planning context persists across sessions)

**`wp-content/uploads/invoiceforge-logs/`** (runtime, outside plugin dir):
- Purpose: Log files written by `Logger`; protected by `.htaccess`
- Generated: Yes (on activation)
- Committed: No

**`wp-content/uploads/invoiceforge-pdfs/`** (runtime, outside plugin dir):
- Purpose: Reserved for PDF temp storage; created on activation
- Generated: Yes (on activation)
- Committed: No

---

*Structure analysis: 2026-03-14*
