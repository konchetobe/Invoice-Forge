# Codebase Concerns

**Analysis Date:** 2026-03-14

## Tech Debt

**Missing `saveTaxRate` and `deleteTaxRate` AJAX handlers:**
- Issue: `InvoiceAjaxHandler::register()` binds `wp_ajax_invoiceforge_save_tax_rate` and `wp_ajax_invoiceforge_delete_tax_rate` to `[$this, 'saveTaxRate']` and `[$this, 'deleteTaxRate']` respectively, but neither method is implemented on the class. Calling these actions will trigger a PHP fatal error or a WordPress "call to undefined method" notice.
- Files: `src/Ajax/InvoiceAjaxHandler.php` (lines 145-146)
- Impact: Any UI that attempts to create or delete tax rates via AJAX will hard-fail silently (WP will return a `-1` response). The Settings page "Tax Rates" management feature is completely non-functional.
- Fix approach: Implement `public function saveTaxRate(): void` and `public function deleteTaxRate(): void` methods on `InvoiceAjaxHandler`, mirroring the `getTaxRates()` pattern and delegating to `TaxRateRepository`.

**WooCommerce integration passes plain arrays to `LineItemRepository::save()` which expects a `LineItem` object:**
- Issue: `WooCommerceIntegration::generateFromOrder()` calls `$this->lineItemRepo->save([...])` (lines 223, 243) passing associative arrays. `LineItemRepository::save()` is typed `save(LineItem $item): int` and will throw a PHP TypeError at runtime when WooCommerce order-to-invoice generation is triggered.
- Files: `src/Integrations/WooCommerce/WooCommerceIntegration.php` (lines 223-255), `src/Repositories/LineItemRepository.php` (line 84)
- Impact: Automatic invoice generation from WooCommerce orders will fail with an uncaught TypeError. The error is caught by the outer try/catch and silently logged, resulting in an invoice post with no line items and inaccurate totals.
- Fix approach: Replace the array literals with `LineItem::fromArray()` calls or construct `LineItem` objects directly before calling `$this->lineItemRepo->save()`.

**WooCommerce line item field uses `sort_order` key instead of `item_order`:**
- Issue: In `WooCommerceIntegration::generateFromOrder()` the array passed to `save()` uses key `'sort_order'` (lines 231, 251), but `LineItem::fromArray()` and the database schema use `item_order`. This naming mismatch would silently discard the ordering even if the array-vs-object issue above is resolved.
- Files: `src/Integrations/WooCommerce/WooCommerceIntegration.php` (lines 231, 251)
- Impact: Line items generated from WooCommerce orders will have no explicit order, relying on insertion order.
- Fix approach: Change `'sort_order'` to `'item_order'` in both places.

**Inconsistent tax meta key between modules:**
- Issue: `InvoiceAjaxHandler` stores tax as `_invoice_tax` (line 389) and reads it back as `_invoice_tax` (line 617). `WooCommerceIntegration` stores it as `_invoice_tax_total` (line 261). `PdfService::getInvoiceData()` reads `_invoice_tax_total` (line 163). Invoices created through the standard AJAX editor will show `tax_total = 0.0` in PDFs; invoices created from WooCommerce will show `tax = 0.0` in the invoice list/editor API response.
- Files: `src/Ajax/InvoiceAjaxHandler.php` (line 389), `src/Integrations/WooCommerce/WooCommerceIntegration.php` (line 261), `src/Services/PdfService.php` (line 163)
- Impact: Tax amounts silently show as zero in either the PDF or the admin UI depending on the invoice's origin.
- Fix approach: Standardise on a single meta key (`_invoice_tax`) across all writers and readers, and run a migration to re-key any existing `_invoice_tax_total` entries.

**`Activator::createOptions()` sets a `date_format` default that `SettingsPage` does not define:**
- Issue: `Activator::createOptions()` includes `'date_format' => get_option('date_format', 'Y-m-d')` in the default options array (line 101), but `SettingsPage::getDefaults()` does not include a `date_format` key. This means the option is set on first activation but is never exposed through the settings UI or read back through `getDefaults()`. It will be absent from arrays merged against `getDefaults()` on subsequent reads.
- Files: `src/Core/Activator.php` (line 101), `src/Admin/Pages/SettingsPage.php` (line 982-1009)
- Impact: Any code that attempts to read `$settings['date_format']` after merging with defaults will get a PHP notice/undefined key.
- Fix approach: Either add `date_format` to `SettingsPage::getDefaults()` and `TAB_FIELDS`, or remove it from `Activator`.

