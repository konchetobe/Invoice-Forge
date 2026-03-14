# Architecture

**Analysis Date:** 2026-03-14

## Pattern Overview

**Overall:** WordPress Plugin — Layered MVC with Dependency Injection Container

**Key Characteristics:**
- Singleton `Plugin` class bootstraps all components on `plugins_loaded`
- Custom DI container (`Container`) resolves services lazily; singletons by default
- `Loader` collects WordPress actions/filters and registers them in a single `run()` call
- Data stored across two systems: WordPress CPT post meta (invoice/client records) and custom `$wpdb` tables (line items, tax rates, payments schema)
- Admin UI is fully AJAX-driven — PHP renders page shells, JavaScript handles all CRUD via `wp_ajax_*` endpoints

## Layers

**Entry Point:**
- Purpose: Bootstrap validation, autoloader, and plugin boot
- Location: `invoiceforge.php`
- Contains: Version constants, PHP/WP version guards, activation/deactivation hooks
- Depends on: Composer autoloader, `Core\Plugin`
- Used by: WordPress itself on activation and `plugins_loaded`

**Core:**
- Purpose: Plugin orchestration, DI container, hook loader, lifecycle management
- Location: `src/Core/`
- Contains: `Plugin.php` (singleton orchestrator), `Container.php` (DI), `Loader.php` (hook registry), `Activator.php`, `Deactivator.php`, `UpdateChecker.php`
- Depends on: All other layers (wires them together)
- Used by: `invoiceforge.php` only

**Admin:**
- Purpose: WordPress admin menu, settings registration, asset enqueueing
- Location: `src/Admin/`
- Contains: `AdminController.php` (menu/settings), `Assets.php` (enqueue), `Pages/InvoicesPage.php`, `Pages/ClientsPage.php`, `Pages/SettingsPage.php`
- Depends on: Security layer, PostTypes layer
- Used by: `Core\Plugin` via Loader hooks (`admin_menu`, `admin_init`, `admin_enqueue_scripts`)

**AJAX:**
- Purpose: Handle all `wp_ajax_invoiceforge_*` requests for CRUD operations
- Location: `src/Ajax/`
- Contains: `InvoiceAjaxHandler.php`, `ClientAjaxHandler.php`
- Depends on: Security layer, Services layer, Repositories layer, Models layer
- Used by: Registered directly via `add_action('wp_ajax_*')` in admin context

**PostTypes:**
- Purpose: Register CPTs, meta boxes, admin list columns
- Location: `src/PostTypes/`
- Contains: `InvoicePostType.php` (CPT slug: `if_invoice`), `ClientPostType.php` (CPT slug: `if_client`)
- Depends on: Security layer, `Services\NumberingService`
- Used by: `Core\Plugin` via Loader hooks (`init`, `add_meta_boxes`, `save_post_*`)

**Services:**
- Purpose: Business logic — calculations, PDF generation, email dispatch, invoice numbering
- Location: `src/Services/`
- Contains: `TaxService.php`, `NumberingService.php`, `PdfService.php`, `EmailService.php`
- Depends on: Repositories layer, Utilities layer, mPDF (vendor)
- Used by: AJAX handlers, PostTypes, WooCommerce integration

**Repositories:**
- Purpose: Database abstraction for custom tables — raw `$wpdb` queries returning typed models
- Location: `src/Repositories/`
- Contains: `LineItemRepository.php`, `TaxRateRepository.php`
- Depends on: `Database\Schema` (table names), Models layer
- Used by: Services layer, AJAX handlers

**Models:**
- Purpose: Value objects for custom-table entities; hydration from DB rows and POST arrays
- Location: `src/Models/`
- Contains: `LineItem.php`, `TaxRate.php`
- Depends on: Nothing (pure data classes)
- Used by: Repositories, AJAX handlers, Services

**Database:**
- Purpose: Schema definitions and migrations for custom tables
- Location: `src/Database/`
- Contains: `Schema.php` — CREATE TABLE SQL, `dbDelta` migrations, table name helpers
- Depends on: `$wpdb` global
- Used by: `Core\Activator`, Repositories

**Security:**
- Purpose: CSRF protection, capability checks, input sanitisation, validation, encryption
- Location: `src/Security/`
- Contains: `Nonce.php`, `Capabilities.php`, `Sanitizer.php`, `Validator.php`, `Encryption.php`
- Depends on: WordPress core functions only
- Used by: All AJAX handlers, PostTypes, AdminController

**Utilities:**
- Purpose: Cross-cutting infrastructure concerns
- Location: `src/Utilities/`
- Contains: `Logger.php` (file-based, PSR-like levels, rotated logs in `wp-content/uploads/invoiceforge-logs/`)
- Depends on: WordPress `wp_upload_dir()`
- Used by: Every layer that needs logging

**Integrations:**
- Purpose: Optional third-party bridges; load conditionally
- Location: `src/Integrations/WooCommerce/`
- Contains: `WooCommerceIntegration.php`
- Depends on: Services layer, Repositories layer, PostTypes constants
- Used by: `Core\Plugin::boot()` always instantiated; guards with `isAvailable()` static check

**Templates:**
- Purpose: PHP view files for admin pages and PDF rendering
- Location: `templates/admin/` (admin UI), `templates/pdf/` (PDF template — `invoice-default.php`)
- Contains: `dashboard.php`, `invoice-list.php`, `invoice-editor.php`, `client-list.php`, `client-editor.php`, `settings.php`
- Used by: `Admin\Pages\*`, `Services\PdfService`

## Data Flow

**AJAX Invoice Save:**

