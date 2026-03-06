<?php
/**
 * Client List Template
 * Modern custom list view for clients
 *
 * @package InvoiceForge
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/** @var array $clients */
/** @var array $countries */

$clientsPage = new \InvoiceForge\Admin\Pages\ClientsPage();
?>
<div class="invoiceforge-wrap">
    <!-- Page Header -->
    <div class="invoiceforge-page-header">
        <h1 class="invoiceforge-page-title">
            <?php esc_html_e('Clients', 'invoiceforge'); ?>
            <span>(<?php echo esc_html(count($clients)); ?>)</span>
        </h1>
        <div class="invoiceforge-page-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-clients&action=new')); ?>" class="invoiceforge-btn invoiceforge-btn-primary">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e('New Client', 'invoiceforge'); ?>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="invoiceforge-filters">
        <div class="invoiceforge-search">
            <span class="invoiceforge-search-icon dashicons dashicons-search"></span>
            <input type="text" class="invoiceforge-search-input" placeholder="<?php esc_attr_e('Search clients...', 'invoiceforge'); ?>">
        </div>
    </div>

    <!-- Clients Table -->
    <div class="invoiceforge-card">
        <div class="invoiceforge-table-wrap">
            <table class="invoiceforge-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'invoiceforge'); ?></th>
                        <th><?php esc_html_e('Company', 'invoiceforge'); ?></th>
                        <th><?php esc_html_e('Email', 'invoiceforge'); ?></th>
                        <th><?php esc_html_e('Phone', 'invoiceforge'); ?></th>
                        <th><?php esc_html_e('Location', 'invoiceforge'); ?></th>
                        <th><?php esc_html_e('Invoices', 'invoiceforge'); ?></th>
                        <th><?php esc_html_e('Actions', 'invoiceforge'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)) : ?>
                        <tr>
                            <td colspan="7" class="invoiceforge-table-empty">
                                <div class="invoiceforge-empty-state">
                                    <div class="invoiceforge-empty-icon">
                                        <span class="dashicons dashicons-groups"></span>
                                    </div>
                                    <h3 class="invoiceforge-empty-title"><?php esc_html_e('No clients yet', 'invoiceforge'); ?></h3>
                                    <p class="invoiceforge-empty-text"><?php esc_html_e('Add your first client to start creating invoices.', 'invoiceforge'); ?></p>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-clients&action=new')); ?>" class="invoiceforge-btn invoiceforge-btn-primary">
                                        <?php esc_html_e('Add Client', 'invoiceforge'); ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($clients as $client) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-clients&action=edit&client_id=' . $client['id'])); ?>" class="invoiceforge-table-link">
                                        <?php 
                                        $name = trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''));
                                        echo esc_html($name ?: $client['title']);
                                        ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo esc_html($client['company'] ?: '—'); ?>
                                </td>
                                <td>
                                    <?php if (!empty($client['email'])) : ?>
                                        <a href="mailto:<?php echo esc_attr($client['email']); ?>">
                                            <?php echo esc_html($client['email']); ?>
                                        </a>
                                    <?php else : ?>
                                        <span style="color: var(--if-gray-400);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html($client['phone'] ?: '—'); ?>
                                </td>
                                <td>
                                    <?php 
                                    $location = $clientsPage->formatAddress($client);
                                    echo esc_html($location ?: '—');
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($client['invoice_count']) && $client['invoice_count'] > 0) : ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-invoices&client=' . $client['id'])); ?>">
                                            <?php echo esc_html($client['invoice_count']); ?>
                                        </a>
                                    <?php else : ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="invoiceforge-table-actions">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=invoiceforge-clients&action=edit&client_id=' . $client['id'])); ?>" class="invoiceforge-btn invoiceforge-btn-sm invoiceforge-btn-secondary" title="<?php esc_attr_e('Edit', 'invoiceforge'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                        <button type="button" class="invoiceforge-btn invoiceforge-btn-sm invoiceforge-btn-secondary invoiceforge-delete-client" data-id="<?php echo esc_attr($client['id']); ?>" title="<?php esc_attr_e('Delete', 'invoiceforge'); ?>">
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
