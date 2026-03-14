# External Integrations

**Analysis Date:** 2026-03-14

## APIs & External Services

**Plugin Auto-Updates:**
- GitHub Releases - Delivers plugin update ZIPs for the WordPress update system
  - SDK/Client: `yahnis-elsts/plugin-update-checker ^5.4` (Composer)
  - Implementation: `src/Core/UpdateChecker.php`
  - Repo: `konchetobe/Invoice-Forge` (hardcoded constant `GITHUB_REPO`)
  - Mechanism: `PucFactory::buildUpdateChecker()` with `enableReleaseAssets()` — polls GitHub Releases API for new versions, delivers the ZIP asset attached to each release
  - Auth: No auth token; public repo only

**PDF Generation:**
- mPDF library - Renders HTML invoice templates to PDF
  - SDK/Client: `mpdf/mpdf ^8.2` (Composer)
  - Implementation: `src/Services/PdfService.php`
  - Template: `templates/pdf/invoice-default.php` (falls back to inline HTML if absent)
  - No external API call; fully local/server-side
  - Graceful degradation: checked via `class_exists(\Mpdf\Mpdf::class)` before use

## Data Storage

**Databases:**
- MySQL/MariaDB via WordPress `$wpdb`
  - Connection: Inherited from WordPress `wp-config.php` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`)
  - Client: WordPress `$wpdb` global (no separate ORM)
  - Invoice/client data: WordPress post meta (`wp_postmeta`) on custom post types `if_invoice` and `if_client`
  - Custom tables created on activation (`src/Database/Schema.php` via `dbDelta()`):
    - `{prefix}invoiceforge_invoice_items` - Line items per invoice
    - `{prefix}invoiceforge_payments` - Payment records (gateway, transaction_id, status, refund tracking)
    - `{prefix}invoiceforge_tax_rates` - Configurable tax rates
  - Schema version tracked in `invoiceforge_db_version` option

**File Storage:**
- Local filesystem only (WordPress uploads directory)
- Log files: `wp-content/uploads/invoiceforge-logs/`
- Temporary PDF files: `sys_get_temp_dir()` — created and immediately deleted after email attachment

**Caching:**
- None (no object cache, transients, or Redis/Memcached usage detected)

## Authentication & Identity

**Auth Provider:**
- WordPress native — no external auth provider
  - Capabilities: Custom capability `manage_invoices` checked via `src/Security/Capabilities.php`
  - Nonces: WordPress `wp_create_nonce` / `wp_verify_nonce` via `src/Security/Nonce.php`
  - All AJAX endpoints verify nonce + capability before processing

## Email

**Provider:**
- WordPress `wp_mail()` — default delivery path
  - Implementation: `src/Services/EmailService.php`
  - Sends invoice emails with optional PDF attachment (temp file, cleaned up after send)
  - Sends payment reminder emails

**Optional SMTP Override:**
- Configurable via Settings → Email tab (stored in `invoiceforge_settings`)
- Settings: `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password` (AES-256-GCM encrypted at rest), `smtp_encryption` (TLS/SSL/None)
- Note: SMTP settings are stored but the plugin does NOT apply them itself — a separate SMTP plugin (e.g., WP Mail SMTP) or `phpmailer_init` hook would be needed to consume these settings. The fields exist in the UI but the connection between the stored SMTP config and actual `wp_mail` override is not yet implemented.

## WooCommerce Integration

**Status:** Optional, enable/disable via Settings → Integrations tab (`woo_enabled`)
- Implementation: `src/Integrations/WooCommerce/WooCommerceIntegration.php`
- Availability guard: `class_exists('WooCommerce')`

**Triggers:**
- Hooks onto `woocommerce_order_status_{status}` actions for configurable statuses (default: `wc-completed`)
- Fires `invoiceforge_invoice_generated_from_order` action after invoice creation

**Behavior on trigger:**
1. Creates `if_invoice` post from WC order data
2. Maps WC order items + shipping as `invoiceforge_invoice_items` line items
3. Syncs billing data to `if_client` post (finds existing by `_client_email` meta or creates new)
4. Links invoice back to order via `_invoiceforge_invoice_id` meta on the order post
5. Optionally auto-emails PDF invoice if `woo_auto_email` is enabled

**Admin UI integration:**
- Adds meta box to WooCommerce order edit screens (`shop_order`, `woocommerce_page_wc-orders`)
- Box shows linked invoice number, edit link, and PDF download link; or a "Generate Invoice" button if none exists
- AJAX action `invoiceforge_generate_from_order` for manual invoice generation from order screen

**Invoice numbering options:**
- `invoiceforge` mode: Uses InvoiceForge sequential numbering service
- `woocommerce` mode: Uses WC order number with configurable prefix (`woo_invoice_prefix`)

## Monitoring & Observability

**Error Tracking:**
- None (no Sentry, Bugsnag, Rollbar, etc.)

**Logs:**
- Custom file-based logger; see `src/Utilities/Logger.php`
- Errors also written to WordPress `error_log()` when `WP_DEBUG` is true (for update checker failures and encryption errors)

## CI/CD & Deployment

**Hosting:**
- Standard WordPress plugin — deployed to any WordPress-compatible host
- No platform-specific cloud configuration

**CI Pipeline:**
- GitHub Actions: `.github/workflows/release.yml`
  - Trigger: Push of `v*` tags
  - Steps: Checkout → PHP 8.1 + Composer setup → `composer update --no-dev --optimize-autoloader` → rsync to `/tmp/invoiceforge/` (excludes `.git`, `.github`, `tests`, `phpunit.xml`, `node_modules`) → zip to `invoiceforge-plugin-installable.zip` → GitHub Release with auto-generated release notes

## Webhooks & Callbacks

**Incoming:**
- WordPress AJAX endpoints (via `wp_ajax_{action}` hooks, not external webhooks):
  - `invoiceforge_save_invoice` - Save invoice via admin AJAX
  - `invoiceforge_delete_invoice` - Delete invoice
  - `invoiceforge_get_clients` - Fetch client list
  - `invoiceforge_download_pdf` - Stream PDF download
  - `invoiceforge_send_invoice_email` - Send invoice email
  - `invoiceforge_generate_from_order` - Manually generate invoice from WC order
  - Additional client CRUD actions via `src/Ajax/ClientAjaxHandler.php`

**Outgoing:**
- GitHub API (read-only): Plugin update checker polls GitHub Releases API to detect new versions
- `wp_mail()` SMTP delivery: Outbound email to client addresses

---

*Integration audit: 2026-03-14*
