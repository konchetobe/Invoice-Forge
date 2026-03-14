<?php
/**
 * InvoiceForge - Professional Invoice Management for WordPress
 *
 * @package     InvoiceForge
 * @author      InvoiceForge Team
 * @copyright   2025 InvoiceForge
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       InvoiceForge
 * Plugin URI:        https://invoiceforge.io
 * Description:       A production-grade WordPress invoice management plugin with payment gateways, client portal, multi-currency support, and compliance-ready templates.
 * Version:           1.1.5
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            InvoiceForge Team
 * Author URI:        https://invoiceforge.io
 * Text Domain:       invoiceforge
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://invoiceforge.io/updates/
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin constants
 */
define('INVOICEFORGE_VERSION', '1.1.5');
define('INVOICEFORGE_PLUGIN_FILE', __FILE__);
define('INVOICEFORGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INVOICEFORGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('INVOICEFORGE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('INVOICEFORGE_MIN_PHP_VERSION', '8.1');
define('INVOICEFORGE_MIN_WP_VERSION', '6.0');

/**
 * Check PHP version compatibility
 *
 * @return bool True if PHP version is compatible
 */
function invoiceforge_check_php_version(): bool
{
    return version_compare(PHP_VERSION, INVOICEFORGE_MIN_PHP_VERSION, '>=');
}

/**
 * Check WordPress version compatibility
 *
 * @return bool True if WordPress version is compatible
 */
function invoiceforge_check_wp_version(): bool
{
    global $wp_version;
    return version_compare($wp_version, INVOICEFORGE_MIN_WP_VERSION, '>=');
}

/**
 * Display admin notice for incompatible PHP version
 *
 * @return void
 */
function invoiceforge_php_version_notice(): void
{
    $message = sprintf(
        /* translators: 1: Required PHP version, 2: Current PHP version */
        esc_html__('InvoiceForge requires PHP version %1$s or higher. Your current version is %2$s. Please upgrade PHP to use this plugin.', 'invoiceforge'),
        INVOICEFORGE_MIN_PHP_VERSION,
        PHP_VERSION
    );
    
    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        $message
    );
}

/**
 * Display admin notice for incompatible WordPress version
 *
 * @return void
 */
function invoiceforge_wp_version_notice(): void
{
    global $wp_version;
    
    $message = sprintf(
        /* translators: 1: Required WordPress version, 2: Current WordPress version */
        esc_html__('InvoiceForge requires WordPress version %1$s or higher. Your current version is %2$s. Please upgrade WordPress to use this plugin.', 'invoiceforge'),
        INVOICEFORGE_MIN_WP_VERSION,
        $wp_version
    );
    
    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        $message
    );
}

/**
 * Load Composer autoloader
 *
 * @return bool True if autoloader loaded successfully
 */
function invoiceforge_load_autoloader(): bool
{
    $autoloader = INVOICEFORGE_PLUGIN_DIR . 'vendor/autoload.php';
    
    if (!file_exists($autoloader)) {
        return false;
    }
    
    require_once $autoloader;
    return true;
}

/**
 * Display admin notice for missing autoloader
 *
 * @return void
 */
function invoiceforge_autoloader_notice(): void
{
    $message = esc_html__(
        'InvoiceForge requires Composer dependencies. Please run "composer install" in the plugin directory.',
        'invoiceforge'
    );
    
    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        $message
    );
}

/**
 * Initialize the plugin
 *
 * @return void
 */
function invoiceforge_init(): void
{
    // Check PHP version
    if (!invoiceforge_check_php_version()) {
        add_action('admin_notices', 'invoiceforge_php_version_notice');
        return;
    }
    
    // Check WordPress version
    if (!invoiceforge_check_wp_version()) {
        add_action('admin_notices', 'invoiceforge_wp_version_notice');
        return;
    }
    
    // Load autoloader
    if (!invoiceforge_load_autoloader()) {
        add_action('admin_notices', 'invoiceforge_autoloader_notice');
        return;
    }
    
    // Boot the plugin
    \InvoiceForge\Core\Plugin::getInstance()->boot();
}

/**
 * Run activation hook
 *
 * @return void
 */
function invoiceforge_activate(): void
{
    // Check PHP version before activation
    if (!invoiceforge_check_php_version()) {
        deactivate_plugins(INVOICEFORGE_PLUGIN_BASENAME);
        wp_die(
            sprintf(
                /* translators: %s: Required PHP version */
                esc_html__('InvoiceForge requires PHP %s or higher.', 'invoiceforge'),
                INVOICEFORGE_MIN_PHP_VERSION
            ),
            esc_html__('Plugin Activation Error', 'invoiceforge'),
            ['back_link' => true]
        );
    }
    
    // Load autoloader for activation
    if (!invoiceforge_load_autoloader()) {
        deactivate_plugins(INVOICEFORGE_PLUGIN_BASENAME);
        wp_die(
            esc_html__('InvoiceForge requires Composer dependencies. Please run "composer install" first.', 'invoiceforge'),
            esc_html__('Plugin Activation Error', 'invoiceforge'),
            ['back_link' => true]
        );
    }
    
    \InvoiceForge\Core\Activator::activate();
}

/**
 * Run deactivation hook
 *
 * @return void
 */
function invoiceforge_deactivate(): void
{
    if (invoiceforge_load_autoloader()) {
        \InvoiceForge\Core\Deactivator::deactivate();
    }
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'invoiceforge_activate');
register_deactivation_hook(__FILE__, 'invoiceforge_deactivate');

// Initialize the plugin
add_action('plugins_loaded', 'invoiceforge_init');
