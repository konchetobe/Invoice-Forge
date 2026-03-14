# Coding Conventions

**Analysis Date:** 2026-03-14

## Naming Patterns

**Files:**
- PascalCase for PHP class files matching the class name: `InvoiceAjaxHandler.php`, `TaxService.php`, `LineItemRepository.php`
- Kebab-case for CSS/JS assets: `admin.css`, `admin.js`
- Kebab-case for templates: `invoice-editor.php`, `client-list.php`
- Snake_case for language files: `invoiceforge-bg_BG.po`

**Classes:**
- PascalCase, always namespaced under `InvoiceForge\{Subpackage}`: `class TaxService`, `class LineItemRepository`
- Suffix communicates role: `*Controller`, `*Service`, `*Repository`, `*Handler`, `*PostType`, `*Page`
- Final for singletons: `final class Plugin`

**Methods:**
- camelCase for all class methods: `calculateItem()`, `findByInvoice()`, `saveMetaData()`
- Boolean methods prefixed with `can*`, `is*`, `has*`: `canEditInvoices()`, `isAvailable()`, `tablesExist()`
- Private helper methods follow same camelCase: `getInvoiceData()`, `getRate()`, `formatEntry()`

**Properties:**
- camelCase for class properties: `$lineItemRepo`, `$numberingService`, `$rateCache`
- All properties have explicit typed declarations (PHP 8.1+ typed properties)
- Private by default; public only for model data objects (`LineItem` public properties)

**Constants:**
- SCREAMING_SNAKE_CASE for class constants: `POST_TYPE`, `META_PREFIX`, `NONCE_ACTION`, `LEVEL_DEBUG`
- Plugin-wide constants use `INVOICEFORGE_` prefix: `INVOICEFORGE_VERSION`, `INVOICEFORGE_PLUGIN_DIR`

**Database / Meta Keys:**
- Meta keys prefixed with `_invoice_` or `_client_`: `_invoice_number`, `_invoice_client_id`
- Table names prefixed with `invoiceforge_`: `invoiceforge_invoice_items`, `invoiceforge_tax_rates`
- PHP post-type slugs prefixed with `if_`: `if_invoice`, `if_client`

**AJAX Actions:**
- Prefixed with `invoiceforge_`: `invoiceforge_save_invoice`, `invoiceforge_download_pdf`

**JavaScript:**
- camelCase for object methods: `handleInvoiceSave`, `bindEvents`, `showToast`
- Module object wraps all code in an IIFE: `(function($) { 'use strict'; ... })(jQuery);`
- Object literal pattern for the main module: `const InvoiceForgeAdmin = { ... }`

## Code Style

**Formatting:**
- PHP: 4-space indentation (inferred from files; `phpcbf` enforces WordPress standard)
- JavaScript: 4-space indentation with single quotes; jQuery-centric
- Allman-adjacent brace style for PHP class/method definitions; opening brace on same line for control structures
- Trailing commas in multi-line arrays

**Linting:**
- PHP: `phpcs --standard=WordPress src/` (WPCS 3.0)
- Static analysis: `phpstan analyse src/ --level=6`
- Both configured in `composer.json` scripts
- `// phpcs:ignore WordPress.DB.DirectDatabaseQuery` is used on every raw `$wpdb` call in repositories

**Strict Types:**
- `declare(strict_types=1);` at the top of every PHP file in `src/`

**Direct Access Guard:**
- Every PHP file opens with:
  ```php
  if (!defined('ABSPATH')) {
      exit;
  }
  ```

## Import Organization

