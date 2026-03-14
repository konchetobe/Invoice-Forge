# Technology Stack

**Analysis Date:** 2026-03-14

## Languages

**Primary:**
- PHP 8.1+ - All plugin logic, services, repositories, AJAX handlers, templates

**Secondary:**
- JavaScript (ES5/jQuery) - Admin UI interactivity (`assets/admin/js/admin.js`)
- CSS - Admin styling (`assets/admin/css/admin.css`)
- SQL - Custom table definitions in `src/Database/Schema.php`

## Runtime

**Environment:**
- PHP 8.1 minimum (`INVOICEFORGE_MIN_PHP_VERSION = '8.1'`)
- WordPress 6.0 minimum (`INVOICEFORGE_MIN_WP_VERSION = '6.0'`)
- MySQL/MariaDB - Via WordPress `$wpdb` abstraction

**Package Manager:**
- Composer (no npm/yarn present)
- Lockfile: `composer.lock` present

## Frameworks

**Core:**
- WordPress Plugin API - Hook system (actions/filters), post types, meta, options, AJAX
- No standalone PHP framework (Laravel, Symfony, etc.)

**Testing:**
- PHPUnit ^10.0 (dev) - Unit test runner
- Config: `phpunit.xml` (referenced in composer scripts)

**Build/Dev:**
- PHP_CodeSniffer ^3.7 (dev) with `wp-coding-standards/wpcs ^3.0` - Linting against WordPress coding standards
- PHPStan ^1.10 at level 6 (dev) - Static analysis

**CI/CD:**
- GitHub Actions - `.github/workflows/release.yml` - Triggers on `v*` tag push, runs `composer install --no-dev`, packages ZIP, creates GitHub Release via `softprops/action-gh-release@v1`

## Key Dependencies

**Critical:**
- `mpdf/mpdf ^8.2` - PDF generation for invoices (`src/Services/PdfService.php`). Checked at runtime via `class_exists(\Mpdf\Mpdf::class)`. Plugin degrades gracefully if absent.
- `yahnis-elsts/plugin-update-checker ^5.4` - GitHub-based auto-update mechanism (`src/Core/UpdateChecker.php`). Uses GitHub Releases ZIP assets from `konchetobe/Invoice-Forge`.

**Infrastructure:**
- PSR-4 autoloading via Composer - namespace `InvoiceForge\` maps to `src/`
- `vendor/autoload.php` loaded by `invoiceforge_load_autoloader()` in `invoiceforge.php`

## Configuration

**Environment:**
- No `.env` files - all configuration stored in WordPress options table under the key `invoiceforge_settings`
- Configurable via WordPress admin Settings page (4 tabs: General, Email, Advanced, Integrations)
- Key settings fields: `company_name`, `company_email`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password` (AES-256-GCM encrypted), `default_currency`, `invoice_prefix`, `woo_enabled`, `woo_trigger_statuses`

**Build:**
- `composer.json` - Dependency manifest
- `composer.lock` - Locked dependency versions
- No webpack/vite/npm build step - raw JS/CSS served directly
- Autoloader optimized for release: `composer update --no-dev --optimize-autoloader` (see `release.yml`)

## Encryption

- AES-256-GCM via PHP `openssl_encrypt`
- Key derived from WordPress `AUTH_KEY` + `SECURE_AUTH_KEY` salts, hashed with SHA-256
- Used for: SMTP password field in settings (`src/Security/Encryption.php`)

## Logging

- Custom file-based logger (`src/Utilities/Logger.php`)
- Log files: `wp-content/uploads/invoiceforge-logs/invoiceforge-YYYY-MM-DD.log`
- Levels: DEBUG, INFO, WARNING, ERROR
- 30-day retention with probabilistic cleanup (1% chance per write)
- Directory protected by `.htaccess` (deny all) and `index.php` sentinel

## Platform Requirements

**Development:**
- PHP 8.1+
- Composer installed
- WordPress 6.0+ environment
- Run `composer install` before activating

**Production:**
- WordPress-hosted environment (shared hosting, VPS, managed WP)
- PHP 8.1+ with `openssl` extension (for AES-256-GCM encryption)
- MySQL/MariaDB (WordPress requirement)
- Deployed as standard WordPress plugin ZIP via GitHub Releases

---

*Stack analysis: 2026-03-14*
