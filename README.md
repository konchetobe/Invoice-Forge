# InvoiceForge - Professional Invoice Management for WordPress

[![WordPress Version](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

InvoiceForge is a production-grade WordPress invoice management plugin designed for freelancers, small businesses, and agencies. It provides comprehensive invoicing capabilities with payment gateway integration, client portal, multi-currency support, and compliance-ready templates.

## Features (Phase 1A - Foundation)

### Core Features
- **Invoice Management**: Create, edit, and manage invoices with sequential numbering
- **Client Management**: Maintain a comprehensive client database
- **Custom Post Types**: Native WordPress integration for invoices and clients
- **Admin Dashboard**: Clean, intuitive interface for managing all aspects

### Security
- Nonce verification on all forms
- Capability-based access control
- Input sanitization and validation
- Encrypted storage for sensitive data (API keys)

### Technical Features
- PSR-4 autoloading with Composer
- PHP 8.1+ with strict types
- WordPress coding standards compliance
- Translation-ready (i18n)
- Comprehensive logging system

## Requirements

- WordPress 6.0 or higher
- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Composer (for development)

## Installation

### From WordPress Admin
1. Download the latest release ZIP file
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin

### Manual Installation
1. Clone or download this repository:
   ```bash
   git clone https://github.com/invoiceforge/invoiceforge.git
   cd invoiceforge
   ```

2. Install Composer dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. Copy the `invoiceforge` folder to your WordPress plugins directory:
   ```bash
   cp -r invoiceforge /path/to/wordpress/wp-content/plugins/
   ```

4. Activate the plugin in WordPress admin

### Development Installation
```bash
git clone https://github.com/invoiceforge/invoiceforge.git
cd invoiceforge
composer install
```

## Configuration

### Initial Setup
1. Navigate to **InvoiceForge > Settings**
2. Configure your company information:
   - Company Name
   - Company Email
   - Company Phone
   - Company Address
   - Company Logo
3. Configure email settings (optional)
4. Save settings

### Creating Your First Invoice
1. Go to **InvoiceForge > Clients**
2. Add a new client with their details
3. Go to **InvoiceForge > Invoices**
4. Click **Add New Invoice**
5. Select the client, set dates, and fill in details
6. Save the invoice

## File Structure

```
invoiceforge/
├── assets/
│   └── admin/
│       ├── css/admin.css
│       └── js/admin.js
├── languages/
├── src/
│   ├── Admin/
│   │   ├── AdminController.php
│   │   ├── Assets.php
│   │   └── Pages/
│   │       ├── ClientsPage.php
│   │       ├── InvoicesPage.php
│   │       └── SettingsPage.php
│   ├── Core/
│   │   ├── Activator.php
│   │   ├── Container.php
│   │   ├── Deactivator.php
│   │   ├── Loader.php
│   │   └── Plugin.php
│   ├── Database/
│   │   └── Schema.php
│   ├── PostTypes/
│   │   ├── ClientPostType.php
│   │   └── InvoicePostType.php
│   ├── Security/
│   │   ├── Capabilities.php
│   │   ├── Encryption.php
│   │   ├── Nonce.php
│   │   ├── Sanitizer.php
│   │   └── Validator.php
│   ├── Services/
│   │   └── NumberingService.php
│   └── Utilities/
│       └── Logger.php
├── templates/
│   └── admin/
│       ├── client-editor.php
│       ├── client-list.php
│       ├── invoice-editor.php
│       ├── invoice-list.php
│       └── settings.php
├── AGENTS.md
├── composer.json
├── invoiceforge.php
├── README.md
├── ROADMAP.md
└── uninstall.php
```

## Development

### Running Tests
```bash
composer test
```

### Code Quality
```bash
# PHP CodeSniffer
composer phpcs

# Auto-fix coding standards
composer phpcbf

# Static analysis
composer phpstan
```

### Building for Production
```bash
composer install --no-dev --optimize-autoloader
```

## Hooks & Filters

### Actions
- `invoiceforge_invoice_created` - Fired when an invoice is created
- `invoiceforge_invoice_updated` - Fired when an invoice is updated
- `invoiceforge_client_created` - Fired when a client is created
- `invoiceforge_settings_saved` - Fired when settings are saved

### Filters
- `invoiceforge_invoice_number_format` - Customize invoice number format
- `invoiceforge_invoice_statuses` - Modify available invoice statuses
- `invoiceforge_default_currency` - Set default currency
- `invoiceforge_admin_menu_capability` - Change required capability for admin menu

## Roadmap

See [ROADMAP.md](ROADMAP.md) for the complete implementation plan.

## Contributing

See [AGENTS.md](AGENTS.md) for AI development instructions and coding standards.

## Support

- Documentation: https://invoiceforge.io/docs
- Support: support@invoiceforge.io
- GitHub Issues: https://github.com/invoiceforge/invoiceforge/issues

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Built with ❤️ by the InvoiceForge Team
