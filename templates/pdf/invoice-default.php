<?php
/**
 * Default Invoice PDF/Email Template
 *
 * Included by PdfService::generate() with all template variables pre-extracted.
 * Branches on $render_mode: 'pdf' renders a professional mPDF-compatible layout;
 * 'email' renders a simplified inline-styled HTML email body.
 *
 * Available variables (injected via extract() from PdfService::getTemplateContext()):
 *
 * Invoice data:
 *   @var int    $id
 *   @var string $number
 *   @var string $date
 *   @var string $due_date
 *   @var string $status
 *   @var string $currency
 *   @var string $currency_symbol
 *   @var float  $subtotal
 *   @var float  $tax_total
 *   @var float  $total_amount
 *   @var string $discount_type
 *   @var float  $discount_value
 *   @var string $notes
 *   @var string $terms
 *   @var string $payment_instructions
 *   @var string $payment_method
 *   @var array  $line_items
 *
 * Company:
 *   @var string $company_name
 *   @var string $company_email
 *   @var string $company_phone
 *   @var string $company_address
 *   @var string $company_id_no
 *   @var string $company_office
 *   @var string $company_att_to
 *   @var string $company_bank_name
 *   @var string $company_iban
 *   @var string $company_bic
 *
 * Client:
 *   @var string $client_name
 *   @var string $client_email
 *   @var string $client_address
 *   @var string $client_city
 *   @var string $client_state
 *   @var string $client_zip
 *   @var string $client_country
 *   @var string $client_phone
 *   @var string $client_id_no
 *   @var string $client_office
 *   @var string $client_att_to
 *
 * Template config:
 *   @var string $render_mode        'pdf' | 'email'
 *   @var string $accent_color       hex colour, e.g. '#1a2b4a'
 *   @var string $logo_path          absolute filesystem path (PDF mode)
 *   @var string $logo_url           public URL (email mode)
 *   @var string $id_no_label        e.g. 'EIK'
 *   @var array  $section_order      ordered list of section keys
 *   @var array  $section_visibility assoc: signature|notes|discount_row => bool
 *   @var array  $signature_fields   [{label, col}]
 *
 * Computed helpers:
 *   @var bool   $has_discount       true if any line item has a non-zero discount
 *
 * @package    InvoiceForge
 * @since      1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─── PDF MODE ────────────────────────────────────────────────────────────────

if ($render_mode === 'pdf') :
?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10pt;
        color: #111;
        background: #fff;
        margin: 0;
        padding: 0;
    }
    table {
        table-layout: fixed;
        border-collapse: collapse;
        width: 100%;
    }
    td, th {
        word-wrap: break-word;
        vertical-align: top;
        padding: 4px 6px;
    }
    .accent-bg {
        background-color: <?php echo esc_attr($accent_color); ?>;
        color: #fff;
    }
    .accent-text {
        color: <?php echo esc_attr($accent_color); ?>;
    }
    .bold { font-weight: bold; }
    .center { text-align: center; }
    .right { text-align: right; }
    .border-table td, .border-table th {
        border: 1px solid #ccc;
    }
    .section-header {
        background-color: <?php echo esc_attr($accent_color); ?>;
        color: #fff;
        font-weight: bold;
        font-size: 9pt;
        padding: 3px 6px;
        text-transform: uppercase;
    }
    .totals-label { text-align: right; font-weight: bold; }
    .totals-value { text-align: right; width: 20%; }
    .underline { border-bottom: 1px solid #777; min-height: 24px; margin-top: 4px; }
    .sig-label { font-size: 8pt; color: #555; }
    .spacer { height: 10px; }
</style>
</head>
<body>

<?php

// Helper: render a two-column signature field
$render_sig_col = function(array $fields, string $side) use ($accent_color): void {
    foreach ($fields as $field) {
        if (($field['col'] ?? '') === $side) {
            echo '<p class="sig-label">' . esc_html(__($field['label'], 'invoiceforge')) . '</p>';
            echo '<div class="underline">&nbsp;</div>';
        }
    }
};

foreach ($section_order as $section) :
    switch ($section) :

        // ── HEADER ──────────────────────────────────────────────────────────
        case 'header':
?>
<table style="table-layout:fixed; width:100%; margin-bottom:10px;" class="border-table">
    <tr>
        <!-- BUYER (38%) -->
        <td style="width:38%; vertical-align:top; padding:8px;">
            <strong><?php echo esc_html(__('BUYER', 'invoiceforge')); ?></strong><br>
            <strong><?php echo esc_html($client_name); ?></strong><br>
            <?php if (!empty($client_address)) : ?>
                <?php echo nl2br(esc_html($client_address)); ?><br>
            <?php endif; ?>
            <?php
            $addr_parts = array_filter([
                !empty($client_zip) ? $client_zip : '',
                !empty($client_city) ? $client_city : '',
            ]);
            $addr_line2 = implode(' ', $addr_parts);
            if (!empty($client_state)) {
                $addr_line2 .= ($addr_line2 ? ', ' : '') . $client_state;
            }
            if (!empty($client_country)) {
                $addr_line2 .= ($addr_line2 ? ', ' : '') . $client_country;
            }
            ?>
            <?php if (!empty($addr_line2)) : ?>
                <?php echo esc_html($addr_line2); ?><br>
            <?php endif; ?>
            <?php if (!empty($client_id_no)) : ?>
                <?php echo esc_html($id_no_label); ?>: <?php echo esc_html($client_id_no); ?><br>
            <?php endif; ?>
            <?php if (!empty($client_office)) : ?>
                <?php echo esc_html(__('Office', 'invoiceforge')); ?>: <?php echo esc_html($client_office); ?><br>
            <?php endif; ?>
            <?php if (!empty($client_att_to)) : ?>
                <?php echo esc_html(__('Att To', 'invoiceforge')); ?>: <?php echo esc_html($client_att_to); ?><br>
            <?php endif; ?>
            <?php if (!empty($client_email)) : ?>
                <?php echo esc_html($client_email); ?><br>
            <?php endif; ?>
            <?php if (!empty($client_phone)) : ?>
                <?php echo esc_html($client_phone); ?><br>
            <?php endif; ?>
        </td>
        <!-- INVOICE NUMBER / DATE CENTER (24%) -->
        <td style="width:24%; text-align:center; vertical-align:middle; background-color:<?php echo esc_attr($accent_color); ?>; color:#fff; padding:8px;">
            <strong style="font-size:12pt;"><?php echo esc_html(__('INVOICE', 'invoiceforge')); ?></strong><br><br>
            <strong><?php echo esc_html(__('No.', 'invoiceforge')); ?></strong> <?php echo esc_html($number); ?><br><br>
            <?php echo esc_html(__('Date', 'invoiceforge')); ?>: <?php echo esc_html($date); ?><br>
            <?php if (!empty($due_date)) : ?>
                <?php echo esc_html(__('Due', 'invoiceforge')); ?>: <?php echo esc_html($due_date); ?><br>
            <?php endif; ?>
        </td>
        <!-- SELLER (38%) -->
        <td style="width:38%; vertical-align:top; padding:8px;">
            <strong><?php echo esc_html(__('SELLER', 'invoiceforge')); ?></strong><br>
            <?php if (!empty($logo_path) && file_exists($logo_path)) : ?>
                <img src="<?php echo esc_attr($logo_path); ?>" style="max-height:60px; max-width:180px; display:block; margin-bottom:4px;" alt=""><br>
            <?php endif; ?>
            <strong><?php echo esc_html($company_name); ?></strong><br>
            <?php if (!empty($company_address)) : ?>
                <?php echo nl2br(esc_html($company_address)); ?><br>
            <?php endif; ?>
            <?php if (!empty($company_id_no)) : ?>
                <?php echo esc_html($id_no_label); ?>: <?php echo esc_html($company_id_no); ?><br>
            <?php endif; ?>
            <?php if (!empty($company_office)) : ?>
                <?php echo esc_html(__('Office', 'invoiceforge')); ?>: <?php echo esc_html($company_office); ?><br>
            <?php endif; ?>
            <?php if (!empty($company_att_to)) : ?>
                <?php echo esc_html(__('Att To', 'invoiceforge')); ?>: <?php echo esc_html($company_att_to); ?><br>
            <?php endif; ?>
            <?php if (!empty($company_phone)) : ?>
                <?php echo esc_html($company_phone); ?><br>
            <?php endif; ?>
            <?php if (!empty($company_email)) : ?>
                <?php echo esc_html($company_email); ?><br>
            <?php endif; ?>
        </td>
    </tr>
</table>
<?php
            break;

        // ── LINE ITEMS ───────────────────────────────────────────────────────
        case 'line_items':
?>
<div class="spacer"></div>
<table style="table-layout:fixed; width:100%; margin-bottom:10px;" class="border-table">
    <thead>
        <tr class="accent-bg">
            <th style="width:5%;" class="center"><?php echo esc_html(__('No.', 'invoiceforge')); ?></th>
            <th style="<?php echo $has_discount ? 'width:32%;' : 'width:42%;'; ?>"><?php echo esc_html(__('Description', 'invoiceforge')); ?></th>
            <th style="width:7%;" class="center"><?php echo esc_html(__('Qty', 'invoiceforge')); ?></th>
            <th style="width:10%;" class="right"><?php echo esc_html(__('Unit Price', 'invoiceforge')); ?></th>
            <?php if ($has_discount) : ?>
                <th style="width:8%;" class="right"><?php echo esc_html(__('Disc.%', 'invoiceforge')); ?></th>
                <th style="width:8%;" class="right"><?php echo esc_html(__('Disc.Amt', 'invoiceforge')); ?></th>
            <?php endif; ?>
            <th style="width:8%;" class="right"><?php echo esc_html(__('Tax %', 'invoiceforge')); ?></th>
            <th style="width:10%;" class="right"><?php echo esc_html(__('Tax Amt', 'invoiceforge')); ?></th>
            <th style="width:10%;" class="right"><?php echo esc_html(__('Total', 'invoiceforge')); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($line_items as $i => $item) : ?>
            <?php
            // Compute effective discount for display
            $disp_disc_pct = '';
            $disp_disc_amt = '';
            if ($has_discount && !empty($item['discount_type'])) {
                if ($item['discount_type'] === 'percentage') {
                    $disp_disc_pct = number_format((float)($item['discount_value'] ?? 0), 2) . '%';
                    $base = (float)($item['unit_price'] ?? 0) * (float)($item['quantity'] ?? 1);
                    $disp_disc_amt = number_format($base * ((float)($item['discount_value'] ?? 0) / 100), 2);
                } else {
                    $disp_disc_pct = '';
                    $disp_disc_amt = number_format((float)($item['discount_value'] ?? 0), 2);
                }
            }
            $tax_rate_display = '';
            if (!empty($item['tax_rate_id'])) {
                // Fetch rate label from DB for display; gracefully skip if not found
                global $wpdb;
                $rate = $wpdb->get_var($wpdb->prepare(
                    "SELECT rate FROM {$wpdb->prefix}invoiceforge_tax_rates WHERE id = %d",
                    (int)$item['tax_rate_id']
                ));
                $tax_rate_display = $rate !== null ? number_format((float)$rate, 2) . '%' : '';
            }
            ?>
            <tr style="background: <?php echo ($i % 2 === 0) ? '#fff' : '#f7f7f7'; ?>;">
                <td class="center"><?php echo esc_html((string)($i + 1)); ?></td>
                <td><?php echo esc_html($item['description'] ?? ''); ?></td>
                <td class="center"><?php echo esc_html(number_format((float)($item['quantity'] ?? 0), 2)); ?></td>
                <td class="right"><?php echo esc_html($currency_symbol . number_format((float)($item['unit_price'] ?? 0), 2)); ?></td>
                <?php if ($has_discount) : ?>
                    <td class="right"><?php echo esc_html($disp_disc_pct); ?></td>
                    <td class="right"><?php echo !empty($disp_disc_amt) ? esc_html($currency_symbol . $disp_disc_amt) : ''; ?></td>
                <?php endif; ?>
                <td class="right"><?php echo esc_html($tax_rate_display); ?></td>
                <td class="right"><?php echo esc_html($currency_symbol . number_format((float)($item['tax_amount'] ?? 0), 2)); ?></td>
                <td class="right"><?php echo esc_html($currency_symbol . number_format((float)($item['total'] ?? 0), 2)); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
            break;

        // ── TOTALS ───────────────────────────────────────────────────────────
        case 'totals':
?>
<div class="spacer"></div>
<table style="table-layout:fixed; width:100%; margin-bottom:6px;">
    <tr>
        <td style="width:60%;"></td>
        <td style="width:40%;">
            <table style="table-layout:fixed; width:100%;" class="border-table">
                <tr>
                    <td class="totals-label"><?php echo esc_html(__('Subtotal', 'invoiceforge')); ?></td>
                    <td class="totals-value"><?php echo esc_html($currency_symbol . number_format($subtotal, 2)); ?></td>
                </tr>
                <?php if (!empty($section_visibility['discount_row']) && $discount_value > 0) : ?>
                    <tr>
                        <td class="totals-label">
                            <?php echo esc_html(__('Discount', 'invoiceforge')); ?>
                            <?php if ($discount_type === 'percentage') : ?>
                                (<?php echo esc_html(number_format($discount_value, 2)); ?>%)
                            <?php endif; ?>
                        </td>
                        <td class="totals-value">
                            <?php
                            if ($discount_type === 'percentage') {
                                $disc_amt = $subtotal * ($discount_value / 100);
                            } else {
                                $disc_amt = $discount_value;
                            }
                            echo esc_html('- ' . $currency_symbol . number_format($disc_amt, 2));
                            ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="totals-label"><?php echo esc_html(__('Tax', 'invoiceforge')); ?></td>
                    <td class="totals-value"><?php echo esc_html($currency_symbol . number_format($tax_total, 2)); ?></td>
                </tr>
                <tr style="background-color:<?php echo esc_attr($accent_color); ?>; color:#fff;">
                    <td class="totals-label" style="color:#fff; font-size:11pt; padding:5px 6px;">
                        <?php echo esc_html(__('TOTAL', 'invoiceforge')); ?>
                    </td>
                    <td class="totals-value" style="color:#fff; font-size:11pt; font-weight:bold; padding:5px 6px;">
                        <?php echo esc_html($currency_symbol . number_format($total_amount, 2)); ?>
                    </td>
                </tr>
            </table>
            <?php if ($tax_total == 0) : ?>
                <p style="font-size:8pt; color:#666; margin-top:4px; font-style:italic;">
                    <?php echo esc_html(__('VAT exempt — Article 70 VATA.', 'invoiceforge')); ?>
                </p>
            <?php endif; ?>
        </td>
    </tr>
</table>
<?php
            break;

        // ── BANK ─────────────────────────────────────────────────────────────
        case 'bank':
            if ($payment_method !== 'Bank transfer') {
                break;
            }
?>
<div class="spacer"></div>
<table style="table-layout:fixed; width:100%; margin-bottom:10px;" class="border-table">
    <tr>
        <td colspan="2" class="section-header"><?php echo esc_html(__('Payment Details', 'invoiceforge')); ?></td>
    </tr>
    <tr>
        <td style="width:50%;">
            <strong><?php echo esc_html(__('Payment Method', 'invoiceforge')); ?>:</strong> <?php echo esc_html($payment_method); ?><br>
            <?php if (!empty($company_bank_name)) : ?>
                <strong><?php echo esc_html(__('Bank', 'invoiceforge')); ?>:</strong> <?php echo esc_html($company_bank_name); ?><br>
            <?php endif; ?>
            <?php if (!empty($company_iban)) : ?>
                <strong><?php echo esc_html(__('IBAN', 'invoiceforge')); ?>:</strong> <?php echo esc_html($company_iban); ?><br>
            <?php endif; ?>
            <?php if (!empty($company_bic)) : ?>
                <strong><?php echo esc_html(__('BIC', 'invoiceforge')); ?>:</strong> <?php echo esc_html($company_bic); ?><br>
            <?php endif; ?>
        </td>
        <td style="width:50%;">
            <?php if (!empty($payment_instructions)) : ?>
                <?php echo nl2br(esc_html($payment_instructions)); ?>
            <?php endif; ?>
        </td>
    </tr>
</table>
<?php
            break;

        // ── NOTES ────────────────────────────────────────────────────────────
        case 'notes':
            if (empty($section_visibility['notes']) || empty($notes)) {
                break;
            }
?>
<div class="spacer"></div>
<table style="table-layout:fixed; width:100%; margin-bottom:10px;" class="border-table">
    <tr>
        <td class="section-header"><?php echo esc_html(__('Notes', 'invoiceforge')); ?></td>
    </tr>
    <tr>
        <td><?php echo nl2br(esc_html($notes)); ?></td>
    </tr>
</table>
<?php
            break;

        // ── SIGNATURE ────────────────────────────────────────────────────────
        case 'signature':
            if (empty($section_visibility['signature'])) {
                break;
            }
?>
<div class="spacer"></div>
<table style="table-layout:fixed; width:100%; margin-bottom:10px;">
    <tr>
        <td colspan="2" class="section-header"><?php echo esc_html(__('Signatures', 'invoiceforge')); ?></td>
    </tr>
    <tr>
        <td style="width:50%; vertical-align:top; padding-right:20px;">
            <?php if (!empty($signature_left_title)) : ?>
                <p style="font-weight:bold; font-size:9pt; margin:0 0 4px;"><?php echo esc_html($signature_left_title); ?></p>
            <?php endif; ?>
            <?php $render_sig_col($signature_fields, 'left'); ?>
        </td>
        <td style="width:50%; vertical-align:top;">
            <?php if (!empty($signature_right_title)) : ?>
                <p style="font-weight:bold; font-size:9pt; margin:0 0 4px;"><?php echo esc_html($signature_right_title); ?></p>
            <?php endif; ?>
            <?php $render_sig_col($signature_fields, 'right'); ?>
        </td>
    </tr>
</table>
<?php
            break;

    endswitch;
endforeach;
?>

</body>
</html>
<?php

// ─── EMAIL MODE ───────────────────────────────────────────────────────────────

elseif ($render_mode === 'email') :
    // Inline-styled HTML email body.  No <style> blocks, no class attributes.
    // mPDF is NOT used for this output.
    $btn_style = 'display:inline-block; padding:10px 20px; background-color:' . esc_attr($accent_color) . '; color:#ffffff; text-decoration:none; border-radius:4px; font-weight:bold;';
    $th_style  = 'background-color:' . esc_attr($accent_color) . '; color:#ffffff; padding:8px 10px; text-align:left;';
    $td_style  = 'padding:6px 10px; border-bottom:1px solid #eeeeee;';
    $td_right  = 'padding:6px 10px; border-bottom:1px solid #eeeeee; text-align:right;';
?><!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif; font-size:13px; color:#333; background:#f4f4f4; margin:0; padding:20px;">
<table style="table-layout:fixed; width:600px; max-width:100%; margin:0 auto; background:#ffffff; border:1px solid #dddddd;">
    <tr>
        <td style="padding:20px; background-color:<?php echo esc_attr($accent_color); ?>; color:#ffffff;">
            <?php if (!empty($logo_url)) : ?>
                <img src="<?php echo esc_attr($logo_url); ?>" alt="" style="max-height:50px; max-width:150px; display:block; margin-bottom:8px;">
            <?php endif; ?>
            <strong style="font-size:18px;"><?php echo esc_html($company_name); ?></strong>
        </td>
    </tr>
    <tr>
        <td style="padding:20px;">
            <p style="font-size:20px; font-weight:bold; margin:0 0 16px;"><?php echo esc_html(__('Invoice', 'invoiceforge') . ' ' . $number); ?></p>

            <!-- Summary table -->
            <table style="table-layout:fixed; width:100%; margin-bottom:20px; border-collapse:collapse;">
                <tr>
                    <td style="<?php echo $td_style; ?>"><?php echo esc_html(__('Date', 'invoiceforge')); ?></td>
                    <td style="<?php echo $td_right; ?>"><?php echo esc_html($date); ?></td>
                </tr>
                <?php if (!empty($due_date)) : ?>
                <tr>
                    <td style="<?php echo $td_style; ?>"><?php echo esc_html(__('Due Date', 'invoiceforge')); ?></td>
                    <td style="<?php echo $td_right; ?>"><?php echo esc_html($due_date); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td style="<?php echo $td_style; ?>"><?php echo esc_html(__('Status', 'invoiceforge')); ?></td>
                    <td style="<?php echo $td_right; ?>"><?php echo esc_html(ucfirst($status)); ?></td>
                </tr>
                <tr>
                    <td style="<?php echo $td_style; ?> font-weight:bold;"><?php echo esc_html(__('Amount Due', 'invoiceforge')); ?></td>
                    <td style="<?php echo $td_right; ?> font-weight:bold; font-size:15px;"><?php echo esc_html($currency_symbol . number_format($total_amount, 2)); ?></td>
                </tr>
            </table>

            <!-- Line items summary -->
            <?php if (!empty($line_items)) : ?>
            <table style="table-layout:fixed; width:100%; margin-bottom:20px; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="<?php echo $th_style; ?>"><?php echo esc_html(__('Description', 'invoiceforge')); ?></th>
                        <th style="<?php echo $th_style; ?> width:60px; text-align:center;"><?php echo esc_html(__('Qty', 'invoiceforge')); ?></th>
                        <th style="<?php echo $th_style; ?> width:90px; text-align:right;"><?php echo esc_html(__('Total', 'invoiceforge')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($line_items as $item) : ?>
                    <tr>
                        <td style="<?php echo $td_style; ?>"><?php echo esc_html($item['description'] ?? ''); ?></td>
                        <td style="<?php echo $td_style; ?> text-align:center;"><?php echo esc_html(number_format((float)($item['quantity'] ?? 0), 2)); ?></td>
                        <td style="<?php echo $td_right; ?>"><?php echo esc_html($currency_symbol . number_format((float)($item['total'] ?? 0), 2)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Pay Invoice button (placeholder — Phase 8 will populate href) -->
            <p style="text-align:center; margin:24px 0;">
                <a href="#" data-invoice-id="<?php echo esc_attr((string)$id); ?>" style="<?php echo $btn_style; ?>">
                    <?php echo esc_html(__('Pay Invoice', 'invoiceforge')); ?>
                </a>
            </p>

            <?php if (!empty($notes)) : ?>
            <p style="font-size:11px; color:#666; margin-top:16px; border-top:1px solid #eeeeee; padding-top:12px;">
                <strong><?php echo esc_html(__('Notes', 'invoiceforge')); ?>:</strong><br>
                <?php echo nl2br(esc_html($notes)); ?>
            </p>
            <?php endif; ?>
        </td>
    </tr>
    <!-- Footer -->
    <tr>
        <td style="padding:14px 20px; background:#f9f9f9; border-top:1px solid #dddddd; font-size:11px; color:#777;">
            <strong><?php echo esc_html($company_name); ?></strong><br>
            <?php if (!empty($company_address)) : ?>
                <?php echo nl2br(esc_html($company_address)); ?><br>
            <?php endif; ?>
            <?php if (!empty($company_email)) : ?>
                <?php echo esc_html($company_email); ?><br>
            <?php endif; ?>
        </td>
    </tr>
</table>
</body>
</html>
<?php
endif;
