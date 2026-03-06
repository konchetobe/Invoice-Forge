# InvoiceForge Installation Guide

Welcome to InvoiceForge - a production-grade WordPress invoice management plugin.

---

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Installation Instructions](#installation-instructions)
3. [Quick Start Guide](#quick-start-guide)
4. [Accessing the Plugin](#accessing-the-plugin)
5. [Phase 1A Features](#phase-1a-features)
6. [Troubleshooting](#troubleshooting)

---

## System Requirements

### Minimum Requirements

| Component | Version |
|-----------|---------|
| WordPress | 6.0 or higher |
| PHP | 8.1 or higher |
| MySQL | 5.7+ or MariaDB 10.3+ |
| Web Server | Apache 2.4+ or Nginx |

### Recommended Requirements

| Component | Version |
|-----------|---------|
| WordPress | 6.4+ |
| PHP | 8.2+ |
| Memory Limit | 256MB+ |
| Max Execution Time | 60 seconds |

### Required PHP Extensions

- `json` (usually bundled with PHP)
- `mbstring` (for multi-byte string handling)
- `openssl` (for encryption features)

---

## Installation Instructions

### Method 1: Standard WordPress Upload (Recommended)

1. **Download the Plugin**
   - Download the `invoiceforge` folder or create a zip file of the plugin directory.

2. **Upload to WordPress**
   - Log in to your WordPress admin dashboard
   - Navigate to **Plugins → Add New → Upload Plugin**
   - Choose the `invoiceforge.zip` file
   - Click **Install Now**

3. **Activate the Plugin**
   - After installation, click **Activate Plugin**
   - Or go to **Plugins → Installed Plugins** and click **Activate** next to InvoiceForge

### Method 2: FTP/SFTP Upload

1. **Prepare the Files**
   - Ensure the plugin directory contains the `vendor/` folder with the autoloader
   - If not, run `composer install` in the plugin directory first

2. **Upload via FTP**
   - Connect to your server via FTP/SFTP
   - Navigate to `/wp-content/plugins/`
   - Upload the entire `invoiceforge` folder

3. **Activate**
   - Go to your WordPress admin → **Plugins**
   - Find InvoiceForge and click **Activate**

### Method 3: WP-CLI Installation

```bash
# Navigate to your WordPress installation
cd /path/to/wordpress

# Copy the plugin
cp -r /path/to/invoiceforge wp-content/plugins/

# Activate via WP-CLI
wp plugin activate invoiceforge
```

### Post-Installation: Composer Dependencies

If you're installing from source (without the vendor folder):

```bash
cd wp-content/plugins/invoiceforge
composer install --no-dev --optimize-autoloader
```

For development installations (with testing tools):

```bash
cd wp-content/plugins/invoiceforge
composer install
```

---

## Quick Start Guide

### Step 1: Access the Dashboard
After activation, look for **InvoiceForge** in your WordPress admin sidebar.

### Step 2: Configure Settings
1. Go to **InvoiceForge → Settings**
2. Set your **Business Name** and details
3. Configure the **Invoice Prefix** (default: INV)
4. Set your **Default Currency**
5. Click **Save Changes**

### Step 3: Create Your First Client
1. Go to **InvoiceForge → Clients → Add New**
2. Enter the client's name (title) and contact information
3. Fill in address and billing details
4. Click **Publish**

### Step 4: Create Your First Invoice
1. Go to **InvoiceForge → Invoices → Add New**
2. Enter an invoice title/description
3. Select the client from the dropdown
4. Set invoice date and due date
5. Enter the total amount
6. Set status (Draft, Pending, Paid, etc.)
7. Click **Publish**

### Step 5: View Your Dashboard
Go to **InvoiceForge → Dashboard** to see:
- Total invoices and clients
- Revenue overview
- Outstanding amounts
- Recent activity

---

## Accessing the Plugin

After activation, InvoiceForge adds a new top-level menu to your WordPress admin:

### Menu Structure

```
InvoiceForge (Dashboard icon)
├── Dashboard       - Overview and statistics
├── Invoices        - Manage all invoices
│   └── Add New     - Create new invoice
├── Clients         - Manage all clients
│   └── Add New     - Create new client
└── Settings        - Plugin configuration
```

### Direct Access URLs

Replace `your-site.com` with your actual domain:

| Page | URL |
|------|-----|
| Dashboard | `your-site.com/wp-admin/admin.php?page=invoiceforge` |
| Invoices | `your-site.com/wp-admin/edit.php?post_type=if_invoice` |
| Clients | `your-site.com/wp-admin/edit.php?post_type=if_client` |
| Settings | `your-site.com/wp-admin/admin.php?page=invoiceforge-settings` |

### User Capabilities

InvoiceForge respects WordPress user roles:

| Capability | Description |
|------------|-------------|
| `manage_invoiceforge` | Full access to all features (Administrators) |
| `edit_invoices` | Create and edit invoices |
| `delete_invoices` | Delete invoices |
| `view_invoiceforge_reports` | View dashboard and reports |

---

## Phase 1A Features

Phase 1A establishes the core foundation of InvoiceForge. Here's what's included:

### ✅ Available Features

#### Invoice Management
- Create, edit, and delete invoices
- Auto-generated sequential invoice numbers (INV-2025-0001)
- Invoice statuses: Draft, Pending, Paid, Partially Paid, Overdue, Cancelled, Refunded
- Invoice date and due date tracking
- Multi-currency support (USD, EUR, GBP, CAD, AUD, JPY, INR)
- Notes/memo field for additional information
- Admin list view with sortable columns

#### Client Management
- Complete client database
- Contact information (email, phone)
- Company/organization name
- Full address with country selection
- Billing information and Tax ID
- View total invoices per client
- Admin list view with custom columns

#### Dashboard
- Quick statistics overview
- Total invoices and clients count
- Revenue and outstanding amounts
- Invoice breakdown by status
- Recent invoices list
- Recent clients list
- Quick action buttons

#### Settings
- **General Tab**: Business info, invoice prefix, numbering, default currency
- **Email Tab**: From name, from email, SMTP configuration (preparation for Phase 1C)
- **Advanced Tab**: Debug logging toggle

#### Security Features
- Input sanitization on all user data
- WordPress nonce verification
- Role-based access control
- Encrypted storage for sensitive data (SMTP passwords)
- XSS prevention throughout

#### Developer Features
- PSR-4 autoloading via Composer
- PHPStan static analysis configuration
- WordPress Coding Standards (WPCS)
- PHPUnit test framework setup
- Modular architecture for extensibility
- Comprehensive logging utility

### 🔜 Coming in Future Phases

| Feature | Target Phase |
|---------|--------------|
| Line items with quantity/price | Phase 1B |
| Tax rates and calculations | Phase 1B |
| Custom database tables | Phase 1B |
| PDF invoice generation | Phase 1C |
| Email invoices to clients | Phase 1C |
| Payment tracking | Phase 1D |
| Payment gateway integration | Phase 2 |
| Client portal | Phase 2 |
| Recurring invoices | Phase 3 |
| Reports and analytics | Phase 3 |

---

## Troubleshooting

### Common Issues

#### 1. "Plugin could not be activated" Error

**Cause**: PHP version too low or missing autoloader

**Solutions**:
```bash
# Check PHP version (must be 8.1+)
php -v

# Regenerate autoloader
cd wp-content/plugins/invoiceforge
composer dump-autoload -o
```

#### 2. White Screen or Fatal Error After Activation

**Cause**: Missing Composer dependencies

**Solution**:
```bash
cd wp-content/plugins/invoiceforge
composer install --no-dev
```

#### 3. Menu Not Appearing

**Cause**: Insufficient user permissions

**Solutions**:
- Ensure you're logged in as an Administrator
- Check if another plugin is conflicting
- Deactivate other plugins temporarily to test

#### 4. Invoice Numbers Not Auto-Generating

**Cause**: Option not saved or transient lock issue

**Solutions**:
1. Go to **InvoiceForge → Settings**
2. Verify "Auto Generate Invoice Numbers" is checked
3. Click **Save Changes**
4. If issue persists, click "Reset Counter" in Advanced tab

#### 5. Styles Not Loading / Broken Layout

**Cause**: CSS not enqueued or cached

**Solutions**:
- Clear your browser cache
- Clear any WordPress caching plugins
- Check browser console for 404 errors on CSS files

#### 6. SMTP Settings Not Working

**Note**: SMTP email functionality is planned for Phase 1C. The settings fields are available for configuration but email sending is not yet active.

### Debug Mode

Enable debug logging to diagnose issues:

1. Go to **InvoiceForge → Settings → Advanced**
2. Check **Enable Debug Logging**
3. Click **Save Changes**
4. Reproduce the issue
5. Check logs in `wp-content/uploads/invoiceforge-logs/`

### WordPress Debug Mode

Add to `wp-config.php` for additional debugging:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check `wp-content/debug.log` for errors.

### Getting Help

If you encounter issues not covered here:

1. **Check WordPress compatibility** - Ensure WordPress 6.0+
2. **Check server error logs** - Often reveals the actual error
3. **Disable other plugins** - Test for conflicts
4. **Switch to default theme** - Test for theme conflicts

### Uninstallation

InvoiceForge includes a clean uninstall process:

1. Deactivate the plugin in **Plugins**
2. Click **Delete** to remove

**Warning**: Uninstalling will remove:
- All plugin options and settings
- All custom capabilities
- Log files

**Note**: Invoices and Clients (custom post types) will remain in the database unless manually deleted.

---

## File Structure Reference

```
invoiceforge/
├── assets/
│   └── admin/
│       ├── css/admin.css      # Admin styles
│       └── js/admin.js        # Admin JavaScript
├── src/
│   ├── Admin/                 # Admin controllers and pages
│   ├── Core/                  # Plugin core (loader, activator)
│   ├── Database/              # Database schema definitions
│   ├── PostTypes/             # Custom post types (Invoice, Client)
│   ├── Security/              # Sanitization, validation, encryption
│   ├── Services/              # Business logic services
│   └── Utilities/             # Helper utilities (Logger)
├── templates/
│   └── admin/                 # Admin page templates
├── vendor/                    # Composer dependencies
├── composer.json              # Composer configuration
├── invoiceforge.php           # Main plugin file
├── uninstall.php              # Cleanup on uninstall
└── INSTALLATION.md            # This file
```

---

## Version Information

- **Plugin Version**: 1.0.0 (Phase 1A)
- **Minimum WordPress**: 6.0
- **Minimum PHP**: 8.1
- **License**: GPL-2.0-or-later

---

*Thank you for using InvoiceForge!*
