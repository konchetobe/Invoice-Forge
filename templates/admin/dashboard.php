<?php
/**
 * Dashboard Template
 * Modern dashboard with stats, quick actions, and recent activity
 *
 * @package InvoiceForge
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use InvoiceForge\PostTypes\InvoicePostType;
use InvoiceForge\PostTypes\ClientPostType;

/** @var array $stats */
?>
<div class="invoiceforge-wrap">
    <!-- Page Header -->
    <div class="invoiceforge-page-header">
        <h1 class="invoiceforge-page-title">
            <?php esc_html_e('Dashboard', 'invoiceforge'); ?>
        </h1>
        <div class="invoiceforge-page-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-invoices&action=new')); ?>" class="invoiceforge-btn invoiceforge-btn-primary">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e('New Invoice', 'invoiceforge'); ?>
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="invoiceforge-stats-grid">
        <div class="invoiceforge-stat-card stat-primary">
            <div class="invoiceforge-stat-icon">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div class="invoiceforge-stat-label"><?php esc_html_e('Total Revenue', 'invoiceforge'); ?></div>
            <div class="invoiceforge-stat-value">
                <?php echo esc_html($this->formatCurrency($stats['total_revenue'])); ?>
            </div>
        </div>

        <div class="invoiceforge-stat-card">
            <div class="invoiceforge-stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="invoiceforge-stat-label"><?php esc_html_e('Outstanding', 'invoiceforge'); ?></div>
            <div class="invoiceforge-stat-value">
                <?php echo esc_html($this->formatCurrency($stats['outstanding'])); ?>
            </div>
        </div>

        <div class="invoiceforge-stat-card">
            <div class="invoiceforge-stat-icon">
                <span class="dashicons dashicons-media-document"></span>
            </div>
            <div class="invoiceforge-stat-label"><?php esc_html_e('Total Invoices', 'invoiceforge'); ?></div>
            <div class="invoiceforge-stat-value">
                <?php echo esc_html($stats['total_invoices']); ?>
            </div>
        </div>

        <div class="invoiceforge-stat-card">
            <div class="invoiceforge-stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="invoiceforge-stat-label"><?php esc_html_e('Total Clients', 'invoiceforge'); ?></div>
            <div class="invoiceforge-stat-value">
                <?php echo esc_html($stats['total_clients']); ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="invoiceforge-card" style="margin-bottom: var(--if-space-6);">
        <div class="invoiceforge-card-header">
            <h3 class="invoiceforge-card-title"><?php esc_html_e('Quick Actions', 'invoiceforge'); ?></h3>
        </div>
        <div class="invoiceforge-card-body">
            <div class="invoiceforge-quick-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-invoices&action=new')); ?>" class="invoiceforge-quick-action">
                    <span class="dashicons dashicons-media-document"></span>
                    <?php esc_html_e('Create Invoice', 'invoiceforge'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-clients&action=new')); ?>" class="invoiceforge-quick-action">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e('Add Client', 'invoiceforge'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-invoices')); ?>" class="invoiceforge-quick-action">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e('View All Invoices', 'invoiceforge'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-settings')); ?>" class="invoiceforge-quick-action">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('Settings', 'invoiceforge'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Dashboard Grid -->
    <div class="invoiceforge-dashboard-grid">
        <!-- Recent Invoices -->
        <div class="invoiceforge-card">
            <div class="invoiceforge-card-header">
                <h3 class="invoiceforge-card-title"><?php esc_html_e('Recent Invoices', 'invoiceforge'); ?></h3>
                <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-invoices')); ?>" class="invoiceforge-btn invoiceforge-btn-sm invoiceforge-btn-secondary">
                    <?php esc_html_e('View All', 'invoiceforge'); ?>
                </a>
            </div>
            <div class="invoiceforge-card-body" style="padding: 0;">
                <?php if (empty($stats['recent_invoices'])) : ?>
                    <div class="invoiceforge-empty-state" style="padding: var(--if-space-6);">
                        <p><?php esc_html_e('No invoices yet. Create your first invoice to get started!', 'invoiceforge'); ?></p>
                    </div>
                <?php else : ?>
                    <table class="invoiceforge-table">
                        <tbody>
                            <?php foreach ($stats['recent_invoices'] as $invoice) : ?>
                                <?php
                                $invoice_number = get_post_meta($invoice->ID, '_invoice_number', true);
                                $status = get_post_meta($invoice->ID, '_invoice_status', true) ?: 'draft';
                                $amount = (float) get_post_meta($invoice->ID, '_invoice_total_amount', true);
                                $currency = get_post_meta($invoice->ID, '_invoice_currency', true) ?: 'USD';
                                $client_id = get_post_meta($invoice->ID, '_invoice_client_id', true);
                                $client = $client_id ? get_post($client_id) : null;
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-invoices&action=edit&invoice_id=' . $invoice->ID)); ?>" class="invoiceforge-table-link">
                                            <?php echo esc_html($invoice_number ?: '#' . $invoice->ID); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php echo esc_html($client ? $client->post_title : '—'); ?>
                                    </td>
                                    <td>
                                        <span class="invoiceforge-amount">
                                            <?php echo esc_html($this->formatCurrency($amount, $currency)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="invoiceforge-status invoiceforge-status-<?php echo esc_attr($status); ?>">
                                            <?php echo esc_html(InvoicePostType::STATUSES[$status] ?? $status); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Invoice Status Breakdown -->
        <div class="invoiceforge-card">
            <div class="invoiceforge-card-header">
                <h3 class="invoiceforge-card-title"><?php esc_html_e('Invoice Status', 'invoiceforge'); ?></h3>
            </div>
            <div class="invoiceforge-card-body">
                <?php foreach (InvoicePostType::STATUSES as $status_key => $status_label) : ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--if-space-2) 0; border-bottom: 1px solid var(--if-gray-100);">
                        <span class="invoiceforge-status invoiceforge-status-<?php echo esc_attr($status_key); ?>">
                            <?php echo esc_html($status_label); ?>
                        </span>
                        <span style="font-weight: 600; color: var(--if-gray-700);">
                            <?php echo esc_html($stats['invoice_counts'][$status_key] ?? 0); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent Clients -->
    <div class="invoiceforge-card" style="margin-top: var(--if-space-6);">
        <div class="invoiceforge-card-header">
            <h3 class="invoiceforge-card-title"><?php esc_html_e('Recent Clients', 'invoiceforge'); ?></h3>
            <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-clients')); ?>" class="invoiceforge-btn invoiceforge-btn-sm invoiceforge-btn-secondary">
                <?php esc_html_e('View All', 'invoiceforge'); ?>
            </a>
        </div>
        <div class="invoiceforge-card-body" style="padding: 0;">
            <?php if (empty($stats['recent_clients'])) : ?>
                <div class="invoiceforge-empty-state" style="padding: var(--if-space-6);">
                    <p><?php esc_html_e('No clients yet. Add your first client to start invoicing!', 'invoiceforge'); ?></p>
                </div>
            <?php else : ?>
                <table class="invoiceforge-table">
                    <tbody>
                        <?php foreach ($stats['recent_clients'] as $client) : ?>
                            <?php
                            $email = get_post_meta($client->ID, '_client_email', true);
                            $company = get_post_meta($client->ID, '_client_company', true);
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-clients&action=edit&client_id=' . $client->ID)); ?>" class="invoiceforge-table-link">
                                        <?php echo esc_html($client->post_title); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo esc_html($company ?: '—'); ?>
                                </td>
                                <td>
                                    <?php if ($email) : ?>
                                        <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                    <?php else : ?>
                                        <span style="color: var(--if-gray-400);">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