**WooCommerce integration settings fields are not sanitised through `sanitizeField()`:**
- Issue: `SettingsPage::TAB_FIELDS['integrations']` lists `woo_enabled`, `woo_trigger_statuses`, `woo_invoice_number_format`, `woo_invoice_prefix`, and `woo_auto_email`, but none of these have `case` entries in `sanitizeField()`. They fall through to `default: return $this->sanitizer->text((string) $value)` — which is correct for scalar strings but casts `woo_trigger_statuses` (an array) to the string `"Array"` and stores it. `woo_enabled` and `woo_auto_email` (booleans) are also stringified.
- Files: `src/Admin/Pages/SettingsPage.php` (lines 83-88, 896-954)
- Impact: Saving the Integrations settings tab corrupts `woo_trigger_statuses` to the string `"Array"`, breaking trigger-status detection in `WooCommerceIntegration::register()`. Boolean flags become the string `"1"` or `""`.
- Fix approach: Add cases for all five WooCommerce fields in `sanitizeField()`: array sanitisation for `woo_trigger_statuses`, `sanitizer->bool()` for `woo_enabled` and `woo_auto_email`, `sanitizer->option()` for `woo_invoice_number_format`, and `sanitizer->text()` for `woo_invoice_prefix`.

**Settings `language` field is not sanitised through `sanitizeField()`:**
- Issue: `TAB_FIELDS['general']` includes `'language'` (line 64), but there is no `case 'language'` in `sanitizeField()`. It falls through to `default: return $this->sanitizer->text(...)` rather than being validated against the known language codes returned by `getAvailableLanguages()`.
- Files: `src/Admin/Pages/SettingsPage.php` (lines 64, 896-954)
- Impact: An arbitrary locale string can be stored and later passed to `switch_to_locale()` in `Plugin::loadTextDomain()`.
- Fix approach: Add `case 'language': return $this->sanitizer->option($value, array_keys($this->getAvailableLanguages()), '');`

**No tests directory exists despite PHPUnit being declared as a dev dependency:**
- Issue: `composer.json` declares `"phpunit/phpunit": "^10.0"` as a dev dependency and defines an autoload-dev namespace `InvoiceForge\\Tests\\` pointing to `tests/`, but the `tests/` directory does not exist and no test files are present anywhere in the repository.
- Files: `composer.json` (lines 17-30)
- Impact: `composer test` will fail immediately. There is zero automated test coverage for any business logic, security checks, or database operations.
- Fix approach: Create a `tests/` directory and write at minimum unit tests for `NumberingService`, `TaxService`, `Sanitizer`, `Validator`, and `Encryption`.

**Media uploader singleton leaks between image fields:**
- Issue: In `assets/admin/js/admin.js`, the `mediaUploader` variable is declared once in the `initMediaUploader` closure (line 78) and reused across all image upload buttons. Once opened for the first field, subsequent fields that trigger `mediaUploader.open()` will always use the first field's `$preview` and `$input` targets because the `on('select', ...)` handler and the target references are captured in the first click's closure.
- Files: `assets/admin/js/admin.js` (lines 78-107)
- Impact: If the page ever has more than one image upload field, only the first field will correctly receive the selected image. Currently only `company_logo` exists, so this is latent.
- Fix approach: Either destroy and recreate the media frame per button click, or pass the target references into the `select` callback via closure scope on each invocation.

**Discount is applied to post-tax total instead of pre-tax subtotal for percentage discounts:**
- Issue: In `InvoiceAjaxHandler::saveInvoice()` (lines 380-385), percentage discounts compute `$discountAmount = round($invoiceSubtotal * ($discount_value / 100), 2)` (based on subtotal) but subtract from `$invoiceTotal` (which already includes tax). This means the effective discount rate is applied pre-tax while the label may imply a post-tax rate, or vice versa — the intent is ambiguous. Mixing subtotal-based calculation with total-based deduction is financially inconsistent.
- Files: `src/Ajax/InvoiceAjaxHandler.php` (lines 380-385)
- Impact: The final invoice total may differ from what a client expects if they interpret the discount as applying to the total (including tax). This is a financial accuracy concern.
- Fix approach: Decide and document the intended discount semantics. If pre-tax: apply discount to subtotal, then recalculate tax. If post-tax: compute the discount amount from the total. Make the approach consistent and add a comment explaining the behaviour.

