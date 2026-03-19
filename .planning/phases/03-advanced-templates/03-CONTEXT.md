# Phase 3: Advanced Templates - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Build a configurable invoice template system: a single, highly-customizable PDF template modelled on the Bulgarian business invoice reference (structured BUYER/SELLER header, line items with discount columns, VAT breakdown, bank/payment info, signature block). Includes logo support, section visibility/ordering via drag-and-drop editor, new business profile fields, and dual rendering (full PDF + simplified email body).

Template _selection_ (multiple named templates) is out of scope — that belongs to future phases.

</domain>

<decisions>
## Implementation Decisions

### Rendering engine
- Keep HTML/CSS → mPDF pipeline (no engine change)
- `PdfService.php` extended, not replaced
- New template file replaces `templates/pdf/invoice-default.php`

### Template count and structure
- Single configurable template (not a library of templates)
- Sections can be shown/hidden and reordered via admin editor
- All configuration stored in `invoiceforge_settings` (existing WordPress options key)

### Logo
- Position: Top left of the SELLER section (above/beside company name in the header)
- Upload mechanism: Dedicated file input in Settings page (NOT WordPress media library)
- Stored in `wp-content/uploads/invoiceforge/` alongside other plugin uploads
- Falls back gracefully if no logo set (section simply omits the image)

### Visual style
- Formal document base (black/white, bold section headers, simple table borders — matches reference PDF)
- Configurable accent color (admin sets hex value in Settings → General)
- Accent color applied to: section header backgrounds, totals block, column headers
- Default accent: professional dark navy

### Section visibility
- Always visible (EU VAT Directive mandatory): Invoice number/date, Buyer/Seller details, line items, VAT amount, total
- Conditionally shown by default:
  - VAT note ("VAT not charged…" / reverse charge text) — shown when invoice is VAT-exempt or reverse charge applies
  - Bank/IBAN section — shown when payment method = Bank transfer
- Admin-toggleable (optional):
  - Signature block (ATTENDED TO / COMPILER)
  - Whole document discount row (also auto-hidden when discount = 0)
  - Additional notes section

### Section ordering
- Admin can reorder sections via drag-and-drop visual editor in Settings
- Editor shows a live preview or ordered list of section blocks
- Order persisted in `invoiceforge_settings`

### Discount display in line items table
- Discount columns (% and value) shown only when at least one line item has a non-zero discount
- Whole document discount row hidden when value = 0

### Notes placement
- Additional notes appear below the full footer (full page width), matching reference PDF

### Signature block
- Fields are configurable — admin can add/remove fields
- Default fields match reference PDF: Date, Place (left column); Compiler, Personal code, Attended to (right column)
- Field labels are editable

### New buyer/seller profile fields
All added to both the Client record AND the Settings company profile:
- **ID No** — label is configurable per-install (e.g., "EIK", "BULSTAT", "Reg No", "VAT No 2")
- **Office** — office/branch identifier
- **Att To** — attention to (contact name)

### Payment methods
- Configurable list defined in Settings (admin adds/removes options, e.g., "Bank transfer", "Cash", "Credit card")
- Selectable per invoice (dropdown)
- Bank/IBAN footer section auto-shows when "Bank transfer" is selected

### Email vs PDF rendering
- Shared base template logic, rendered differently by mode flag
- **PDF mode:** Full template with all configured sections
- **Email mode:** Simplified summary body (invoice number, client, total, due date, line items summary) + "Pay Invoice" button (placeholder for Phase 8 payment gateway link) + PDF invoice attached
- Email HTML body avoids mPDF — rendered as standard HTML for email clients

### Language / i18n
- All template labels use `__()` translation functions
- Translates automatically with WordPress locale
- Bulgarian translations already in place in plugin (`invoiceforge-bg_BG.po`)

### Claude's Discretion
- Exact HTML/CSS layout details within the reference PDF structure
- Drag-and-drop JS library choice (sortable.js or similar)
- mPDF configuration tweaks for table rendering
- Responsive behavior of the admin section editor
- How section config is serialized in `invoiceforge_settings`

</decisions>

<specifics>
## Specific Ideas

- Reference PDF: Bulgarian business invoice format (uploaded 2026-03-19). Key visual: three-column header (BUYER | No+Date | SELLER), line items table with DISCOUNT % and value columns, two-column footer (payment info left | totals right), ATTENDED TO / COMPILER signature block at bottom, Additional Notes below.
- "I want sections to be customizable and moveable" — drag-and-drop editor in admin
- Email body should show a "Pay Invoice" button even before Phase 8 gateways exist (placeholder that will be wired up in Phase 8)
- The ID No field is specifically for EIK/BULSTAT in the Bulgarian context but should be generically labeled with a configurable name

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `src/Services/PdfService.php`: Existing mPDF wrapper — extend `render()` to accept mode flag (pdf|email). Already handles mPDF instantiation and memory management.
- `templates/pdf/invoice-default.php`: Current template to be replaced. PdfService `include`s this file.
- `src/Services/EmailService.php`: Sends email with optional PDF attachment via `wp_mail`. Extend to generate simplified HTML body from template.
- `src/Security/Encryption.php`: Not relevant here, but the Settings pattern it follows (stored in `invoiceforge_settings`) applies to new config fields.
- `src/Admin/Pages/SettingsPage.php`: Add new tabs/sections: logo upload, accent color, ID field label, payment methods list, section visibility toggles, section order editor.
- `assets/admin/js/admin.js`: Add drag-and-drop section editor JS (needs sortable library or native HTML5 drag events).

### Established Patterns
- Settings stored as array under `invoiceforge_settings` WP option — all new template config follows this pattern
- AJAX pattern: `wp_ajax_invoiceforge_*` hooks with nonce + capability check — used for saving section order/config
- Template files: PHP included by service, data passed as variables — new template follows same pattern

### Integration Points
- `PdfService` → `templates/pdf/invoice-default.php` (render PDF)
- `EmailService` → generates email HTML body + attaches PDF
- `SettingsPage` → new Settings tabs for template configuration
- `ClientPostType` / client post meta → new fields: `_client_id_no`, `_client_office`, `_client_att_to`
- `invoiceforge_settings` → new keys: `logo_path`, `accent_color`, `id_no_label`, `payment_methods`, `section_visibility`, `section_order`, `signature_fields`

</code_context>

<deferred>
## Deferred Ideas

- Multiple named templates (template library / template picker) — future advanced-templates expansion
- Visual template customizer beyond section order (font picker, margin control) — future phase
- Per-invoice template override — future phase

</deferred>

---

*Phase: 03-advanced-templates*
*Context gathered: 2026-03-19*
