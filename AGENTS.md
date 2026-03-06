# InvoiceForge - AI Development Instructions

This document provides guidelines for AI agents (GitHub Copilot, Claude, ChatGPT, etc.) working on the InvoiceForge codebase.

## Project Overview

InvoiceForge is a production-grade WordPress invoice management plugin. It follows modern PHP practices while maintaining WordPress compatibility and coding standards.

### Tech Stack
- **PHP**: 8.1+ with strict types
- **WordPress**: 6.0+
- **Autoloading**: PSR-4 via Composer
- **Namespace**: `InvoiceForge\`
- **PDF**: mPDF (Phase 1C)
- **Charts**: Chart.js (Phase 1D)

### Key Directories
```
src/           - PHP classes (PSR-4 autoloaded)
templates/     - PHP template files
assets/        - CSS, JS, images
languages/     - Translation files (.pot, .po, .mo)
tests/         - PHPUnit tests
```

---

## Coding Standards

### PHP Standards

1. **Strict Types**: Always declare strict types
   ```php
   <?php
   declare(strict_types=1);
   ```

2. **Namespace**: All classes must be namespaced
   ```php
   namespace InvoiceForge\Admin\Pages;
   ```

3. **Type Hints**: Use type hints for parameters and return types
   ```php
   public function getInvoice(int $id): ?Invoice
   ```

4. **Nullable Types**: Use `?Type` or `Type|null` for nullable
   ```php
   public function findClient(?int $id): ?Client
   ```

5. **Arrays**: Use typed arrays in docblocks
   ```php
   /**
    * @param array<string, mixed> $data
    * @return array<int, Invoice>
    */
   ```

### WordPress Standards

1. **Hooks**: Use descriptive action/filter names with prefix
   ```php
   do_action('invoiceforge_invoice_created', $invoice_id);
   apply_filters('invoiceforge_invoice_statuses', $statuses);
   ```

2. **Nonces**: Always verify nonces on form submissions
   ```php
   if (!wp_verify_nonce($_POST['_nonce'], 'invoiceforge_save_invoice')) {
       wp_die(__('Security check failed.', 'invoiceforge'));
   }
   ```

3. **Capabilities**: Check user capabilities
   ```php
   if (!current_user_can('edit_posts')) {
       wp_die(__('Unauthorized access.', 'invoiceforge'));
   }
   ```

4. **Sanitization**: Sanitize all input
   ```php
   $title = sanitize_text_field($_POST['title'] ?? '');
   $email = sanitize_email($_POST['email'] ?? '');
   $content = wp_kses_post($_POST['content'] ?? '');
   ```

5. **Escaping**: Escape all output
   ```php
   echo esc_html($title);
   echo esc_attr($value);
   echo esc_url($url);
   echo wp_kses_post($content);
   ```

6. **Database**: Use prepared statements
   ```php
   $wpdb->prepare(
       "SELECT * FROM {$wpdb->prefix}table WHERE id = %d",
       $id
   );
   ```

### Documentation

1. **File Headers**: Every PHP file needs a header
   ```php
   /**
    * Invoice Post Type Registration
    *
    * @package    InvoiceForge
    * @subpackage PostTypes
    * @since      1.0.0
    */
   ```

2. **Class Documentation**
   ```php
   /**
    * Handles invoice post type registration and meta boxes.
    *
    * @since 1.0.0
    */
   class InvoicePostType
   ```

3. **Method Documentation**
   ```php
   /**
    * Register the invoice custom post type.
    *
    * @since 1.0.0
    *
    * @param string $post_type The post type name.
    * @return void
    */
   public function register(string $post_type): void
   ```

4. **Inline Comments**: Explain complex logic
   ```php
   // Lock to prevent race conditions during invoice number generation
   $lock = get_transient('invoiceforge_number_lock');
   ```

---

## Architecture Patterns

### Singleton Pattern (Plugin.php)
```php
class Plugin
{
    private static ?Plugin $instance = null;

    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
}
```

### Dependency Injection (Container.php)
```php
// Register
$container->register('logger', fn() => new Logger());

// Resolve
$logger = $container->resolve('logger');
```

### Repository Pattern (Phase 1B+)
```php
interface InvoiceRepositoryInterface
{
    public function find(int $id): ?Invoice;
    public function save(Invoice $invoice): int;
    public function delete(int $id): bool;
}
```

### Service Pattern
```php
class NumberingService
{
    public function generate(): string
    {
        // Generate sequential invoice number
        return sprintf('INV-%d-%04d', $year, $number);
    }
}
```

---

## Security Guidelines

### Input Handling

```php
// Always sanitize based on expected data type
$id = absint($_GET['id'] ?? 0);
$email = sanitize_email($_POST['email'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);
$date = sanitize_text_field($_POST['date'] ?? '');

// Validate after sanitization
if (!is_email($email)) {
    // Handle invalid email
}
```

### Output Escaping

```php
// In templates, ALWAYS escape
<input type="text" value="<?php echo esc_attr($value); ?>">
<p><?php echo esc_html($message); ?></p>
<a href="<?php echo esc_url($link); ?>">Link</a>
<?php echo wp_kses_post($html_content); ?>
```

### Nonce Verification

```php
// Create nonce in form
wp_nonce_field('invoiceforge_save_invoice', 'invoiceforge_nonce');

// Verify nonce on submit
if (!isset($_POST['invoiceforge_nonce']) || 
    !wp_verify_nonce($_POST['invoiceforge_nonce'], 'invoiceforge_save_invoice')) {
    wp_die(__('Security check failed.', 'invoiceforge'));
}
```

### Capability Checks

```php
// Check before any privileged action
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'invoiceforge'));
}