## Known Bugs

**`saveTaxRate`/`deleteTaxRate` methods missing — PHP fatal on invocation:**
- Symptoms: Calling `wp_ajax_invoiceforge_save_tax_rate` or `wp_ajax_invoiceforge_delete_tax_rate` results in a WordPress `wp_send_json_error` or uncaught PHP error because the methods do not exist.
- Files: `src/Ajax/InvoiceAjaxHandler.php` (lines 145-146)
- Trigger: Any UI action that attempts to manage tax rates through AJAX.
- Workaround: None. Tax rate management via AJAX is entirely non-functional.

**WooCommerce auto-invoice generation produces invoices with no line items:**
- Symptoms: When a WooCommerce order reaches a trigger status, an invoice post is created but contains no line items in the database. The invoice total is stored from `$order->get_total()` but the custom table entries are missing.
- Files: `src/Integrations/WooCommerce/WooCommerceIntegration.php` (lines 223, 243)
- Trigger: Any WooCommerce order status change matching `woo_trigger_statuses` when the integration is enabled.
- Workaround: Disable WooCommerce auto-generation (`woo_enabled = false`); generate invoices manually after creating them through the standard invoice editor.

**WooCommerce integration settings are corrupted when saved:**
- Symptoms: After saving the Integrations settings tab, `woo_trigger_statuses` becomes the string `"Array"` in the database, and the WooCommerce hooks are never registered because `str_replace('wc-', '', sanitize_key("Array"))` does not match any order status.
- Files: `src/Admin/Pages/SettingsPage.php` (line 952)
- Trigger: Any save of the Integrations settings tab.
- Workaround: Directly set `woo_trigger_statuses` in the database using `update_option()` via a custom snippet or WP-CLI.

**PDF template file is missing — fallback HTML used for all PDFs:**
- Symptoms: `PdfService::generate()` checks for `templates/pdf/invoice-default.php` (line 95-97). This file does not exist in the repository. Every PDF generation falls back to `getFallbackHtml()`, which produces a minimal unstyled HTML table with no logo, company address, or payment instructions.
- Files: `src/Services/PdfService.php` (lines 95-97), `templates/pdf/invoice-default.php` (missing)
- Trigger: Any PDF download or preview action, and any email attachment.
- Workaround: None without creating the template file.

## Security Considerations

**Inline new-client data from `$_POST` is passed unsanitised to `createClientFromInvoice()`:**
- Risk: Lines 245-254 of `InvoiceAjaxHandler::saveInvoice()` construct `$client_data` directly from raw `$_POST` values with no sanitisation applied at this layer. The data is sanitised inside `ClientAjaxHandler::createClientFromInvoice()` (lines 299-308), but the raw data is logged at line 297 (`$this->log('debug', 'createClientFromInvoice called', $data)`) before sanitisation. Log files stored in `wp-content/uploads/invoiceforge-logs/` could contain raw unsanitised user input.
- Files: `src/Ajax/InvoiceAjaxHandler.php` (lines 244-254), `src/Ajax/ClientAjaxHandler.php` (lines 297-308)
- Current mitigation: Nonce and capability checks occur before this code path. Sanitisation occurs before writing to the database.
- Recommendations: Sanitise `$client_data` values at the point of collection in `InvoiceAjaxHandler` (before logging), or ensure the logger redacts/truncates raw input.

**Encryption key falls back to a guessable value when WordPress salts are absent:**
- Risk: `Encryption::getKey()` (line 68) falls back to `'invoiceforge-default-key-' . ABSPATH` if neither `AUTH_KEY` nor `SECURE_AUTH_KEY` is defined. On development servers or misconfigured installs, this creates a predictable key derivation. Any SMTP password encrypted under this fallback key could be decrypted by any attacker who knows ABSPATH.
- Files: `src/Security/Encryption.php` (lines 59-73)
- Current mitigation: WordPress best-practice installs always define salts. The risk is limited to misconfigured or test environments.
- Recommendations: Log a warning (or refuse to encrypt) rather than silently using the predictable fallback.