1. Browser JS posts to `admin-ajax.php?action=invoiceforge_save_invoice`
2. `InvoiceAjaxHandler::saveInvoice()` verifies nonce via `Security\Nonce`, checks capability
3. `Security\Sanitizer` cleans all `$_POST` fields
4. Optional inline client creation via `ClientAjaxHandler::createClientFromInvoice()`
5. `wp_insert_post` / `wp_update_post` creates/updates the `if_invoice` CPT record
6. Invoice metadata saved via `update_post_meta` (`_invoice_*` keys)
7. Existing line items deleted via `LineItemRepository::deleteByInvoice()`
8. `LineItem::fromArray()` hydrates models from raw POST data
9. `TaxService::calculateInvoice()` computes subtotals, tax, totals per item
10. `LineItemRepository::save()` inserts each item into `{prefix}invoiceforge_invoice_items`
11. Computed totals stored back to post meta; `wp_send_json_success` returns invoice data array

**PDF Generation Flow:**

1. Admin clicks Download/Preview — browser navigates to `admin-ajax.php?action=invoiceforge_download_pdf&invoice_id=N&nonce=X`
2. `InvoiceAjaxHandler::downloadPdf()` verifies nonce, checks `PdfService::isAvailable()`
3. `PdfService::getInvoiceData()` assembles all invoice + client + settings data
4. `templates/pdf/invoice-default.php` is `include`d into output buffer, produces HTML
5. mPDF renders HTML to PDF, outputs with `D` (download) or `I` (inline) mode

**WooCommerce Auto-Invoice:**

1. WooCommerce fires `woocommerce_order_status_completed` (or configured trigger)
2. `WooCommerceIntegration` checks settings, verifies no duplicate invoice for order
3. Calls `generateFromOrder()` — maps order billing data to `if_client` CPT + `if_invoice` CPT
4. Line items created from WC order items via `LineItemRepository::save()`
5. Invoice number assigned via `NumberingService::generate()`

**State Management:**
- Plugin settings stored in `wp_options` key `invoiceforge_settings`
- Invoice counter in `wp_options` keys `invoiceforge_last_invoice_number` / `invoiceforge_last_invoice_year`
- Invoice/client domain data in WordPress post meta (`_invoice_*`, `_client_*` keys)
- Line items and tax rates in custom `$wpdb` tables
- No front-end state manager — each page load fetches via AJAX

## Key Abstractions

**Container (`src/Core/Container.php`):**
- Purpose: Lazy singleton DI container; services registered as closures, resolved on demand
- Pattern: Service Locator / IoC Container
- All services registered in `Plugin::registerServices()` with string keys (`'logger'`, `'nonce'`, etc.)

**Loader (`src/Core/Loader.php`):**
- Purpose: Deferred hook registration — collects all `add_action`/`add_filter` calls, executes them together in `run()`
- Pattern: Command Queue / Deferred Execution
- Prevents premature hook registration during DI wiring

**Repository Pattern (`src/Repositories/`):**
- Purpose: Isolate `$wpdb` queries; return typed model objects
- Examples: `LineItemRepository`, `TaxRateRepository`
- Pattern: Repository; uses `Schema::getXxxTable()` for prefixed table names

**Model Pattern (`src/Models/`):**
- Purpose: Plain PHP data objects with factory methods (`fromRow`, `fromArray`) and `toArray` serialisation
- Examples: `src/Models/LineItem.php`, `src/Models/TaxRate.php`
- Pattern: Anemic domain model / DTO

## Entry Points

**Plugin Bootstrap:**
- Location: `invoiceforge.php`
- Triggers: `plugins_loaded` WordPress action
- Responsibilities: Version checks, autoloader load, calls `Plugin::getInstance()->boot()`

**Activation:**
- Location: `src/Core/Activator.php`
- Triggers: `register_activation_hook`
- Responsibilities: Creates DB tables (`Schema::createTables()`), seeds default tax rates, registers custom capabilities, creates upload directories, flushes rewrite rules

**Deactivation:**
- Location: `src/Core/Deactivator.php`
- Triggers: `register_deactivation_hook`
- Responsibilities: Cleans transients, flushes rewrite rules (does NOT drop tables)

**Admin AJAX:**
- Location: `src/Ajax/InvoiceAjaxHandler.php`, `src/Ajax/ClientAjaxHandler.php`
- Triggers: `wp_ajax_invoiceforge_*` hooks
- Responsibilities: All create/read/update/delete operations for invoices, clients, tax rates; PDF download/preview; email send

## Error Handling

**Strategy:** Try/catch at AJAX handler boundary; all exceptions produce `wp_send_json_error` with HTTP status code; `Logger` records error with context.

**Patterns:**
- All `public` AJAX methods wrap logic in `try { } catch (\Exception $e)` — 500 JSON error returned
- PDF/email flows check availability guards (`PdfService::isAvailable()`) before instantiation, die with message on failure
- `WP_Error` checked after `wp_insert_post` / `wp_update_post`
- Logger silently degrades if log directory is unavailable

## Cross-Cutting Concerns

**Logging:** File-based via `src/Utilities/Logger.php`; four levels (DEBUG/INFO/WARNING/ERROR); daily log files in `wp-content/uploads/invoiceforge-logs/`; directory protected by `.htaccess`

**Validation:** Input validated in AJAX handlers using `Security\Validator` and `Security\Sanitizer`; `Security\Nonce` verifies CSRF on every mutating request

**Authentication:** WordPress capability system with custom capabilities (`edit_if_invoices`, `manage_invoiceforge_settings`, etc.) registered on activation; checked via `current_user_can()` inside each handler

---

*Architecture analysis: 2026-03-14*
