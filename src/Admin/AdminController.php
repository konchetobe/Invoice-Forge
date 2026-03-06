<?php
/**
 * Admin Controller
 *
 * Handles admin menu registration and settings.
 * Updated to use custom admin pages instead of WordPress CPT screens.
 *
 * @package    InvoiceForge
 * @subpackage Admin
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Admin;

use InvoiceForge\Admin\Pages\InvoicesPage;
use InvoiceForge\Admin\Pages\ClientsPage;
use InvoiceForge\Admin\Pages\SettingsPage;
use InvoiceForge\Security\Nonce;
use InvoiceForge\Security\Sanitizer;
use InvoiceForge\Security\Validator;
use InvoiceForge\Security\Capabilities;
use InvoiceForge\Security\Encryption;
use InvoiceForge\Utilities\Logger;
use InvoiceForge\PostTypes\InvoicePostType;
use InvoiceForge\PostTypes\ClientPostType;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin controller class.
 *
 * Manages the admin menu structure and page rendering.
 *
 * @since 1.0.0
 */
class AdminController
{
    /**
     * Menu slug prefix.
     *
     * @since 1.0.0
     * @var string
     */
    public const MENU_SLUG = 'invoiceforge';

    /**
     * Nonce handler.
     *
     * @since 1.0.0
     * @var Nonce
     */
    private Nonce $nonce;

    /**
     * Sanitizer instance.
     *
     * @since 1.0.0
     * @var Sanitizer
     */
    private Sanitizer $sanitizer;

    /**
     * Validator instance.
     *
     * @since 1.0.0
     * @var Validator
     */
    private Validator $validator;

    /**
     * Capabilities handler.
     *
     * @since 1.0.0
     * @var Capabilities
     */
    private Capabilities $capabilities;

    /**
     * Encryption handler.
     *
     * @since 1.0.0
     * @var Encryption
     */
    private Encryption $encryption;

    /**
     * Logger instance.
     *
     * @since 1.0.0
     * @var Logger
     */
    private Logger $logger;

    /**
     * Settings page instance.
     *
     * @since 1.0.0
     * @var SettingsPage|null
     */
    private ?SettingsPage $settingsPage = null;

    /**
     * Invoices page instance.
     *
     * @since 1.0.0
     * @var InvoicesPage|null
     */
    private ?InvoicesPage $invoicesPage = null;

    /**
     * Clients page instance.
     *
     * @since 1.0.0
     * @var ClientsPage|null
     */
    private ?ClientsPage $clientsPage = null;

    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Nonce        $nonce        Nonce handler.
     * @param Sanitizer    $sanitizer    Sanitizer instance.
     * @param Validator    $validator    Validator instance.
     * @param Capabilities $capabilities Capabilities handler.
     * @param Encryption   $encryption   Encryption handler.
     * @param Logger       $logger       Logger instance.
     */
    public function __construct(
        Nonce $nonce,
        Sanitizer $sanitizer,
        Validator $validator,
        Capabilities $capabilities,
        Encryption $encryption,
        Logger $logger
    ) {
        $this->nonce = $nonce;
        $this->sanitizer = $sanitizer;
        $this->validator = $validator;
        $this->capabilities = $capabilities;
        $this->encryption = $encryption;
        $this->logger = $logger;
    }

    /**
     * Register admin menus.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function registerMenus(): void
    {
        $capability = $this->capabilities->getMenuCapability();

        // Main menu
        add_menu_page(
            __('InvoiceForge', 'invoiceforge'),
            __('InvoiceForge', 'invoiceforge'),
            $capability,
            self::MENU_SLUG,
            [$this, 'renderDashboard'],
            'dashicons-media-document',
            30
        );

        // Dashboard submenu (same as main)
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'invoiceforge'),
            __('Dashboard', 'invoiceforge'),
            $capability,
            self::MENU_SLUG,
            [$this, 'renderDashboard']
        );

        // Custom Invoices page (replaces CPT screen)
        add_submenu_page(
            self::MENU_SLUG,
            __('Invoices', 'invoiceforge'),
            __('Invoices', 'invoiceforge'),
            $capability,
            self::MENU_SLUG . '-invoices',
            [$this, 'renderInvoices']
        );

        // Custom Clients page (replaces CPT screen)
        add_submenu_page(
            self::MENU_SLUG,
            __('Clients', 'invoiceforge'),
            __('Clients', 'invoiceforge'),
            $capability,
            self::MENU_SLUG . '-clients',
            [$this, 'renderClients']
        );

        // Settings submenu
        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'invoiceforge'),
            __('Settings', 'invoiceforge'),
            'manage_options',
            self::MENU_SLUG . '-settings',
            [$this, 'renderSettings']
        );

        // Hide CPT menus from sidebar (they're still accessible directly)
        remove_submenu_page('edit.php?post_type=' . InvoicePostType::POST_TYPE, 'edit.php?post_type=' . InvoicePostType::POST_TYPE);
        remove_submenu_page('edit.php?post_type=' . ClientPostType::POST_TYPE, 'edit.php?post_type=' . ClientPostType::POST_TYPE);
    }

    /**
     * Register settings.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function registerSettings(): void
    {
        $this->getSettingsPage()->register();
    }

    /**
     * Get the settings page instance.
     *
     * @since 1.0.0
     *
     * @return SettingsPage The settings page instance.
     */
    private function getSettingsPage(): SettingsPage
    {
        if ($this->settingsPage === null) {
            $this->settingsPage = new SettingsPage(
                $this->nonce,
                $this->sanitizer,
                $this->validator,
                $this->capabilities,
                $this->encryption
            );
        }

        return $this->settingsPage;
    }

