<?php
/**
 * PDF Service
 *
 * Handles PDF generation for invoices using mPDF.
 *
 * @package    InvoiceForge
 * @subpackage Services
 * @since      1.1.0
 */

declare(strict_types=1);

namespace InvoiceForge\Services;

use InvoiceForge\Utilities\Logger;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PDF generation service.
 *
 * @since 1.1.0
 */
class PdfService
{
    /**
     * Logger instance.
     *
     * @since 1.1.0
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @since 1.1.0
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Check if mPDF is available.
     *
     * @since 1.1.0
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return class_exists(\Mpdf\Mpdf::class);
    }

    /**
     * Generate a PDF (or email HTML) for the given invoice ID.
     *
     * @since 1.1.0
     *
     * @param int    $invoice_id  The invoice post ID.
     * @param string $output_mode 'S' = string, 'F' = file, 'D' = download, 'I' = inline.
     * @param string $file_path   Optional file path when mode is 'F'.
     * @param string $render_mode 'pdf' = generate PDF via mPDF, 'email' = return raw HTML string.
     * @return string|null PDF string on mode 'S' or email HTML on 'email' render_mode, null otherwise.
     */
    public function generate(int $invoice_id, string $output_mode = 'S', string $file_path = '', string $render_mode = 'pdf'): ?string
    {
        if ($render_mode === 'pdf' && !self::isAvailable()) {
            $this->logger->warning('mPDF not installed. Run: composer require mpdf/mpdf');
            return null;
        }

        $invoice = $this->getInvoiceData($invoice_id);
        if (!$invoice) {
            $this->logger->error('Invoice not found for PDF generation', ['invoice_id' => $invoice_id]);
            return null;
        }

        try {
            $template_file = INVOICEFORGE_PLUGIN_DIR . 'templates/pdf/invoice-default.php';

            if (!file_exists($template_file)) {
                if ($render_mode === 'email') {
                    return $this->getFallbackHtml($invoice);
                }
                $html = $this->getFallbackHtml($invoice);
            } else {
                $template_vars = $this->getTemplateContext($invoice_id, $render_mode);
                ob_start();
                extract($template_vars, EXTR_SKIP);
                include $template_file;
                $html = ob_get_clean();
            }

            // Email mode: return raw HTML without mPDF
            if ($render_mode === 'email') {
                return $html;
            }

            $mpdf = new \Mpdf\Mpdf([
                'mode'          => 'utf-8',
                'format'        => 'A4',
                'margin_top'    => 15,
                'margin_right'  => 15,
                'margin_bottom' => 15,
                'margin_left'   => 15,
            ]);

            // Apply table-layout:fixed as default CSS so mPDF renders tables predictably
            $mpdf->WriteHTML('<style>table{table-layout:fixed;}td,th{word-wrap:break-word;}</style>', \Mpdf\HTMLParserMode::HEADER_CSS);

            $mpdf->WriteHTML($html);

            if ($output_mode === 'S') {
                return $mpdf->Output('', 'S');
            } elseif ($output_mode === 'F' && $file_path) {
                $mpdf->Output($file_path, 'F');
                return null;
            } elseif ($output_mode === 'D') {
                $mpdf->Output('invoice-' . ($invoice['number'] ?? $invoice_id) . '.pdf', 'D');
                return null;
            } elseif ($output_mode === 'I') {
                $mpdf->Output('invoice-' . ($invoice['number'] ?? $invoice_id) . '.pdf', 'I');
                return null;
            }

        } catch (\Throwable $e) {
            $this->logger->error('PDF generation failed', [
                'invoice_id'  => $invoice_id,
                'render_mode' => $render_mode,
                'error'       => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Render the invoice as an HTML email body.
     *
     * Convenience wrapper around generate() for email use-cases.
     * Called by EmailService in Plan 03.
     *
     * @since 1.3.0
     *
     * @param int $invoice_id Invoice post ID.
     * @return string HTML suitable for sending as an email body.
     */
    public function renderEmailBody(int $invoice_id): string
    {
        return $this->generate($invoice_id, 'S', '', 'email') ?? '';
    }

    /**
     * Render the invoice template as HTML using a mix of saved data and form overrides.
     *
     * Accepts posted form field values and merges them over the saved invoice data
     * to render a live preview WITHOUT persisting any changes.
     *
     * @since 1.4.0
     *
     * @param int   $invoice_id     Invoice post ID.
     * @param array $form_overrides Posted form field values to overlay on saved data.
     * @return string Rendered HTML preview.
     */
    public function renderPreviewHtml(int $invoice_id, array $form_overrides = []): string
    {
        $invoice = $this->getInvoiceData($invoice_id);
        if (!$invoice) {
            return '';
        }

        // Merge simple scalar overrides
        $scalar_fields = [
            'title', 'invoice_date', 'due_date', 'currency',
            'payment_method', 'status', 'discount_type', 'discount_value',
            'notes', 'terms',
        ];
        foreach ($scalar_fields as $field) {
            // Support both 'invoice_date' from POST and 'date' key in invoice data
            if (isset($form_overrides[$field])) {
                // Map invoice_date/due_date to the invoice array keys 'date'/'due_date'
                if ($field === 'invoice_date') {
                    $invoice['date'] = $form_overrides[$field];
                } else {
                    $invoice[$field] = $form_overrides[$field];
                }
            }
        }

        // Re-resolve client if client_id changed
        if (isset($form_overrides['client_id'])) {
            $new_client_id = (int) $form_overrides['client_id'];
            if ($new_client_id > 0 && $new_client_id !== (int) ($invoice['client_id'] ?? 0)) {
                $invoice['client_name']    = $this->getClientDisplayName($new_client_id);
                $invoice['client_email']   = (string) get_post_meta($new_client_id, '_client_email', true);
                $invoice['client_address'] = (string) get_post_meta($new_client_id, '_client_address', true);
                $invoice['client_city']    = (string) get_post_meta($new_client_id, '_client_city', true);
                $invoice['client_state']   = (string) get_post_meta($new_client_id, '_client_state', true);
                $invoice['client_zip']     = (string) get_post_meta($new_client_id, '_client_zip', true);
                $invoice['client_country'] = (string) get_post_meta($new_client_id, '_client_country', true);
                $invoice['client_phone']   = (string) get_post_meta($new_client_id, '_client_phone', true);
                $invoice['client_id_no']   = (string) get_post_meta($new_client_id, '_client_id_no', true);
                $invoice['client_office']  = (string) get_post_meta($new_client_id, '_client_office', true);
                $invoice['client_att_to']  = (string) get_post_meta($new_client_id, '_client_att_to', true);
            }
        }

        // Recalculate line items if provided
        if (!empty($form_overrides['line_items']) && is_array($form_overrides['line_items'])) {
            $discount_type  = $invoice['discount_type']  ?? '';
            $discount_value = (float) ($invoice['discount_value'] ?? 0);

            $computed_items = [];
            $subtotal       = 0.0;
            $tax_total      = 0.0;

            foreach ($form_overrides['line_items'] as $item) {
                $qty        = max(0, (float) ($item['quantity']   ?? 1));
                $price      = (float) ($item['unit_price']  ?? 0);
                $tax_rate   = (float) ($item['tax_rate']    ?? 0);
                $item_disc  = (float) ($item['discount_value'] ?? 0);
                $description = (string) ($item['description'] ?? '');

                $line_base  = $qty * $price;
                $line_disc  = $item_disc; // item-level discount (flat)
                $line_net   = max(0, $line_base - $line_disc);
                $line_tax   = $line_net * ($tax_rate / 100);
                $line_total = $line_net + $line_tax;

                $computed_items[] = [
                    'description'    => $description,
                    'quantity'       => $qty,
                    'unit_price'     => $price,
                    'tax_rate'       => $tax_rate,
                    'discount_value' => $item_disc,
                    'total'          => $line_total,
                ];

                $subtotal  += $line_net;
                $tax_total += $line_tax;
            }

            // Apply invoice-level discount
            $inv_discount = 0.0;
            if ($discount_type === 'percentage') {
                $inv_discount = $subtotal * ($discount_value / 100);
            } elseif ($discount_type === 'fixed') {
                $inv_discount = $discount_value;
            }

            $total_amount = max(0, $subtotal - $inv_discount + $tax_total);

            $invoice['line_items']    = $computed_items;
            $invoice['subtotal']      = $subtotal;
            $invoice['tax_total']     = $tax_total;
            $invoice['total_amount']  = $total_amount;
        }

        try {
            $template_file = INVOICEFORGE_PLUGIN_DIR . 'templates/pdf/invoice-default.php';

            if (!file_exists($template_file)) {
                return $this->getFallbackHtml($invoice);
            }

            $template_vars = $this->getTemplateContext($invoice_id, 'email', $invoice);
            ob_start();
            extract($template_vars, EXTR_SKIP);
            include $template_file;
            return (string) ob_get_clean();

        } catch (\Throwable $e) {
            $this->logger->error('Preview HTML render failed', [
                'invoice_id' => $invoice_id,
                'error'      => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Build the full template context array for template rendering.
     *
     * Combines invoice data, template settings, company profile, client extended
     * fields, and computed helpers so the template receives a single flat array.
     *
     * @since 1.3.0
     *
     * @param int                       $invoice_id            Invoice post ID.
     * @param string                    $render_mode           'pdf' or 'email'.
     * @param array<string, mixed>|null $invoice_data_override When provided, skip DB fetch and use this data instead.
     * @return array<string, mixed>
     */
    private function getTemplateContext(int $invoice_id, string $render_mode, ?array $invoice_data_override = null): array
    {
        $invoice  = $invoice_data_override ?? $this->getInvoiceData($invoice_id);
        $settings = get_option('invoiceforge_settings', []);
        $template = $settings['template'] ?? [];

        // Resolve client extended fields
        // When an override is provided, client fields are already merged into $invoice
        if ($invoice_data_override !== null) {
            $client_id_no  = (string) ($invoice['client_id_no']  ?? '');
            $client_office = (string) ($invoice['client_office'] ?? '');
            $client_att_to = (string) ($invoice['client_att_to'] ?? '');
            $payment_method = (string) ($invoice['payment_method'] ?? '');
        } else {
            $client_id = (int) get_post_meta($invoice_id, '_invoice_client_id', true);
            $client_id_no  = $client_id ? (string) get_post_meta($client_id, '_client_id_no', true) : '';
            $client_office = $client_id ? (string) get_post_meta($client_id, '_client_office', true) : '';
            $client_att_to = $client_id ? (string) get_post_meta($client_id, '_client_att_to', true) : '';
            // Payment method from invoice meta
            $payment_method = (string) get_post_meta($invoice_id, '_invoice_payment_method', true);
        }

        // Determine whether any line item carries a non-zero discount
        $has_discount = false;
        foreach (($invoice['line_items'] ?? []) as $item) {
            if (!empty($item['discount_value']) && (float) $item['discount_value'] !== 0.0) {
                $has_discount = true;
                break;
            }
        }

        // Currency symbol map
        $currency_symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'BGN' => 'лв',
        ];
        $currency_code   = $invoice['currency'] ?? 'USD';
        $currency_symbol = $currency_symbols[$currency_code] ?? $currency_code;

        // Default section order and visibility when not configured
        $default_section_order = ['header', 'line_items', 'totals', 'bank', 'notes', 'signature'];
        $section_order = !empty($template['section_order']) && is_array($template['section_order'])
            ? $template['section_order']
            : $default_section_order;

        $default_visibility = ['signature' => true, 'notes' => true, 'discount_row' => true];
        $section_visibility = !empty($template['section_visibility']) && is_array($template['section_visibility'])
            ? array_merge($default_visibility, $template['section_visibility'])
            : $default_visibility;

        $default_signature_fields = [
            ['label' => 'Date',          'col' => 'left'],
            ['label' => 'Place',         'col' => 'left'],
            ['label' => 'Compiler',      'col' => 'right'],
            ['label' => 'Personal code', 'col' => 'right'],
            ['label' => 'Attended to',   'col' => 'right'],
        ];
        $signature_fields = !empty($template['signature_fields']) && is_array($template['signature_fields'])
            ? $template['signature_fields']
            : $default_signature_fields;

        // Validate accent colour (must be a valid 3- or 6-digit hex colour)
        $raw_accent = $template['accent_color'] ?? '#1a2b4a';
        $accent_color = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $raw_accent)
            ? $raw_accent
            : '#1a2b4a';

        return array_merge($invoice ?? [], [
            // Render context
            'render_mode'        => $render_mode,

            // Template config
            'accent_color'       => $accent_color,
            'logo_path'          => $template['logo_path'] ?? '',
            'logo_url'           => $template['logo_url'] ?? '',
            'id_no_label'        => $template['id_no_label'] ?? 'EIK',
            'section_order'      => $section_order,
            'section_visibility' => $section_visibility,
            'signature_fields'      => $signature_fields,
            'signature_left_title'  => $template['signature_left_title'] ?? '',
            'signature_right_title' => $template['signature_right_title'] ?? '',

            // Company profile (extended fields from Plan 01)
            'company_id_no'    => $settings['company_id_no']    ?? '',
            'company_office'   => $settings['company_office']   ?? '',
            'company_att_to'   => $settings['company_att_to']   ?? '',
            'company_bank_name'=> $settings['company_bank_name']?? '',
            'company_iban'     => $settings['company_iban']     ?? '',
            'company_bic'      => $settings['company_bic']      ?? '',

            // Client extended fields
            'client_id_no'  => $client_id_no,
            'client_office' => $client_office,
            'client_att_to' => $client_att_to,

            // Computed helpers
            'payment_method'  => $payment_method,
            'has_discount'    => $has_discount,
            'currency_symbol' => $currency_symbol,
        ]);
    }

    /**
     * Get invoice data array for use in templates.
     *
     * @since 1.1.0
     *
     * @param int $invoice_id Invoice post ID.
     * @return array<string, mixed>|null
     */
    public function getInvoiceData(int $invoice_id): ?array
    {
        $post = get_post($invoice_id);
        if (!$post || $post->post_type !== 'if_invoice') {
            return null;
        }

        $client_id = (int) get_post_meta($invoice_id, '_invoice_client_id', true);
        $client    = $client_id ? get_post($client_id) : null;

        $lineItemRepo = new \InvoiceForge\Repositories\LineItemRepository();
        $line_items   = array_map(
            fn($item) => $item->toArray(),
            $lineItemRepo->findByInvoice($invoice_id)
        );

        $settings = get_option('invoiceforge_settings', []);

        return [
            'id'             => $invoice_id,
            'number'         => get_post_meta($invoice_id, '_invoice_number', true),
            'date'           => get_post_meta($invoice_id, '_invoice_date', true),
            'due_date'       => get_post_meta($invoice_id, '_invoice_due_date', true),
            'status'         => get_post_meta($invoice_id, '_invoice_status', true) ?: 'draft',
            'currency'       => get_post_meta($invoice_id, '_invoice_currency', true) ?: 'USD',
            'subtotal'       => (float) get_post_meta($invoice_id, '_invoice_subtotal', true),
            'tax_total'      => (float) get_post_meta($invoice_id, '_invoice_tax_total', true),
            'total_amount'   => (float) get_post_meta($invoice_id, '_invoice_total_amount', true),
            'discount_type'  => get_post_meta($invoice_id, '_invoice_discount_type', true),
            'discount_value' => (float) get_post_meta($invoice_id, '_invoice_discount_value', true),
            'notes'          => get_post_meta($invoice_id, '_invoice_notes', true),
            'terms'          => get_post_meta($invoice_id, '_invoice_terms', true),
            'payment_instructions' => get_post_meta($invoice_id, '_invoice_payment_instructions', true),
            'payment_method' => (string) get_post_meta($invoice_id, '_invoice_payment_method', true),
            'client_name'    => $client_id ? $this->getClientDisplayName($client_id) : ($client ? $client->post_title : ''),
            'client_email'   => $client_id ? get_post_meta($client_id, '_client_email', true) : '',
            'client_address' => $client_id ? get_post_meta($client_id, '_client_address', true) : '',
            'client_city'    => $client_id ? get_post_meta($client_id, '_client_city', true) : '',
            'client_state'   => $client_id ? get_post_meta($client_id, '_client_state', true) : '',
            'client_zip'     => $client_id ? get_post_meta($client_id, '_client_zip', true) : '',
            'client_country' => $client_id ? get_post_meta($client_id, '_client_country', true) : '',
            'client_phone'   => $client_id ? get_post_meta($client_id, '_client_phone', true) : '',
            'client_id_no'   => $client_id ? get_post_meta($client_id, '_client_id_no', true) : '',
            'client_office'  => $client_id ? get_post_meta($client_id, '_client_office', true) : '',
            'client_att_to'  => $client_id ? get_post_meta($client_id, '_client_att_to', true) : '',
            'company_name'   => $settings['company_name'] ?? get_option('blogname'),
            'company_email'  => $settings['company_email'] ?? get_option('admin_email'),
            'company_phone'  => $settings['company_phone'] ?? '',
            'company_address' => $settings['company_address'] ?? '',
            'line_items'     => $line_items,
        ];
    }

    /**
     * Minimal inline-HTML fallback when no PDF template file exists.
     *
     * @since 1.1.0
     *
     * @param array<string, mixed> $invoice Invoice data.
     * @return string HTML.
     */
    /**
     * Get client display name from meta (company name for companies, first+last for individuals).
     *
     * @since 1.0.0
     *
     * @param int $client_id The client post ID.
     * @return string Display name.
     */
    private function getClientDisplayName(int $client_id): string
    {
        $company = (string) get_post_meta($client_id, '_client_company', true);
        if (!empty($company)) {
            return $company;
        }
        $first = (string) get_post_meta($client_id, '_client_first_name', true);
        $last  = (string) get_post_meta($client_id, '_client_last_name', true);
        $name  = trim($first . ' ' . $last);
        return $name ?: __('Unknown Client', 'invoiceforge');
    }

    private function getFallbackHtml(array $invoice): string
    {
        $rows = '';
        foreach ($invoice['line_items'] as $item) {
            $rows .= '<tr>'
                . '<td>' . esc_html($item['description'] ?? '') . '</td>'
                . '<td>' . esc_html($item['quantity'] ?? '') . '</td>'
                . '<td>' . esc_html(number_format((float)($item['unit_price'] ?? 0), 2)) . '</td>'
                . '<td>' . esc_html(number_format((float)($item['total'] ?? 0), 2)) . '</td>'
                . '</tr>';
        }

        return '<html><body>
        <h1>INVOICE ' . esc_html($invoice['number']) . '</h1>
        <p><strong>' . esc_html($invoice['company_name']) . '</strong></p>
        <p>To: ' . esc_html($invoice['client_name']) . '</p>
        <p>Date: ' . esc_html($invoice['date']) . '</p>
        <table border="1" cellpadding="5" width="100%">
            <thead><tr><th>Description</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
            <tbody>' . $rows . '</tbody>
        </table>
        <p><strong>Total: ' . esc_html($invoice['currency']) . ' ' . esc_html(number_format($invoice['total_amount'], 2)) . '</strong></p>
        </body></html>';
    }
}
