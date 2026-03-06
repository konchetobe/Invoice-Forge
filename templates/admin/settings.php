<?php
/**
 * Settings Template
 * Modern tabbed settings page
 *
 * @package InvoiceForge
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use InvoiceForge\Admin\Pages\SettingsPage;
use InvoiceForge\Services\NumberingService;

/** @var SettingsPage $settingsPage */

$tabs = $settingsPage->getTabs();
$active_tab = $settingsPage->getActiveTab();
$settings = $settingsPage->getSettings();
$numberingService = new NumberingService(new \InvoiceForge\Utilities\Logger());
?>
<div class="invoiceforge-wrap">
    <!-- Page Header -->
    <div class="invoiceforge-page-header">
        <h1 class="invoiceforge-page-title">
            <?php esc_html_e('Settings', 'invoiceforge'); ?>
        </h1>
    </div>

    <!-- Settings Tabs -->
    <nav class="invoiceforge-settings-nav">
        <?php foreach ($tabs as $tab_slug => $tab_label) : ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-settings&tab=' . $tab_slug)); ?>" 
               class="<?php echo $active_tab === $tab_slug ? 'active' : ''; ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Settings Form -->
    <div class="invoiceforge-card">
        <form method="post" action="options.php" class="invoiceforge-settings-form">
            <?php settings_fields(SettingsPage::SETTINGS_GROUP); ?>

            <div class="invoiceforge-card-body">
                <?php if ($active_tab === 'general') : ?>
                    <?php do_settings_sections('invoiceforge-settings-general'); ?>
                <?php elseif ($active_tab === 'email') : ?>
                    <?php do_settings_sections('invoiceforge-settings-email'); ?>
                    
                    <!-- SMTP Notice -->
                    <div style="background: var(--if-info-light); border-radius: var(--if-radius); padding: var(--if-space-4); margin-top: var(--if-space-4);">
                        <p style="margin: 0; color: var(--if-info);">
                            <span class="dashicons dashicons-info" style="margin-right: var(--if-space-2);"></span>
                            <?php esc_html_e('Email sending functionality will be available in a future update. Configure your SMTP settings now to be ready.', 'invoiceforge'); ?>
                        </p>
                    </div>
                <?php elseif ($active_tab === 'advanced') : ?>
                    <?php do_settings_sections('invoiceforge-settings-advanced'); ?>
                    
                    <!-- Invoice Number Preview -->
                    <div style="background: var(--if-gray-50); border-radius: var(--if-radius); padding: var(--if-space-4); margin-top: var(--if-space-4);">
                        <p style="margin: 0 0 var(--if-space-2);">
                            <strong><?php esc_html_e('Next Invoice Number:', 'invoiceforge'); ?></strong>
                        </p>
                        <code style="font-size: var(--if-font-size-lg); padding: var(--if-space-2) var(--if-space-3); background: var(--if-white); border-radius: var(--if-radius-sm);">
                            <?php echo esc_html($numberingService->preview()); ?>
                        </code>
                    </div>
                <?php endif; ?>
            </div>

            <div class="invoiceforge-card-footer">
                <?php submit_button(__('Save Settings', 'invoiceforge'), 'invoiceforge-btn invoiceforge-btn-primary', 'submit', false); ?>
            </div>
        </form>
    </div>

    <?php if ($active_tab === 'advanced') : ?>
        <!-- Danger Zone -->
        <div class="invoiceforge-danger-zone">
            <h3>
                <span class="dashicons dashicons-warning" style="color: var(--if-error);"></span>
                <?php esc_html_e('Danger Zone', 'invoiceforge'); ?>
            </h3>
            <p style="color: var(--if-gray-600); margin-bottom: var(--if-space-4);">
                <?php esc_html_e('These actions can affect your invoice data. Use with caution.', 'invoiceforge'); ?>
            </p>
            <button type="button" class="invoiceforge-btn invoiceforge-btn-danger" id="reset-invoice-counter" data-confirm="<?php esc_attr_e('Are you sure you want to reset the invoice counter? This cannot be undone.', 'invoiceforge'); ?>">
                <span class="dashicons dashicons-backup"></span>
                <?php esc_html_e('Reset Invoice Counter', 'invoiceforge'); ?>
            </button>
        </div>
    <?php endif; ?>
</div>
