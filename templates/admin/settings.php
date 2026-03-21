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
        <form method="post" action="options.php" enctype="multipart/form-data" class="invoiceforge-settings-form">
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
                    <?php
                    $numbering_config = $numberingService->getNumberingConfig();
                    $format_parts = [];
                    if ($numbering_config['prefix'] !== '') {
                        $format_parts[] = '{PREFIX}';
                    }
                    if ($numbering_config['date_pattern'] !== 'none') {
                        $date_labels = [
                            'Y'   => '{YEAR}',
                            'Ym'  => '{YEAR}{MONTH}',
                            'Y-m' => '{YEAR}-{MONTH}',
                        ];
                        $format_parts[] = $date_labels[$numbering_config['date_pattern']] ?? '{DATE}';
                    }
                    $format_parts[] = '{' . str_repeat('0', $numbering_config['padding']) . '}';
                    if ($numbering_config['suffix'] !== '') {
                        $format_parts[] = '{SUFFIX}';
                    }
                    $format_hint = implode('-', $format_parts);
                    ?>
                    <div style="background: var(--if-gray-50); border-radius: var(--if-radius); padding: var(--if-space-4); margin-top: var(--if-space-4);">
                        <p style="margin: 0 0 var(--if-space-2);">
                            <strong><?php esc_html_e('Next Invoice Number:', 'invoiceforge'); ?></strong>
                        </p>
                        <code style="font-size: var(--if-font-size-lg); padding: var(--if-space-2) var(--if-space-3); background: var(--if-white); border-radius: var(--if-radius-sm);">
                            <?php echo esc_html($numberingService->preview()); ?>
                        </code>
                        <p style="margin: var(--if-space-2) 0 0; font-size: var(--if-font-size-xs); color: var(--if-gray-500);">
                            <?php esc_html_e('Format:', 'invoiceforge'); ?>
                            <code style="font-size: var(--if-font-size-xs);"><?php echo esc_html($format_hint); ?></code>
                        </p>
                    </div>
                <?php elseif ($active_tab === 'integrations') : ?>

                    <?php $woo_available = class_exists('WooCommerce'); ?>
                    <?php if (!$woo_available) : ?>
                        <div style="background:#fff8e1;border:1px solid #ffc107;border-radius:var(--if-radius);padding:var(--if-space-4);margin-bottom:var(--if-space-4);">
                            <p style="margin:0;color:#856404;">
                                <span class="dashicons dashicons-warning" style="margin-right:4px;"></span>
                                <?php esc_html_e('WooCommerce is not installed or activated. Install WooCommerce to use these integration settings.', 'invoiceforge'); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <h3><?php esc_html_e('WooCommerce Integration', 'invoiceforge'); ?></h3>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Integration', 'invoiceforge'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="invoiceforge_settings[woo_enabled]" value="1"
                                        <?php checked(!empty($settings['woo_enabled'])); ?>
                                        <?php disabled(!$woo_available); ?>>
                                    <?php esc_html_e('Automatically generate invoices from WooCommerce orders', 'invoiceforge'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Trigger on Order Status', 'invoiceforge'); ?></th>
                            <td>
                                <?php
                                $all_statuses = $woo_available ? wc_get_order_statuses() : [
                                    'wc-pending'    => __('Pending payment', 'invoiceforge'),
                                    'wc-processing' => __('Processing', 'invoiceforge'),
                                    'wc-completed'  => __('Completed', 'invoiceforge'),
                                ];
                                $selected = $settings['woo_trigger_statuses'] ?? ['wc-completed'];
                                if (!is_array($selected)) {
                                    $selected = ['wc-completed'];
                                }
                                ?>
                                <select name="invoiceforge_settings[woo_trigger_statuses][]" multiple
                                    style="min-height:90px;min-width:220px;" <?php disabled(!$woo_available); ?>>
                                    <?php foreach ($all_statuses as $slug => $label) : ?>
                                        <option value="<?php echo esc_attr($slug); ?>"
                                            <?php echo in_array($slug, $selected, true) ? 'selected' : ''; ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Hold Ctrl/Cmd to select multiple statuses.', 'invoiceforge'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Invoice Number Format', 'invoiceforge'); ?></th>
                            <td>
                                <select name="invoiceforge_settings[woo_invoice_number_format]" <?php disabled(!$woo_available); ?>>
                                    <option value="invoiceforge" <?php selected($settings['woo_invoice_number_format'] ?? 'invoiceforge', 'invoiceforge'); ?>>
                                        <?php esc_html_e('InvoiceForge Sequential (e.g. INV-2026-0001)', 'invoiceforge'); ?>
                                    </option>
                                    <option value="woocommerce" <?php selected($settings['woo_invoice_number_format'] ?? 'invoiceforge', 'woocommerce'); ?>>
                                        <?php esc_html_e('WooCommerce Order Number with Prefix (e.g. ORD-1234)', 'invoiceforge'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr id="invoiceforge-woo-prefix-row">
                            <th scope="row"><?php esc_html_e('Order Invoice Prefix', 'invoiceforge'); ?></th>
                            <td>
                                <input type="text" name="invoiceforge_settings[woo_invoice_prefix]"
                                    value="<?php echo esc_attr($settings['woo_invoice_prefix'] ?? 'ORD'); ?>"
                                    class="regular-text" <?php disabled(!$woo_available); ?>>
                                <p class="description"><?php esc_html_e('Used when "WooCommerce Order Number" format is selected (e.g. ORD-1234).', 'invoiceforge'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Auto-Send Invoice Email', 'invoiceforge'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="invoiceforge_settings[woo_auto_email]" value="1"
                                        <?php checked(!empty($settings['woo_auto_email'])); ?>
                                        <?php disabled(!$woo_available); ?>>
                                    <?php esc_html_e('Automatically email the PDF invoice to the customer when generated', 'invoiceforge'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <script>
                    (function($) {
                        function togglePrefixRow() {
                            var val = $('select[name="invoiceforge_settings[woo_invoice_number_format]"]').val();
                            $('#invoiceforge-woo-prefix-row').toggle(val === 'woocommerce');
                        }
                        $(document).ready(togglePrefixRow);
                        $(document).on('change', 'select[name="invoiceforge_settings[woo_invoice_number_format]"]', togglePrefixRow);
                    })(jQuery);
                    </script>

                <?php elseif ($active_tab === 'template') : ?>

                    <!-- Hidden sentinel to identify this tab on save -->
                    <input type="hidden" name="invoiceforge_settings[_template_tab_marker]" value="1">

                    <?php
                    $tmpl = $settings['template'] ?? [];
                    $tmpl_logo_url   = $tmpl['logo_url'] ?? '';
                    $tmpl_accent     = $tmpl['accent_color'] ?? '#1a2b4a';
                    $tmpl_id_label   = $tmpl['id_no_label'] ?? 'ID No';
                    $tmpl_methods    = $tmpl['payment_methods'] ?? ['Bank transfer', 'Cash'];
                    $tmpl_order      = $tmpl['section_order'] ?? ['header', 'line_items', 'totals', 'bank', 'notes', 'signature'];
                    $tmpl_visibility = $tmpl['section_visibility'] ?? ['signature' => true, 'notes' => true, 'discount_row' => true];
                    $tmpl_sig_fields = $tmpl['signature_fields'] ?? [];
                    $section_labels  = [
                        'header'     => __('Header (Company + Client)', 'invoiceforge'),
                        'line_items' => __('Line Items', 'invoiceforge'),
                        'totals'     => __('Totals', 'invoiceforge'),
                        'bank'       => __('Bank Details', 'invoiceforge'),
                        'notes'      => __('Notes & Terms', 'invoiceforge'),
                        'signature'  => __('Signature', 'invoiceforge'),
                    ];
                    ?>

                    <?php
                    $tmpl_logo_id = $tmpl['logo_id'] ?? 0;
                    $tmpl_logo_preview = $tmpl_logo_id ? wp_get_attachment_image_url((int) $tmpl_logo_id, 'medium') : '';
                    ?>
                    <h3><?php esc_html_e('Logo', 'invoiceforge'); ?></h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Invoice Logo', 'invoiceforge'); ?></th>
                            <td>
                                <div class="invoiceforge-image-upload">
                                    <input type="hidden" id="template_logo_id"
                                           name="invoiceforge_settings[template][logo_id]"
                                           value="<?php echo esc_attr((string) $tmpl_logo_id); ?>">
                                    <div class="invoiceforge-image-preview" id="template_logo_id_preview">
                                        <?php if ($tmpl_logo_preview) : ?>
                                            <img src="<?php echo esc_url($tmpl_logo_preview); ?>" alt="">
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button invoiceforge-upload-image" data-target="template_logo_id">
                                        <?php esc_html_e('Select Image', 'invoiceforge'); ?>
                                    </button>
                                    <button type="button" class="button invoiceforge-remove-image" data-target="template_logo_id"
                                            style="<?php echo $tmpl_logo_id ? '' : 'display:none;'; ?>">
                                        <?php esc_html_e('Remove Image', 'invoiceforge'); ?>
                                    </button>
                                </div>
                                <p class="description"><?php esc_html_e('Select a logo from the media library (JPG, PNG, SVG). Recommended size: 300x100px.', 'invoiceforge'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <h3><?php esc_html_e('Appearance', 'invoiceforge'); ?></h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Accent Color', 'invoiceforge'); ?></th>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="color" id="if-accent-color-picker" value="<?php echo esc_attr($tmpl_accent); ?>"
                                           oninput="document.getElementById('if-accent-color-text').value = this.value">
                                    <input type="text" id="if-accent-color-text"
                                           name="invoiceforge_settings[template][accent_color]"
                                           value="<?php echo esc_attr($tmpl_accent); ?>"
                                           class="regular-text" maxlength="7" placeholder="#1a2b4a"
                                           oninput="if(/^#[0-9a-fA-F]{3,6}$/.test(this.value)){document.getElementById('if-accent-color-picker').value=this.value}">
                                </div>
                                <p class="description"><?php esc_html_e('Hex color used for headings and accents on the invoice PDF.', 'invoiceforge'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('ID No Label', 'invoiceforge'); ?></th>
                            <td>
                                <input type="text" name="invoiceforge_settings[template][id_no_label]"
                                       value="<?php echo esc_attr($tmpl_id_label); ?>"
                                       class="regular-text" placeholder="ID No">
                                <p class="description"><?php esc_html_e('Label used for the company/client ID number field (e.g. EIK, BULSTAT, Reg No).', 'invoiceforge'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <h3><?php esc_html_e('Payment Methods', 'invoiceforge'); ?></h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Available Methods', 'invoiceforge'); ?></th>
                            <td>
                                <div id="if-payment-methods-list">
                                    <?php foreach ($tmpl_methods as $i => $method) : ?>
                                        <div class="if-repeater-row" style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                            <input type="text" name="invoiceforge_settings[template][payment_methods][]"
                                                   value="<?php echo esc_attr($method); ?>" class="regular-text">
                                            <button type="button" class="button if-remove-payment-method"><?php esc_html_e('Remove', 'invoiceforge'); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" id="if-add-payment-method" class="button">
                                    <?php esc_html_e('+ Add Method', 'invoiceforge'); ?>
                                </button>
                                <p class="description"><?php esc_html_e('Payment methods available to select on each invoice.', 'invoiceforge'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <h3><?php esc_html_e('Section Visibility', 'invoiceforge'); ?></h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Visible Sections', 'invoiceforge'); ?></th>
                            <td>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="invoiceforge_settings[template][section_visibility][signature]" value="1"
                                           <?php checked(!empty($tmpl_visibility['signature'])); ?>>
                                    <?php esc_html_e('Signature block', 'invoiceforge'); ?>
                                </label>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="invoiceforge_settings[template][section_visibility][notes]" value="1"
                                           <?php checked(!empty($tmpl_visibility['notes'])); ?>>
                                    <?php esc_html_e('Notes & Terms', 'invoiceforge'); ?>
                                </label>
                                <label style="display:block;margin-bottom:6px;">
                                    <input type="checkbox" name="invoiceforge_settings[template][section_visibility][discount_row]" value="1"
                                           <?php checked(!empty($tmpl_visibility['discount_row'])); ?>>
                                    <?php esc_html_e('Discount row in totals', 'invoiceforge'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <h3><?php esc_html_e('Section Order', 'invoiceforge'); ?></h3>
                    <p class="description" style="margin-bottom:12px;"><?php esc_html_e('Drag sections to reorder them on the invoice PDF.', 'invoiceforge'); ?></p>
                    <ul id="if-section-order-list" class="if-section-order-list">
                        <?php foreach ($tmpl_order as $slug) :
                            if (!isset($section_labels[$slug])) continue; ?>
                            <li class="if-section-item" data-slug="<?php echo esc_attr($slug); ?>">
                                <span class="if-drag-handle" title="<?php esc_attr_e('Drag to reorder', 'invoiceforge'); ?>"></span>
                                <span class="if-section-label"><?php echo esc_html($section_labels[$slug]); ?></span>
                                <input type="hidden" name="invoiceforge_settings[template][section_order][]" value="<?php echo esc_attr($slug); ?>">
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <h3 style="margin-top:24px;"><?php esc_html_e('Signature Fields', 'invoiceforge'); ?></h3>
                    <p class="description" style="margin-bottom:12px;"><?php esc_html_e('Configure the fields that appear in the signature block.', 'invoiceforge'); ?></p>
                    <table class="form-table" role="presentation" style="margin-bottom:12px;">
                        <tr>
                            <th scope="row"><?php esc_html_e('Left Column Title', 'invoiceforge'); ?></th>
                            <td>
                                <input type="text" name="invoiceforge_settings[template][signature_left_title]"
                                       value="<?php echo esc_attr($tmpl['signature_left_title'] ?? ''); ?>"
                                       class="regular-text" placeholder="<?php esc_attr_e('e.g., Issued by', 'invoiceforge'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Right Column Title', 'invoiceforge'); ?></th>
                            <td>
                                <input type="text" name="invoiceforge_settings[template][signature_right_title]"
                                       value="<?php echo esc_attr($tmpl['signature_right_title'] ?? ''); ?>"
                                       class="regular-text" placeholder="<?php esc_attr_e('e.g., Received by', 'invoiceforge'); ?>">
                            </td>
                        </tr>
                    </table>
                    <div id="if-signature-fields-list">
                        <?php foreach ($tmpl_sig_fields as $sig_field) : ?>
                            <div class="if-repeater-row if-sig-field-row" style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                <input type="text" name="invoiceforge_settings[template][signature_fields][][label]"
                                       value="<?php echo esc_attr($sig_field['label'] ?? ''); ?>"
                                       class="regular-text" placeholder="<?php esc_attr_e('Field label', 'invoiceforge'); ?>">
                                <label style="display:inline-flex;align-items:center;gap:4px;">
                                    <input type="radio" name="if_sig_col_<?php echo esc_attr($sig_field['label'] ?? uniqid()); ?>_tmp"
                                           value="left" <?php checked(($sig_field['col'] ?? 'left'), 'left'); ?>>
                                    <?php esc_html_e('Left', 'invoiceforge'); ?>
                                </label>
                                <label style="display:inline-flex;align-items:center;gap:4px;">
                                    <input type="radio" name="if_sig_col_<?php echo esc_attr($sig_field['label'] ?? uniqid()); ?>_tmp"
                                           value="right" <?php checked(($sig_field['col'] ?? 'left'), 'right'); ?>>
                                    <?php esc_html_e('Right', 'invoiceforge'); ?>
                                </label>
                                <input type="hidden" name="invoiceforge_settings[template][signature_fields][][col]"
                                       value="<?php echo esc_attr($sig_field['col'] ?? 'left'); ?>" class="if-sig-col-hidden">
                                <button type="button" class="button if-remove-sig-field"><?php esc_html_e('Remove', 'invoiceforge'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="if-add-sig-field" class="button">
                        <?php esc_html_e('+ Add Signature Field', 'invoiceforge'); ?>
                    </button>

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