**PHP Namespaces:**
- PSR-4 autoloading: namespace `InvoiceForge\` maps to `src/`
- `use` statements grouped after the namespace declaration, one per line, alphabetical within a group
- Example from `src/Core/Plugin.php`:
  ```php
  use InvoiceForge\Admin\AdminController;
  use InvoiceForge\Admin\Assets;
  use InvoiceForge\Ajax\InvoiceAjaxHandler;
  ```

**Path Aliases:**
- None. PSR-4 autoload is the only import mechanism.

## Error Handling

**PHP Strategy:**
- All public AJAX methods are wrapped in `try/catch (\Exception $e)`
- On exception: log with `$this->log('error', ..., ['exception' => $e->getMessage()])` then return `wp_send_json_error(['message' => __('An unexpected error occurred.', 'invoiceforge')], 500)`
- Validation errors collected in an `$errors[]` array before any DB writes; returned as 400 with joined message string
- `wp_send_json_error` used for JSON endpoints with appropriate HTTP status codes (400, 403, 404, 500)
- `wp_die()` used only for non-AJAX endpoints (PDF download, preview)
- `WP_Error` objects from `wp_insert_post`/`wp_update_post` checked with `is_wp_error()` before proceeding
- Early-return pattern used consistently: check fails → send error → `return;` before proceeding with the happy path

**Logger usage:**
- Every AJAX handler creates a `Logger` instance in the constructor; stored as `?Logger` (nullable)
- Private `log()` wrapper checks `if ($this->logger)` before calling
- Log levels: `debug` (entry + args), `info` (success), `warning` (permission failures), `error` (exceptions + DB failures)
- Logger writes to `wp-content/uploads/invoiceforge-logs/invoiceforge-YYYY-MM-DD.log`

## Logging

**Framework:** `InvoiceForge\Utilities\Logger` (custom file-based logger at `src/Utilities/Logger.php`)

**Levels:** `DEBUG`, `INFO`, `WARNING`, `ERROR` (in that priority order)

**Patterns:**
- Method-level log call on entry (debug): `$this->log('debug', 'saveInvoice called', ['POST' => $_POST]);`
- Success log at info level with key IDs: `$this->log('info', 'Invoice saved successfully', ['post_id' => $post_id]);`
- Permission/nonce failures at warning: `$this->log('warning', 'Nonce verification failed');`
- Exception catch at error: `$this->log('error', 'Exception in saveInvoice', ['exception' => $e->getMessage()]);`
- Context always uses string keys (associative array)

## Comments

**When to Comment:**
- Every class and every public/private method has a full PHPDoc block
- Inline comments for non-obvious logic (e.g., `// subtotal = qty * unit_price`, `// Only run occasionally (1% chance per log write)`)
- `translators:` hints in `sprintf` calls using `/* translators: ... */` format
- `phpcs:ignore` comments are one-line suppressions directly above the offending line

**PHPDoc:**
- All docs include `@since`, `@param`, and `@return` tags
- Array types fully annotated: `array<string, mixed>`, `array<int, TaxRate>`, `array{subtotal: float, tax: float, total: float, items: LineItem[]}`
- `@throws` documented where exceptions are intentionally thrown
- Subpackage indicated: `@subpackage Services`

## Function Design

**Size:** Methods are focused; longer methods (e.g., `saveInvoice`) perform exactly one HTTP action and follow a linear flow: verify → permission check → sanitize → validate → write → respond

**Parameters:** Constructor injection for all dependencies (no static calls except for `Sanitizer` methods in Model factories)

**Return Values:**
- Nullable types used where absence is meaningful: `?LineItem`, `?array`, `?Logger`
- Repository `find()` returns `Model|null`
- Repository `save()` returns `int` (ID)
- Boolean methods return `bool`
- Methods returning multiple values use typed array shapes

## Module Design

**Exports:**
- Each class exported via its namespace; no barrel files

**Service Registration:**
- All services wired in `Plugin::registerServices()` using the `Container`
- Services resolved via snake_case string keys: `'line_item_repo'`, `'tax_service'`, `'numbering'`
- Container defaults to singleton (shared) behavior; `setInstance()` available for testing

**Dependency Injection:**
- Constructor injection throughout; no static service locator in class internals (except `Plugin::getInstance()` singleton entry point)
- `ClientAjaxHandler` instantiated inside `InvoiceAjaxHandler::saveInvoice()` for inline client creation (not injected — a noted inconsistency)

---

*Convention analysis: 2026-03-14*