**`canEditInvoices()` and `canDeleteInvoices()` fall back to generic `edit_posts`/`delete_posts`:**
- Risk: Both capability checks in `InvoiceAjaxHandler` (lines 184, 196) accept `current_user_can('edit_posts')` or `current_user_can('delete_posts')` as sufficient. Any user with the `editor` role (who can edit their own posts) can therefore create, edit, or delete any invoice. The custom `edit_if_invoices` / `delete_if_invoices` capabilities registered during activation should be the primary gate.
- Files: `src/Ajax/InvoiceAjaxHandler.php` (lines 183-196), `src/Security/Capabilities.php` (lines 107-109)
- Current mitigation: The plugin is admin-only and the menu is only rendered for users with `edit_posts`. The attack surface is limited to authenticated backend users.
- Recommendations: Remove the `|| current_user_can('edit_posts')` fallback from `canEditInvoices()` and `canDeleteInvoices()` once the plugin is confirmed stable; rely solely on the custom capabilities.

**Log directory `.htaccess` uses legacy `Order deny,allow` syntax:**
- Risk: `Activator::createDirectories()` writes `.htaccess` files using Apache 2.2 syntax (`Order deny,allow / Deny from all`). On Apache 2.4+ servers with only `mod_authz_host` loaded (not `mod_access_compat`), these directives are silently ignored, leaving log files world-readable via HTTP.
- Files: `src/Core/Activator.php` (lines 142-143, 163-164)
- Current mitigation: The `index.php` file provides a secondary layer of protection. On Nginx servers neither mechanism prevents direct access.
- Recommendations: Replace with `Require all denied` for Apache 2.4 compatibility, or store logs outside the web root.

## Performance Bottlenecks

**`getInvoiceData()` issues one `get_post_meta()` call per meta field:**
- Problem: `InvoiceAjaxHandler::getInvoiceData()` (lines 607-628) calls `get_post_meta()` twelve times per invoice. `getInvoices()` calls `getInvoiceData()` for each post in a paginated result set, resulting in up to 100 × 12 = 1,200 individual database queries per page load.
- Files: `src/Ajax/InvoiceAjaxHandler.php` (lines 593-629)
- Cause: WordPress does not batch `get_post_meta()` calls; each call is a database round-trip unless the meta has been primed by `update_post_meta_cache`.
- Improvement path: Call `update_post_meta_cache(array_column($query->posts, 'ID'))` before the foreach loop in `getInvoices()`, or consolidate all invoice meta into a single serialised option for read-heavy paths.

**`WooCommerceIntegration::syncClient()` uses `get_posts()` with `meta_key` and `meta_value`:**
- Problem: `syncClient()` (line 410) queries for existing clients using `meta_key` / `meta_value` in `get_posts()`. This generates a full table scan against `wp_postmeta` on the `_client_email` key. Without an index on `meta_value` (WordPress does not index it by default), this query degrades linearly with the number of client records.
- Files: `src/Integrations/WooCommerce/WooCommerceIntegration.php` (lines 409-420)
- Cause: WordPress meta table design; large meta value columns are not indexed.
- Improvement path: Consider adding a DB index on `(meta_key, meta_value(100))` for the postmeta table, or maintain a separate lookup table mapping email → client post ID.

**`NumberingService::acquireLock()` uses busy-waiting with exponential backoff:**
- Problem: The locking mechanism (lines 297-315) calls `usleep()` up to 5 times with sleeps of 100ms, 200ms, 400ms, 800ms, 1,600ms before failing. On high-concurrency invoicing (e.g. bulk WooCommerce order processing), PHP-FPM workers can be blocked for up to ~3 seconds waiting for a lock. Transients are stored in the options table and the get-then-set pattern is not atomic.
- Files: `src/Services/NumberingService.php` (lines 295-315)
- Cause: WordPress transients do not provide atomic compare-and-set; the current pattern has a race window between `get_transient()` and `set_transient()`.
- Improvement path: Use a database-level advisory lock (`GET_LOCK()`) or replace the sequential numbering scheme with a database `AUTO_INCREMENT` sequence table for true atomicity.

## Fragile Areas

**Settings tab detection heuristic is fragile:**
- Files: `src/Admin/Pages/SettingsPage.php` (lines 872-884)
- Why fragile: `determineActiveTabFromInput()` identifies the active tab by checking whether any field from that tab appears in the submitted input. If two tabs share a field name (unlikely but possible after future additions), the first matching tab wins. More importantly, this heuristic means the integrations tab checkbox fields (`woo_enabled`, `woo_auto_email`) that are unchecked will not appear in `$input`, so the function may incorrectly identify the tab as "general" and skip all WooCommerce field processing.
- Safe modification: Always include a hidden `active_tab` field in each settings form and read it directly rather than inferring it from submitted fields.
- Test coverage: None.

