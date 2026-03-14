<?php
/**
 * Invoice List Template
 * Modern custom list view for invoices
 *
 * @package InvoiceForge
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use InvoiceForge\Admin\AdminController;

/** @var array $invoices */
/** @var array $statuses */
/** @var array $status_counts */
/** @var array $source_counts */
/** @var string $active_source */

$total_count   = array_sum($status_counts);
$source_counts = $source_counts ?? ['all' => $total_count, 'custom' => 0, 'woocommerce' => 0];
$active_source = $active_source ?? 'all';
$invoicesPage  = new \InvoiceForge\Admin\Pages\InvoicesPage();
?>
<div class="invoiceforge-wrap">
    <!-- Page Header -->
    <div class="invoiceforge-page-header">
        <h1 class="invoiceforge-page-title">
            <?php esc_html_e('Invoices', 'invoiceforge'); ?>
            <span>(<?php echo esc_html($total_count); ?>)</span>
        </h1>
        <div class="invoiceforge-page-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-invoices&action=new')); ?>" class="invoiceforge-btn invoiceforge-btn-primary">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e('New Invoice', 'invoiceforge'); ?>
            </a>
        </div>
    </div>

    <!-- Source Tabs (All / Custom / WooCommerce) -->
    <nav class="invoiceforge-source-tabs" style="margin-bottom:12px;border-bottom:2px solid var(--if-gray-200);display:flex;gap:0;">
        <?php
        $source_tab_items = [
            'all'         => __('All Invoices', 'invoiceforge'),
            'custom'      => __('Custom Invoices', 'invoiceforge'),
            'woocommerce' => __('WooCommerce Orders', 'invoiceforge'),
        ];
        foreach ($source_tab_items as $src_key => $src_label) :
            $src_url   = admin_url('admin.php?page=invoiceforge-invoices&source=' . $src_key);
            $is_active = ($active_source === $src_key);
        ?>
            <a href="<?php echo esc_url($src_url); ?>"
               style="padding:8px 18px;text-decoration:none;font-weight:<?php echo $is_active ? '600' : '400'; ?>;
                      color:<?php echo $is_active ? 'var(--if-primary)' : 'var(--if-gray-600)'; ?>;
                      border-bottom:<?php echo $is_active ? '2px solid var(--if-primary)' : '2px solid transparent'; ?>;
                      margin-bottom:-2px;display:inline-flex;align-items:center;gap:6px;">
                <?php echo esc_html($src_label); ?>
                <span style="background:var(--if-gray-100);border-radius:12px;padding:1px 8px;font-size:12px;">
                    <?php echo esc_html($source_counts[$src_key] ?? 0); ?>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Status Filters -->
    <div class="invoiceforge-filters">
        <div class="invoiceforge-search">
            <span class="invoiceforge-search-icon dashicons dashicons-search"></span>
            <input type="text" class="invoiceforge-search-input" placeholder="<?php esc_attr_e('Search invoices...', 'invoiceforge'); ?>">
        </div>
        <div class="invoiceforge-filter-tabs">
            <button type="button" class="invoiceforge-filter-tab active" data-status="all">
                <?php esc_html_e('All', 'invoiceforge'); ?>
                <span class="count"><?php echo esc_html($total_count); ?></span>
            </button>
            <?php foreach ($statuses as $status_key => $status_label) : ?>
                <button type="button" class="invoiceforge-filter-tab" data-status="<?php echo esc_attr($status_key); ?>">
                    <?php echo esc_html($status_label); ?>
                    <span class="count"><?php echo esc_html($status_counts[$status_key] ?? 0); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="invoiceforge-card">
        <div class="invoiceforge-table-wrap">
            <table class="invoiceforge-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Invoice #', 'invoiceforge'); ?></th>
                        <th><?php esc_html_e('Client', 'invoiceforge'); ?></th>
                        <th><?php esc_html_e('Date', 'invoiceforge'); ?></th>
                        <th><?php esc_html_e('Due Date', 'invoiceforge'); ?></th>
                        <th><?php esc_html_e('Amount', 'invoiceforge'); ?></th>
                        <th><?php esc_html_e('Status', 'invoiceforge'); ?></th>
                        <th><?php esc_html_e('Actions', 'invoiceforge'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)) : ?>
                        <tr>
                            <td colspan="7" class="invoiceforge-table-empty">
                                <div class="invoiceforge-empty-state">
                                    <div class="invoiceforge-empty-icon">
                                        <span class="dashicons dashicons-media-document"></span>
                                    </div>
                                    <h3 class="invoiceforge-empty-title"><?php esc_html_e('No invoices yet', 'invoiceforge'); ?></h3>
                                    <p class="invoiceforge-empty-text"><?php esc_html_e('Create your first invoice to get started.', 'invoiceforge'); ?></p>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-invoices&action=new')); ?>" class="invoiceforge-btn invoiceforge-btn-primary">
                                        <?php esc_html_e('Create Invoice', 'invoiceforge'); ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($invoices as $invoice) : ?>
                            <tr data-status="<?php echo esc_attr($invoice['status']); ?>">
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-invoices&action=edit&invoice_id=' . $invoice['id'])); ?>" class="invoiceforge-table-link">
                                        <?php echo esc_html($invoice['number'] ?: '#' . $invoice['id']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($invoice['client_id']) : ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-clients&action=edit&client_id=' . $invoice['client_id'])); ?>" class="invoiceforge-table-link">
                                            <?php echo esc_html($invoice['client_name']); ?>
                                        </a>
                                    <?php else : ?>
                                        <span style="color: var(--if-gray-400);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $invoice['date'] ? esc_html(date_i18n(get_option('date_format'), strtotime($invoice['date']))) : '—'; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($invoice['due_date']) {
                                        $is_overdue = strtotime($invoice['due_date']) < current_time('timestamp') && !in_array($invoice['status'], ['paid', 'cancelled'], true);
                                        echo '<span style="' . ($is_overdue ? 'color: var(--if-error);' : '') . '">';
                                        echo esc_html(date_i18n(get_option('date_format'), strtotime($invoice['due_date'])));
                                        echo '</span>';
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="invoiceforge-amount">
                                        <?php echo esc_html($invoicesPage->formatCurrency($invoice['total_amount'], $invoice['currency'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="invoiceforge-status invoiceforge-status-<?php echo esc_attr($invoice['status']); ?>">
                                        <?php echo esc_html($invoicesPage->getStatusLabel($invoice['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="invoiceforge-table-actions">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-invoices&action=edit&invoice_id=' . $invoice['id'])); ?>" class="invoiceforge-btn invoiceforge-btn-sm invoiceforge-btn-secondary" title="<?php esc_attr_e('Edit', 'invoiceforge'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                        <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=invoiceforge_download_pdf&invoice_id=' . $invoice['id'] . '&nonce=' . wp_create_nonce('invoiceforge_ajax'))); ?>" class="invoiceforge-btn invoiceforge-btn-sm invoiceforge-btn-secondary" target="_blank" title="<?php esc_attr_e('Download PDF', 'invoiceforge'); ?>">
                                            <span class="dashicons dashicons-pdf"></span>
                                        </a>
                                        <button type="button" class="invoiceforge-btn invoiceforge-btn-sm invoiceforge-btn-secondary invoiceforge-delete-invoice" data-id="<?php echo esc_attr($invoice['id']); ?>" title="<?php esc_attr_e('Delete', 'invoiceforge'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
