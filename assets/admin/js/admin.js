/**
 * InvoiceForge Admin JavaScript
 * Handles AJAX operations, form validation, and UI interactions
 *
 * @package InvoiceForge
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * InvoiceForge Admin Module
     */
    const InvoiceForgeAdmin = {
        
        /**
         * Initialize the admin module
         */
        init: function() {
            this.bindEvents();
            this.initMediaUploader();
            this.initFormValidation();
            this.initToastContainer();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Invoice form submission
            $(document).on('submit', '#invoiceforge-invoice-form', this.handleInvoiceSave.bind(this));
            
            // Client form submission
            $(document).on('submit', '#invoiceforge-client-form', this.handleClientSave.bind(this));
            
            // Delete invoice
            $(document).on('click', '.invoiceforge-delete-invoice', this.handleInvoiceDelete.bind(this));
            
            // Delete client
            $(document).on('click', '.invoiceforge-delete-client', this.handleClientDelete.bind(this));
            
            // Status filter tabs
            $(document).on('click', '.invoiceforge-filter-tab', this.handleFilterTab.bind(this));
            
            // Search functionality
            $(document).on('input', '.invoiceforge-search-input', this.debounce(this.handleSearch.bind(this), 300));
            
            // Settings tab toggle SMTP fields
            $(document).on('change', '#smtp_enabled', this.toggleSmtpFields.bind(this));
            
            // Date validation
            $(document).on('change', '#invoice_date, #due_date', this.validateDates.bind(this));
            
            // Auto-generate client name from first + last name
            $(document).on('input', '#client_first_name, #client_last_name', this.updateClientTitle.bind(this));
            
            // Modal close
            $(document).on('click', '.invoiceforge-modal-close, .invoiceforge-modal-overlay', this.closeModal.bind(this));
            $(document).on('click', '.invoiceforge-modal', function(e) {
                e.stopPropagation();
            });
            
            // Toast close
            $(document).on('click', '.invoiceforge-toast-close', this.closeToast.bind(this));
            
            // Confirm dangerous actions
            $(document).on('click', '[data-confirm]', this.handleConfirm.bind(this));
        },

        /**
         * Initialize media uploader for image fields
         */
        initMediaUploader: function() {
            let mediaUploader;
            
            $(document).on('click', '.invoiceforge-upload-image', function(e) {
                e.preventDefault();
                
                const targetId = $(this).data('target');
                const $preview = $('#' + targetId + '_preview');
                const $input = $('#' + targetId);
                const $removeBtn = $(this).siblings('.invoiceforge-remove-image');
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media({
                    title: InvoiceForge.i18n.selectImage || 'Select Image',
                    button: { text: InvoiceForge.i18n.useImage || 'Use Image' },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    $input.val(attachment.id);
                    $preview.html('<img src="' + attachment.url + '" alt="">');
                    $removeBtn.show();
                });
                
                mediaUploader.open();
            });
            
            $(document).on('click', '.invoiceforge-remove-image', function(e) {
                e.preventDefault();
                
                const targetId = $(this).data('target');
                $('#' + targetId).val('');
                $('#' + targetId + '_preview').empty();
                $(this).hide();
            });
        },

        /**
         * Initialize form validation
         */
        initFormValidation: function() {
            // Add validation classes on blur
            $(document).on('blur', '.invoiceforge-form-input[required], .invoiceforge-form-select[required]', function() {
                const $field = $(this);
                if (!$field.val()) {
                    $field.addClass('error');
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Email validation
            $(document).on('blur', 'input[type="email"]', function() {
                const $field = $(this);
                if ($field.val() && !InvoiceForgeAdmin.isValidEmail($field.val())) {
                    $field.addClass('error');
                } else {
                    $field.removeClass('error');
                }
            });
        },

        /**
         * Initialize toast notification container
         */
        initToastContainer: function() {
            if (!$('#invoiceforge-toast-container').length) {
                $('body').append('<div id="invoiceforge-toast-container" class="invoiceforge-toast-container"></div>');
            }
        },

        /**
         * Handle invoice form save
         * Supports both existing client selection and inline new client creation
         */
        handleInvoiceSave: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitBtn = $form.find('[type="submit"]');
            const clientMode = $form.find('[name="client_mode"]').val() || 'existing';
            
            // Custom validation for client
            if (clientMode === 'existing') {
                const clientId = $form.find('[name="client_id"]').val();
                if (!clientId) {
                    this.showToast('error', InvoiceForge.i18n.clientRequired || 'Please select a client.');
                    return;
                }
            } else if (clientMode === 'new') {
                const firstName = $form.find('[name="new_client_first_name"]').val();
                const lastName = $form.find('[name="new_client_last_name"]').val();
                const email = $form.find('[name="new_client_email"]').val();
                
                if (!firstName || !lastName || !email) {
                    this.showToast('error', 'For new clients, first name, last name and email are required.');
                    return;
                }
                
                if (!this.isValidEmail(email)) {
                    this.showToast('error', InvoiceForge.i18n.invalidEmail || 'Please enter a valid email address.');
                    return;
                }
            }
            
            // Validate other required fields
            if (!$form.find('[name="title"]').val()) {
                this.showToast('error', 'Invoice title is required.');
                return;
            }
            
            // Disable button and show loading
            $submitBtn.prop('disabled', true).addClass('invoiceforge-loading');
            const originalText = $submitBtn.text();
            $submitBtn.html('<span class="invoiceforge-spinner"></span> ' + (InvoiceForge.i18n.saving || 'Saving...'));
            
            // Prepare data
            const formData = {
                action: 'invoiceforge_save_invoice',
                nonce: InvoiceForge.nonce,
                invoice_id: $form.find('[name="invoice_id"]').val() || 0,
                title: $form.find('[name="title"]').val(),
                client_id: $form.find('[name="client_id"]').val() || 0,
                client_mode: clientMode,
                invoice_date: $form.find('[name="invoice_date"]').val(),
                due_date: $form.find('[name="due_date"]').val(),
                status: $form.find('[name="status"]').val(),
                total_amount: $form.find('[name="total_amount"]').val(),
                currency: $form.find('[name="currency"]').val(),
                notes: $form.find('[name="notes"]').val()
            };
            
            // Add new client fields if mode is 'new'
            if (clientMode === 'new') {
                formData.new_client_first_name = $form.find('[name="new_client_first_name"]').val();
                formData.new_client_last_name = $form.find('[name="new_client_last_name"]').val();
                formData.new_client_email = $form.find('[name="new_client_email"]').val();
                formData.new_client_company = $form.find('[name="new_client_company"]').val();
                formData.new_client_phone = $form.find('[name="new_client_phone"]').val();
                formData.new_client_address = $form.find('[name="new_client_address"]').val();
                formData.new_client_city = $form.find('[name="new_client_city"]').val();
                formData.new_client_state = $form.find('[name="new_client_state"]').val();
                formData.new_client_zip = $form.find('[name="new_client_zip"]').val();
                formData.new_client_country = $form.find('[name="new_client_country"]').val();
            }
            
            // Send AJAX request
            $.ajax({
                url: InvoiceForge.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        InvoiceForgeAdmin.showToast('success', response.data.message);
                        
                        // Update URL if new invoice
                        if (!formData.invoice_id && response.data.invoice_id) {
                            const newUrl = window.location.href.replace('action=new', 'action=edit&invoice_id=' + response.data.invoice_id);
                            window.history.replaceState({}, '', newUrl);
                            $form.find('[name="invoice_id"]').val(response.data.invoice_id);
                        }
                        
                        // Update invoice number if generated
                        if (response.data.invoice && response.data.invoice.number) {
                            $form.find('[name="invoice_number"]').val(response.data.invoice.number);
                        }
                        
                        // If new client was created, switch to existing mode and update dropdown
                        if (clientMode === 'new' && response.data.invoice && response.data.invoice.client_id) {
                            const clientName = formData.new_client_first_name + ' ' + formData.new_client_last_name;
                            const $clientSelect = $form.find('[name="client_id"]');
                            $clientSelect.append('<option value="' + response.data.invoice.client_id + '">' + clientName + '</option>');
                            $clientSelect.val(response.data.invoice.client_id);
                            
                            // Switch to existing mode
                            $form.find('input[name="client_mode_radio"][value="existing"]').prop('checked', true);
                            if (typeof toggleClientMode === 'function') {
                                toggleClientMode('existing');
                            }
                        }
                    } else {
                        InvoiceForgeAdmin.showToast('error', response.data.message || InvoiceForge.i18n.saveError);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Invoice save error:', status, error);
                    InvoiceForgeAdmin.showToast('error', InvoiceForge.i18n.networkError || 'Network error. Please try again.');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).removeClass('invoiceforge-loading').text(originalText);
                }
            });
        },

        /**
         * Handle client form save
         */
        handleClientSave: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const $submitBtn = $form.find('[type="submit"]');
            
            // Validate form
            if (!this.validateForm($form)) {
                this.showToast('error', InvoiceForge.i18n.validationError || 'Please fill in all required fields.');
                return;
            }
            
            // Disable button and show loading
            $submitBtn.prop('disabled', true).addClass('invoiceforge-loading');
            const originalText = $submitBtn.text();
            $submitBtn.html('<span class="invoiceforge-spinner"></span> ' + (InvoiceForge.i18n.saving || 'Saving...'));
            
            // Prepare data
            const formData = {
                action: 'invoiceforge_save_client',
                nonce: InvoiceForge.nonce,
                client_id: $form.find('[name="client_id"]').val() || 0,
                first_name: $form.find('[name="first_name"]').val(),
                last_name: $form.find('[name="last_name"]').val(),
                company: $form.find('[name="company"]').val(),
                email: $form.find('[name="email"]').val(),
                phone: $form.find('[name="phone"]').val(),
                address: $form.find('[name="address"]').val(),
                city: $form.find('[name="city"]').val(),
                state: $form.find('[name="state"]').val(),
                zip: $form.find('[name="zip"]').val(),
                country: $form.find('[name="country"]').val(),
                tax_id: $form.find('[name="tax_id"]').val()
            };
            
            // Send AJAX request
            $.ajax({
                url: InvoiceForge.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        InvoiceForgeAdmin.showToast('success', response.data.message);
                        
                        // Update URL if new client
                        if (!formData.client_id && response.data.client_id) {
                            const newUrl = window.location.href.replace('action=new', 'action=edit&client_id=' + response.data.client_id);
                            window.history.replaceState({}, '', newUrl);
                            $form.find('[name="client_id"]').val(response.data.client_id);
                        }
                    } else {
                        InvoiceForgeAdmin.showToast('error', response.data.message || InvoiceForge.i18n.saveError);
                    }
                },
                error: function() {
                    InvoiceForgeAdmin.showToast('error', InvoiceForge.i18n.networkError || 'Network error. Please try again.');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).removeClass('invoiceforge-loading').text(originalText);
                }
            });
        },

        /**
         * Handle invoice delete
         */
        handleInvoiceDelete: function(e) {
            e.preventDefault();
            
            const $btn = $(e.target).closest('.invoiceforge-delete-invoice');
            const invoiceId = $btn.data('id');
            
            if (!confirm(InvoiceForge.i18n.confirmDelete || 'Are you sure you want to delete this invoice?')) {
                return;
            }
            
            $btn.prop('disabled', true).addClass('invoiceforge-loading');
            
            $.ajax({
                url: InvoiceForge.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'invoiceforge_delete_invoice',
                    nonce: InvoiceForge.nonce,
                    invoice_id: invoiceId
                },
                success: function(response) {
                    if (response.success) {
                        InvoiceForgeAdmin.showToast('success', response.data.message);
                        // Remove row from table or redirect
                        $btn.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        InvoiceForgeAdmin.showToast('error', response.data.message);
                    }
                },
                error: function() {
                    InvoiceForgeAdmin.showToast('error', InvoiceForge.i18n.networkError);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('invoiceforge-loading');
                }
            });
        },

        /**
         * Handle client delete
         */
        handleClientDelete: function(e) {
            e.preventDefault();
            
            const $btn = $(e.target).closest('.invoiceforge-delete-client');
            const clientId = $btn.data('id');
            
            if (!confirm(InvoiceForge.i18n.confirmDelete || 'Are you sure you want to delete this client?')) {
                return;
            }
            
            $btn.prop('disabled', true).addClass('invoiceforge-loading');
            
            $.ajax({
                url: InvoiceForge.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'invoiceforge_delete_client',
                    nonce: InvoiceForge.nonce,
                    client_id: clientId
                },
                success: function(response) {
                    if (response.success) {
                        InvoiceForgeAdmin.showToast('success', response.data.message);
                        $btn.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        InvoiceForgeAdmin.showToast('error', response.data.message);
                    }
                },
                error: function() {
                    InvoiceForgeAdmin.showToast('error', InvoiceForge.i18n.networkError);
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('invoiceforge-loading');
                }
            });
        },

        /**
         * Handle status filter tab click
         */
        handleFilterTab: function(e) {
            e.preventDefault();
            
            const $tab = $(e.target).closest('.invoiceforge-filter-tab');
            const status = $tab.data('status');
            
            // Update active state
            $('.invoiceforge-filter-tab').removeClass('active');
            $tab.addClass('active');
            
            // Filter table rows
            const $table = $('.invoiceforge-table tbody');
            
            if (status === 'all') {
                $table.find('tr').show();
            } else {
                $table.find('tr').hide();
                $table.find('tr[data-status="' + status + '"]').show();
            }
        },

        /**
         * Handle search
         */
        handleSearch: function(e) {
            const searchTerm = $(e.target).val().toLowerCase();
            const $table = $('.invoiceforge-table tbody');
            
            $table.find('tr').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(searchTerm) !== -1);
            });
        },

        /**
         * Toggle SMTP fields visibility
         */
        toggleSmtpFields: function(e) {
            const isEnabled = $(e.target).is(':checked');
            $('.smtp-field').closest('tr').toggle(isEnabled);
        },

        /**
         * Validate date fields
         */
        validateDates: function() {
            const invoiceDate = $('#invoice_date').val();
            const dueDate = $('#due_date').val();
            
            if (invoiceDate && dueDate) {
                if (new Date(dueDate) < new Date(invoiceDate)) {
                    this.showToast('warning', InvoiceForge.i18n.dueDateWarning || 'Due date cannot be before invoice date.');
                    $('#due_date').addClass('error');
                } else {
                    $('#due_date').removeClass('error');
                }
            }
        },

        /**
         * Update client title from first + last name
         */
        updateClientTitle: function() {
            const firstName = $('#client_first_name').val() || '';
            const lastName = $('#client_last_name').val() || '';
            const fullName = (firstName + ' ' + lastName).trim();
            
            // Update title preview if exists
            $('.invoiceforge-client-name-preview').text(fullName || 'Client Name');
        },

        /**
         * Validate form
         */
        validateForm: function($form) {
            let isValid = true;
            
            $form.find('[required]').each(function() {
                const $field = $(this);
                if (!$field.val()) {
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Email validation
            $form.find('input[type="email"]').each(function() {
                const $field = $(this);
                if ($field.val() && !InvoiceForgeAdmin.isValidEmail($field.val())) {
                    $field.addClass('error');
                    isValid = false;
                }
            });
            
            return isValid;
        },

        /**
         * Validate email format
         */
        isValidEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        /**
         * Show toast notification
         */
        showToast: function(type, message, duration) {
            duration = duration || 5000;
            
            const icons = {
                success: '<svg viewBox="0 0 20 20" fill="currentColor" style="color: #10b981;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
                error: '<svg viewBox="0 0 20 20" fill="currentColor" style="color: #ef4444;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
                warning: '<svg viewBox="0 0 20 20" fill="currentColor" style="color: #f59e0b;"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
                info: '<svg viewBox="0 0 20 20" fill="currentColor" style="color: #3b82f6;"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>'
            };
            
            const $toast = $(`
                <div class="invoiceforge-toast ${type}">
                    <div class="invoiceforge-toast-icon">${icons[type] || icons.info}</div>
                    <div class="invoiceforge-toast-content">
                        <div class="invoiceforge-toast-message">${message}</div>
                    </div>
                    <button type="button" class="invoiceforge-toast-close">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            `);
            
            $('#invoiceforge-toast-container').append($toast);
            
            // Auto-dismiss
            setTimeout(function() {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, duration);
        },

        /**
         * Close toast notification
         */
        closeToast: function(e) {
            $(e.target).closest('.invoiceforge-toast').fadeOut(300, function() {
                $(this).remove();
            });
        },

        /**
         * Handle confirm dialog
         */
        handleConfirm: function(e) {
            const message = $(e.target).data('confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        },

        /**
         * Open modal
         */
        openModal: function(modalId) {
            $('#' + modalId).addClass('active');
            $('body').css('overflow', 'hidden');
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            if ($(e.target).hasClass('invoiceforge-modal-close') || $(e.target).hasClass('invoiceforge-modal-overlay')) {
                $('.invoiceforge-modal-overlay').removeClass('active');
                $('body').css('overflow', '');
            }
        },

        /**
         * Format currency
         */
        formatCurrency: function(amount, currency) {
            const symbols = {
                'USD': '$',
                'EUR': '€',
                'GBP': '£',
                'CAD': 'C$',
                'AUD': 'A$'
            };
            
            const symbol = symbols[currency] || currency + ' ';
            return symbol + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        },

        /**
         * AJAX helper
         */
        ajax: function(action, data, successCallback, errorCallback) {
            data.action = action;
            data.nonce = InvoiceForge.nonce;
            
            return $.ajax({
                url: InvoiceForge.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        if (successCallback) successCallback(response.data);
                    } else {
                        if (errorCallback) errorCallback(response.data);
                        else InvoiceForgeAdmin.showToast('error', response.data.message || 'An error occurred.');
                    }
                },
                error: function(xhr, status, error) {
                    if (errorCallback) errorCallback({ message: error });
                    else InvoiceForgeAdmin.showToast('error', 'Network error. Please try again.');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        InvoiceForgeAdmin.init();
        
        // Initial SMTP toggle state
        if ($('#smtp_enabled').length && !$('#smtp_enabled').is(':checked')) {
            $('.smtp-field').closest('tr').hide();
        }
    });

    // Expose to global scope for external access
    window.InvoiceForgeAdmin = InvoiceForgeAdmin;

})(jQuery);