**`PdfService` instantiates `LineItemRepository` directly inside `getInvoiceData()`:**
- Files: `src/Services/PdfService.php` (line 147)
- Why fragile: `PdfService` bypasses the DI container and creates `new LineItemRepository()` inside a data-fetching method. This makes unit testing impossible (cannot inject a mock) and couples the PDF service tightly to the database layer. Any future change to `LineItemRepository`'s constructor signature will silently break PDF generation.
- Safe modification: Inject `LineItemRepository` through `PdfService`'s constructor.
- Test coverage: None.

**`InvoiceAjaxHandler` instantiates `ClientAjaxHandler` directly during inline client creation:**
- Files: `src/Ajax/InvoiceAjaxHandler.php` (line 269)
- Why fragile: `new ClientAjaxHandler($this->nonce, $this->sanitizer, $this->validator)` bypasses the DI container. Logger initialisation inside `ClientAjaxHandler::__construct()` (which calls `new Logger()` inside a try/catch) adds a second Logger instance to each inline-client creation request.
- Safe modification: Inject `ClientAjaxHandler` as a constructor dependency or resolve it from the container.
- Test coverage: None.

**`InvoicesPage.php` instantiates `LineItemRepository` directly:**
- Files: `src/Admin/Pages/InvoicesPage.php` (line 122)
- Why fragile: Same concern as `PdfService` above — bypasses DI, prevents testing.
- Safe modification: Pass `LineItemRepository` through `InvoicesPage`'s constructor.
- Test coverage: None.

## Scaling Limits

**All invoice data is stored as WordPress post meta:**
- Current capacity: Adequate for tens to hundreds of invoices per client.
- Limit: WordPress post meta is not designed for relational queries. Filtering invoices by client, date range, or status requires `WP_Query` with `meta_query`, which generates expensive JOIN operations against `wp_postmeta`. Above ~5,000 invoice records, list-page queries will become noticeably slow.
- Scaling path: Migrate invoice header data to a dedicated `invoiceforge_invoices` database table with proper indexes, or add query caching for the invoice list view.

**Invoice number locking via transients does not scale to multiple PHP workers:**
- Current capacity: Handles low-concurrency environments (single worker or serialised requests).
- Limit: The transient-based lock in `NumberingService` has a race condition between `get_transient()` and `set_transient()`. Under concurrent load (multiple PHP-FPM workers processing WooCommerce orders simultaneously), duplicate invoice numbers can be generated.
- Scaling path: Use a database `AUTO_INCREMENT` column on a dedicated sequence table, or use `SELECT GET_LOCK()` with a transaction.

## Missing Critical Features

**No PDF invoice template:**
- Problem: `templates/pdf/invoice-default.php` does not exist. Every PDF falls back to `PdfService::getFallbackHtml()`, which produces a minimal unstyled document with no logo, company branding, payment instructions, or professional layout.
- Blocks: Any production use of PDF export, PDF email attachments, or WooCommerce auto-email invoices.

**Tax rate management AJAX missing:**
- Problem: `saveTaxRate` and `deleteTaxRate` methods are not implemented. Tax rates can only be seeded via `TaxRateRepository::seedDefaults()` during activation; they cannot be created or deleted through the UI.
- Blocks: The Settings → Tax Rates management UI is non-functional.

**Payments table defined but entirely unused:**
- Problem: `Schema::getPaymentsSQL()` defines a `invoiceforge_payments` table and `Schema::createTables()` creates it on activation, but no model, repository, or UI exists for payment records. Invoice status must be manually changed to `paid`; there is no payment tracking, partial payment support, or payment history.
- Blocks: Any payment workflow beyond manual status updates.

## Test Coverage Gaps

**Zero test files:**
- What's not tested: All business logic in `NumberingService`, `TaxService`, `Sanitizer`, `Validator`, `Encryption`, `LineItemRepository`, `TaxRateRepository`, all AJAX handlers, all post type handlers, the WooCommerce integration, and all settings sanitisation logic.
- Files: Entire `src/` directory
- Risk: Any regression introduced during development cannot be caught automatically. The financial calculation logic (tax, discount, totals) is particularly high-risk to have untested.
- Priority: High

---

*Concerns audit: 2026-03-14*
