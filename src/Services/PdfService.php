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
     * Generate a PDF for the given invoice ID.
     *
     * @since 1.1.0
     *
     * @param int    $invoice_id The invoice post ID.
     * @param string $output_mode 'S' = string, 'F' = file, 'D' = download, 'I' = inline.
     * @param string $file_path  Optional file path when mode is 'F'.
     * @return string|null PDF string on mode 'S', null otherwise.
     */
    public function generate(int $invoice_id, string $output_mode = 'S', string $file_path = ''): ?string
    {
        if (!self::isAvailable()) {
            $this->logger->warning('mPDF not installed. Run: composer require mpdf/mpdf');
            return null;
        }

        $invoice = $this->getInvoiceData($invoice_id);
        if (!$invoice) {
            $this->logger->error('Invoice not found for PDF generation', ['invoice_id' => $invoice_id]);
            return null;
        }

        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode'        => 'utf-8',
                'format'      => 'A4',
                'margin_top'  => 15,
                'margin_right' => 15,
                'margin_bottom' => 15,
                'margin_left' => 15,
            ]);

            $template_file = INVOICEFORGE_PLUGIN_DIR . 'templates/pdf/invoice-default.php';
            if (!file_exists($template_file)) {
                $html = $this->getFallbackHtml($invoice);
            } else {
                ob_start();
                include $template_file;
                $html = ob_get_clean();
            }

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
                'invoice_id' => $invoice_id,
                'error'      => $e->getMessage(),
            ]);
        }

        return null;
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
            'client_name'    => $client ? $client->post_title : '',
            'client_email'   => $client_id ? get_post_meta($client_id, '_client_email', true) : '',
            'client_address' => $client_id ? get_post_meta($client_id, '_client_address', true) : '',
            'client_phone'   => $client_id ? get_post_meta($client_id, '_client_phone', true) : '',
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
