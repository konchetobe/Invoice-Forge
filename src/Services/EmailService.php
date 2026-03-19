<?php
/**
 * Email Service
 *
 * Handles sending invoice emails with optional PDF attachments.
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
 * Email service for sending invoices and reminders.
 *
 * @since 1.1.0
 */
class EmailService
{
    /**
     * Logger instance.
     *
     * @since 1.1.0
     * @var Logger
     */
    private Logger $logger;

    /**
     * PDF service instance.
     *
     * @since 1.1.0
     * @var PdfService
     */
    private PdfService $pdfService;

    /**
     * Constructor.
     *
     * @since 1.1.0
     *
     * @param Logger     $logger     Logger instance.
     * @param PdfService $pdfService PDF service instance.
     */
    public function __construct(Logger $logger, PdfService $pdfService)
    {
        $this->logger     = $logger;
        $this->pdfService = $pdfService;
    }

    /**
     * Send an invoice email with optional PDF attachment.
     *
     * @since 1.1.0
     *
     * @param int $invoice_id The invoice post ID.
     * @return bool Whether the email was sent successfully.
     */
    public function sendInvoice(int $invoice_id): bool
    {
        $invoice = $this->pdfService->getInvoiceData($invoice_id);
        if (!$invoice) {
            $this->logger->error('Cannot send email: invoice not found', ['invoice_id' => $invoice_id]);
            return false;
        }

        $to = $invoice['client_email'];
        if (empty($to) || !is_email($to)) {
            $this->logger->warning('Cannot send email: no valid client email', ['invoice_id' => $invoice_id]);
            return false;
        }

        $settings   = get_option('invoiceforge_settings', []);
        $from_name  = $settings['email_from_name'] ?? get_option('blogname');
        $from_email = $settings['email_from_address'] ?? get_option('admin_email');

        $subject = sprintf(
            /* translators: %s: Invoice number */
            __('Invoice %s from %s', 'invoiceforge'),
            $invoice['number'],
            $invoice['company_name']
        );

        $message = $this->pdfService->renderEmailBody($invoice_id);
        if (empty($message)) {
            $message = '<html><body><p>' . sprintf(
                esc_html__('Please find your invoice %s attached.', 'invoiceforge'),
                esc_html($invoice['number'])
            ) . '</p><p><strong>' . esc_html__('Amount Due:', 'invoiceforge') . ' '
            . esc_html($invoice['currency']) . ' '
            . esc_html(number_format($invoice['total_amount'], 2))
            . '</strong></p></body></html>';
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        $attachments = [];

        // Attach PDF if mPDF is available
        if (PdfService::isAvailable()) {
            $pdf_content = $this->pdfService->generate($invoice_id, 'S');
            if ($pdf_content) {
                $tmp_dir  = get_temp_dir();
                $tmp_file = $tmp_dir . 'invoiceforge-' . $invoice_id . '-' . time() . '.pdf';
                file_put_contents($tmp_file, $pdf_content);
                $attachments[] = $tmp_file;
            }
        }

        try {
            $sent = wp_mail($to, $subject, $message, $headers, $attachments);
        } catch (\Throwable $e) {
            $this->logger->error('wp_mail threw exception', ['error' => $e->getMessage()]);
            $sent = false;
        }

        // Cleanup temp PDF
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        if ($sent) {
            update_post_meta($invoice_id, '_invoice_last_email_sent', current_time('mysql'));
            $this->logger->info('Invoice email sent', ['invoice_id' => $invoice_id, 'to' => $to]);
        } else {
            $this->logger->error('Failed to send invoice email', ['invoice_id' => $invoice_id, 'to' => $to]);
        }

        return $sent;
    }

    /**
     * Send a payment reminder email.
     *
     * @since 1.1.0
     *
     * @param int $invoice_id The invoice post ID.
     * @return bool Whether the email was sent successfully.
     */
    public function sendReminder(int $invoice_id): bool
    {
        $invoice = $this->pdfService->getInvoiceData($invoice_id);
        if (!$invoice) {
            return false;
        }

        $to = $invoice['client_email'];
        if (empty($to) || !is_email($to)) {
            return false;
        }

        $settings   = get_option('invoiceforge_settings', []);
        $from_name  = $settings['email_from_name'] ?? get_option('blogname');
        $from_email = $settings['email_from_address'] ?? get_option('admin_email');

        $subject = sprintf(
            /* translators: %s: Invoice number */
            __('Payment Reminder: Invoice %s', 'invoiceforge'),
            $invoice['number']
        );

        $message = '<html><body>'
            . '<p>' . sprintf(
                /* translators: %s: Client name */
                esc_html__('Dear %s,', 'invoiceforge'),
                esc_html($invoice['client_name'])
            ) . '</p>'
            . '<p>' . sprintf(
                /* translators: 1: Invoice number, 2: Currency code, 3: Amount */
                esc_html__('This is a friendly reminder that invoice %1$s for %2$s %3$s is overdue.', 'invoiceforge'),
                esc_html($invoice['number']),
                esc_html($invoice['currency']),
                esc_html(number_format($invoice['total_amount'], 2))
            ) . '</p>'
            . '<p>' . sprintf(
                /* translators: %s: Due date */
                esc_html__('Original due date: %s', 'invoiceforge'),
                esc_html($invoice['due_date'])
            ) . '</p>'
            . '<p>' . esc_html__('Please arrange payment at your earliest convenience.', 'invoiceforge') . '</p>'
            . '<p>' . esc_html($invoice['company_name']) . '</p>'
            . '</body></html>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            update_post_meta($invoice_id, '_invoice_last_reminder_sent', current_time('mysql'));
        }

        return $sent;
    }
}
