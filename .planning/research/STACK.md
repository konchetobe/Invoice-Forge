# Technology Stack

**Project:** InvoiceForge  
**Researched:** March 14, 2026  

## Recommended Stack

### Core Framework
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| PHP | 8.1+ | Server-side scripting | WordPress requires PHP 7.2.24+ but recommends 8.3; 8.1+ enables modern features like enums, readonly properties |
| WordPress | 6.0+ | CMS platform | Provides hooks, APIs, and core functionality for plugins |
| Composer | Latest | Dependency management | Enables PSR-4 autoloading and library management |

### Database
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| MySQL/MariaDB | 5.5.5+/10.6+ | Data storage | WordPress default; supports prepared statements for security |

### Infrastructure
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Apache/Nginx | Latest | Web server | Standard hosting for WordPress sites |
| HTTPS | Required | Secure communication | WordPress recommends HTTPS for security |

### Supporting Libraries
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| mPDF | Latest | PDF generation | For invoice PDFs (Phase 1C) |
| Chart.js | Latest | Data visualization | For dashboard charts (Phase 1D) |

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| PHP Version | 8.1+ | 7.4 | Older versions lack modern features and security improvements |
| Autoloading | PSR-4 via Composer | Manual include/require | PSR-4 is standard and prevents naming conflicts |
| Database | WordPress $wpdb | Direct PDO/MySQLi | $wpdb provides abstraction and security features |

## Installation

```bash
# Install Composer dependencies
composer install

# Install WordPress Coding Standards
composer require --dev wp-coding-standards/wpcs phpcompatibility/phpcompatibility-wp

# Set up PHPCS
./vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs
```

## Sources

- WordPress Requirements: https://wordpress.org/about/requirements/
- PHP Coding Standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/
- Plugin Best Practices: https://developer.wordpress.org/plugins/plugin-basics/best-practices/