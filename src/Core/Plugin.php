<?php
/**
 * Main Plugin Class
 *
 * The core plugin class that orchestrates all plugin functionality.
 * Implements the singleton pattern to ensure only one instance exists.
 *
 * @package    InvoiceForge
 * @subpackage Core
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Core;

use InvoiceForge\Admin\AdminController;
use InvoiceForge\Admin\Assets;
use InvoiceForge\Ajax\InvoiceAjaxHandler;
use InvoiceForge\Ajax\ClientAjaxHandler;
use InvoiceForge\PostTypes\InvoicePostType;
use InvoiceForge\PostTypes\ClientPostType;
use InvoiceForge\Repositories\LineItemRepository;
use InvoiceForge\Repositories\TaxRateRepository;
use InvoiceForge\Services\NumberingService;
use InvoiceForge\Services\TaxService;
use InvoiceForge\Security\Nonce;
use InvoiceForge\Security\Capabilities;
use InvoiceForge\Security\Sanitizer;
use InvoiceForge\Security\Validator;
use InvoiceForge\Security\Encryption;
use InvoiceForge\Utilities\Logger;
use InvoiceForge\Integrations\WooCommerce\WooCommerceIntegration;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin orchestrator class.
 *
 * This class is responsible for:
 * - Maintaining the singleton instance
 * - Registering all hooks via the Loader
 * - Managing the dependency injection container
 * - Coordinating all plugin components
 *
 * @since 1.0.0
 */
final class Plugin
{
    /**
     * The single instance of the plugin.
     *
     * @since 1.0.0
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * The dependency injection container.
     *
     * @since 1.0.0
     * @var Container
     */
    private Container $container;

    /**
     * The hook loader.
     *
     * @since 1.0.0
     * @var Loader
     */
    private Loader $loader;

    /**
     * Whether the plugin has been booted.
     *
     * @since 1.0.0
     * @var bool
     */
    private bool $booted = false;