    /**
     * Get the invoices page instance.
     *
     * @since 1.0.0
     *
     * @return InvoicesPage The invoices page instance.
     */
    private function getInvoicesPage(): InvoicesPage
    {
        if ($this->invoicesPage === null) {
            $this->invoicesPage = new InvoicesPage();
        }

        return $this->invoicesPage;
    }

    /**
     * Get the clients page instance.
     *
     * @since 1.0.0
     *
     * @return ClientsPage The clients page instance.
     */
    private function getClientsPage(): ClientsPage
    {
        if ($this->clientsPage === null) {
            $this->clientsPage = new ClientsPage();
        }

        return $this->clientsPage;
    }

    /**
     * Render the dashboard page.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function renderDashboard(): void
    {
        // Check capabilities
        if (!$this->capabilities->canEditInvoices()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'invoiceforge'));
        }

        // Get statistics
        $stats = $this->getDashboardStats();

        // Load the dashboard template
        include INVOICEFORGE_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Render the invoices page.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function renderInvoices(): void
    {
        // Check capabilities
        if (!$this->capabilities->canEditInvoices()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'invoiceforge'));
        }

        $this->getInvoicesPage()->render();
    }

    /**
     * Render the clients page.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function renderClients(): void
    {
        // Check capabilities
        if (!$this->capabilities->canEditInvoices()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'invoiceforge'));
        }

        $this->getClientsPage()->render();
    }

    /**
     * Render the settings page.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function renderSettings(): void
    {
        // Check capabilities
        $this->capabilities->checkSettingsOrDie();

        // Get settings page instance
        $settingsPage = $this->getSettingsPage();

        // Load the settings template
        include INVOICEFORGE_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * Get dashboard statistics.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> Dashboard statistics.
     */
    private function getDashboardStats(): array
    {
        global $wpdb;

        // Get invoice counts by status
        $invoice_counts = [];
        foreach (InvoicePostType::STATUSES as $status => $label) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type = %s
                    AND p.post_status = 'publish'
                    AND pm.meta_key = '_invoice_status'
                    AND pm.meta_value = %s",
                    InvoicePostType::POST_TYPE,
                    $status
                )
            );
            $invoice_counts[$status] = (int) $count;
        }

        // Get total invoice count
        $total_invoices = array_sum($invoice_counts);

        // Get total clients
        $total_clients = wp_count_posts(ClientPostType::POST_TYPE)->publish ?? 0;

        // Get total revenue (paid invoices)
        $total_revenue = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(pm_amount.meta_value), 0) FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id
                WHERE p.post_type = %s
                AND p.post_status = 'publish'
                AND pm_status.meta_key = '_invoice_status'
                AND pm_status.meta_value = 'paid'
                AND pm_amount.meta_key = '_invoice_total_amount'",
                InvoicePostType::POST_TYPE
            )
        );

        // Get outstanding amount
        $outstanding = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(pm_amount.meta_value), 0) FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
                INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id
                WHERE p.post_type = %s
                AND p.post_status = 'publish'
                AND pm_status.meta_key = '_invoice_status'
                AND pm_status.meta_value IN ('sent', 'overdue')
                AND pm_amount.meta_key = '_invoice_total_amount'",
                InvoicePostType::POST_TYPE
            )
        );

        // Get recent invoices
        $recent_invoices = get_posts([
            'post_type'      => InvoicePostType::POST_TYPE,
            'posts_per_page' => 5,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        // Get recent clients
        $recent_clients = get_posts([
            'post_type'      => ClientPostType::POST_TYPE,
            'posts_per_page' => 5,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        return [
            'invoice_counts'  => $invoice_counts,
            'total_invoices'  => $total_invoices,
            'total_clients'   => (int) $total_clients,
            'total_revenue'   => $total_revenue,
            'outstanding'     => $outstanding,
            'recent_invoices' => $recent_invoices,
            'recent_clients'  => $recent_clients,
        ];
    }

    /**
     * Format currency for display.
     *
     * @since 1.0.0
     *
     * @param float  $amount   The amount.
     * @param string $currency The currency code.
     * @return string The formatted amount.
     */
    public function formatCurrency(float $amount, string $currency = 'USD'): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C$',
            'AUD' => 'A$',
        ];

        $symbol = $symbols[$currency] ?? $currency . ' ';

        return $symbol . number_format($amount, 2);
    }

    /**
     * Get the menu slug.
     *
     * @since 1.0.0
     *
     * @return string The menu slug.
     */
    public function getMenuSlug(): string
    {
        return self::MENU_SLUG;
    }
}