// For post types
if (!current_user_can('edit_post', $post_id)) {
    wp_die(__('You cannot edit this invoice.', 'invoiceforge'));
}
```

---

## Translation (i18n)

### Text Strings

```php
// Simple string
__('Invoice', 'invoiceforge')

// String with escaping for output
esc_html__('Invoice saved.', 'invoiceforge')

// String with placeholder
sprintf(
    /* translators: %s: Invoice number */
    __('Invoice %s has been created.', 'invoiceforge'),
    $invoice_number
)

// Plural
sprintf(
    /* translators: %d: Number of invoices */
    _n(
        '%d invoice found.',
        '%d invoices found.',
        $count,
        'invoiceforge'
    ),
    $count
)
```

### JavaScript Localization

```php
wp_localize_script('invoiceforge-admin', 'InvoiceForge', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('invoiceforge_ajax'),
    'i18n' => [
        'confirmDelete' => __('Are you sure you want to delete?', 'invoiceforge'),
        'saving' => __('Saving...', 'invoiceforge'),
    ],
]);
```

---

## File Templates

### PHP Class Template

```php
<?php
/**
 * [Description]
 *
 * @package    InvoiceForge
 * @subpackage [Subpackage]
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\[Namespace];

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * [Class description]
 *
 * @since 1.0.0
 */
class [ClassName]
{
    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        // Initialize
    }
}
```

### Admin Template

```php
<?php
/**
 * [Template description]
 *
 * @package    InvoiceForge
 * @subpackage Admin/Templates
 * @since      1.0.0
 *
 * @var array $data Template data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap invoiceforge-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Template content -->
</div>
```

---

## Common Patterns

### AJAX Handler

```php
// Register in Plugin.php
add_action('wp_ajax_invoiceforge_save', [$this, 'handleAjaxSave']);

// Handler method
public function handleAjaxSave(): void
{
    // Verify nonce
    check_ajax_referer('invoiceforge_ajax', 'nonce');
    
    // Check capability
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('Unauthorized.', 'invoiceforge')]);
    }
    
    // Process data
    $data = $this->sanitizer->sanitize($_POST);
    
    // Respond
    wp_send_json_success(['id' => $saved_id]);
}
```

### Meta Box

```php
// Register
add_action('add_meta_boxes', function() {
    add_meta_box(
        'invoiceforge_details',
        __('Invoice Details', 'invoiceforge'),
        [$this, 'renderMetaBox'],
        'if_invoice',
        'normal',
        'high'
    );
});

// Save
add_action('save_post_if_invoice', function($post_id) {
    // Verify nonce and capability
    // Sanitize and save meta
    update_post_meta($post_id, '_invoice_status', $status);
});
```

### Settings API

```php
// Register settings
register_setting(
    'invoiceforge_settings',
    'invoiceforge_settings',
    [
        'type' => 'array',
        'sanitize_callback' => [$this, 'sanitizeSettings'],
        'default' => $this->getDefaults(),
    ]
);

// Add section
add_settings_section(
    'invoiceforge_general',
    __('General Settings', 'invoiceforge'),
    [$this, 'renderSection'],
    'invoiceforge-settings'
);

// Add field
add_settings_field(
    'company_name',
    __('Company Name', 'invoiceforge'),
    [$this, 'renderTextField'],
    'invoiceforge-settings',
    'invoiceforge_general',
    ['id' => 'company_name']
);
```

---

## Testing

### Unit Test Template

```php
<?php
namespace InvoiceForge\Tests;

use InvoiceForge\Services\NumberingService;
use PHPUnit\Framework\TestCase;

class NumberingServiceTest extends TestCase
{
    private NumberingService $service;

    protected function setUp(): void
    {
        $this->service = new NumberingService();
    }

    public function testGenerateReturnsCorrectFormat(): void
    {
        $number = $this->service->generate();
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{4}$/', $number);
    }
}
```

---

## Common Mistakes to Avoid

1. **Don't** use raw `$_GET`, `$_POST` without sanitization
2. **Don't** output data without escaping
3. **Don't** use direct database queries without `$wpdb->prepare()`
4. **Don't** forget nonce verification on forms
5. **Don't** skip capability checks
6. **Don't** hardcode text (use translation functions)
7. **Don't** use `echo` in class methods (return instead)
8. **Don't** mix HTML in PHP classes (use templates)
9. **Don't** create god classes (keep single responsibility)
10. **Don't** forget to flush rewrite rules on CPT registration

---

## Quick Reference

### Prefixes
- Options: `invoiceforge_`
- Post types: `if_`
- Meta keys: `_invoice_`, `_client_`
- Transients: `invoiceforge_`
- Hooks: `invoiceforge_`
- CSS classes: `invoiceforge-`
- JS objects: `InvoiceForge`

### Important Files
- `src/Core/Plugin.php` - Main orchestrator
- `src/Core/Container.php` - Dependency injection
- `src/Security/` - All security utilities
- `src/PostTypes/` - Custom post types
- `src/Admin/` - Admin interface

### Useful Commands
```bash
# Install dependencies
composer install

# Code style check
composer phpcs

# Auto-fix code style
composer phpcbf

# Static analysis
composer phpstan

# Run tests
composer test
```

---

## Getting Help

- Check `ROADMAP.md` for implementation status
- Review existing code in similar classes
- Follow established patterns in the codebase
- When in doubt, prioritize security