    /**
     * Get the singleton instance of the plugin.
     *
     * @since 1.0.0
     *
     * @return Plugin The plugin instance.
     */
    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->container = new Container();
        $this->loader = new Loader();
    }

    /**
     * Prevent cloning of the instance.
     *
     * @since 1.0.0
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserializing of the instance.
     *
     * @since 1.0.0
     *
     * @throws \Exception Always throws exception.
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Boot the plugin.
     *
     * This method initializes all plugin components and registers hooks.
     * It should only be called once during the plugin lifecycle.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->registerServices();
        $this->registerHooks();

        // Initialize GitHub-based update checker
        $updateChecker = new UpdateChecker();
        $updateChecker->init();

        // Initialize WooCommerce integration (registers order status hooks)
        $wooIntegration = new WooCommerceIntegration(
            $this->container->resolve('logger'),
            $this->container->resolve('line_item_repo'),
            $this->container->resolve('numbering')
        );
        $wooIntegration->register();

        $this->loader->run();

        $this->booted = true;

        /**
         * Fires after the plugin has been fully booted.
         *
         * @since 1.0.0
         *
         * @param Plugin $plugin The plugin instance.
         */
        do_action('invoiceforge_booted', $this);
    }

    /**
     * Register services in the container.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function registerServices(): void
    {
        // Core services
        $this->container->register('logger', fn(): Logger => new Logger());

        // Security services
        $this->container->register('nonce', fn(): Nonce => new Nonce());
        $this->container->register('capabilities', fn(): Capabilities => new Capabilities());
        $this->container->register('sanitizer', fn(): Sanitizer => new Sanitizer());
        $this->container->register('validator', fn(): Validator => new Validator());
        $this->container->register('encryption', fn(): Encryption => new Encryption());

        // Business services
        $this->container->register('numbering', fn(): NumberingService => new NumberingService(
            $this->container->resolve('logger')
        ));

        // Repositories
        $this->container->register('line_item_repo', fn(): LineItemRepository => new LineItemRepository());
        $this->container->register('tax_rate_repo', fn(): TaxRateRepository => new TaxRateRepository());

        // Tax service
        $this->container->register('tax_service', fn(): TaxService => new TaxService(
            $this->container->resolve('tax_rate_repo')
        ));

        // Post types
        $this->container->register('invoice_post_type', fn(): InvoicePostType => new InvoicePostType(
            $this->container->resolve('nonce'),
            $this->container->resolve('sanitizer'),
            $this->container->resolve('numbering')
        ));

        $this->container->register('client_post_type', fn(): ClientPostType => new ClientPostType(
            $this->container->resolve('nonce'),
            $this->container->resolve('sanitizer')
        ));

        // Admin
        $this->container->register('admin_assets', fn(): Assets => new Assets());
        $this->container->register('admin_controller', fn(): AdminController => new AdminController(
            $this->container->resolve('nonce'),
            $this->container->resolve('sanitizer'),
            $this->container->resolve('validator'),
            $this->container->resolve('capabilities'),
            $this->container->resolve('encryption'),
            $this->container->resolve('logger')
        ));

        // AJAX Handlers
        $this->container->register('invoice_ajax', fn(): InvoiceAjaxHandler => new InvoiceAjaxHandler(
            $this->container->resolve('nonce'),
            $this->container->resolve('sanitizer'),
            $this->container->resolve('validator'),
            $this->container->resolve('numbering'),
            $this->container->resolve('line_item_repo'),
            $this->container->resolve('tax_service')
        ));

        $this->container->register('client_ajax', fn(): ClientAjaxHandler => new ClientAjaxHandler(
            $this->container->resolve('nonce'),
            $this->container->resolve('sanitizer'),
            $this->container->resolve('validator')
        ));
    }

    /**
     * Register all hooks with the loader.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function registerHooks(): void
    {
        // Load text domain
        $this->loader->addAction('init', $this, 'loadTextDomain');

        // Register post types
        /** @var InvoicePostType $invoicePostType */
        $invoicePostType = $this->container->resolve('invoice_post_type');
        $this->loader->addAction('init', $invoicePostType, 'register');
        $this->loader->addAction('add_meta_boxes', $invoicePostType, 'addMetaBoxes');
        $this->loader->addAction('save_post_if_invoice', $invoicePostType, 'saveMetaData');
        $this->loader->addFilter('manage_if_invoice_posts_columns', $invoicePostType, 'addAdminColumns');
        $this->loader->addAction('manage_if_invoice_posts_custom_column', $invoicePostType, 'renderAdminColumn', 10, 2);
        $this->loader->addFilter('manage_edit-if_invoice_sortable_columns', $invoicePostType, 'addSortableColumns');

        /** @var ClientPostType $clientPostType */
        $clientPostType = $this->container->resolve('client_post_type');
        $this->loader->addAction('init', $clientPostType, 'register');
        $this->loader->addAction('add_meta_boxes', $clientPostType, 'addMetaBoxes');
        $this->loader->addAction('save_post_if_client', $clientPostType, 'saveMetaData');
        $this->loader->addFilter('manage_if_client_posts_columns', $clientPostType, 'addAdminColumns');
        $this->loader->addAction('manage_if_client_posts_custom_column', $clientPostType, 'renderAdminColumn', 10, 2);

        // Admin hooks (only in admin context)
        if (is_admin()) {
            /** @var Assets $assets */
            $assets = $this->container->resolve('admin_assets');
            $this->loader->addAction('admin_enqueue_scripts', $assets, 'enqueueStyles');
            $this->loader->addAction('admin_enqueue_scripts', $assets, 'enqueueScripts');

            /** @var AdminController $adminController */
            $adminController = $this->container->resolve('admin_controller');
            $this->loader->addAction('admin_menu', $adminController, 'registerMenus');
            $this->loader->addAction('admin_init', $adminController, 'registerSettings');

            // AJAX handlers (register immediately as they need wp_ajax hooks)
            /** @var InvoiceAjaxHandler $invoiceAjax */
            $invoiceAjax = $this->container->resolve('invoice_ajax');
            $invoiceAjax->register();

            /** @var ClientAjaxHandler $clientAjax */
            $clientAjax = $this->container->resolve('client_ajax');
            $clientAjax->register();
        }
    }

    /**
     * Load the plugin text domain for translations.
     * Applies custom language setting if configured.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function loadTextDomain(): void
    {
        // Check for custom language setting
        $settings = get_option('invoiceforge_settings', []);
        $custom_language = $settings['language'] ?? '';

        if (!empty($custom_language) && function_exists('switch_to_locale')) {
            // Switch to custom locale for this plugin only
            add_filter('plugin_locale', function ($locale, $domain) use ($custom_language) {
                if ($domain === 'invoiceforge') {
                    return $custom_language;
                }
                return $locale;
            }, 10, 2);
        }

        load_plugin_textdomain(
            'invoiceforge',
            false,
            dirname(INVOICEFORGE_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Get the container instance.
     *
     * @since 1.0.0
     *
     * @return Container The container instance.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the loader instance.
     *
     * @since 1.0.0
     *
     * @return Loader The loader instance.
     */
    public function getLoader(): Loader
    {
        return $this->loader;
    }

    /**
     * Get the plugin version.
     *
     * @since 1.0.0
     *
     * @return string The plugin version.
     */
    public function getVersion(): string
    {
        return INVOICEFORGE_VERSION;
    }

    /**
     * Resolve a service from the container.
     *
     * @since 1.0.0
     *
     * @param string $id The service identifier.
     * @return mixed The resolved service.
     */
    public function resolve(string $id): mixed
    {
        return $this->container->resolve($id);
    }
}
