---
phase: 03-advanced-templates
plan: 01
subsystem: ui
tags: [wordpress, php, settings, sortablejs, line-items, meta-fields]

# Dependency graph
requires: []
provides:
  - LineItem model with discount_type and discount_value properties mapped from DB columns
  - ClientPostType saves and renders _client_id_no, _client_office, _client_att_to meta fields
  - InvoicePostType saves _invoice_payment_method from POST data
  - SettingsPage Template tab with logo upload, accent color, payment methods, section visibility, section order, and signature field config
  - SortableJS v1.15.6 bundled and enqueued on settings page
  - Company profile fields (id_no, office, att_to, bank_name, iban, bic) in General settings tab
affects:
  - 03-02 (template rendering depends on these data fields and settings)
  - 03-03 (email rendering depends on template settings and client/invoice fields)

# Tech tracking
tech-stack:
  added: [sortablejs@1.15.6]
  patterns:
    - Sentinel marker pattern for identifying active settings tab without URL params
    - Deep-merge of nested settings sub-arrays to preserve individual template config keys
    - File upload handling via wp_handle_upload() inside sanitizeSettings()
    - Repeater UI pattern (add/remove rows via JS) for payment methods and signature fields
    - SortableJS drag-and-drop with onEnd re-indexing of hidden inputs

key-files:
  created:
    - assets/admin/js/sortable.min.js
    - .planning/phases/03-advanced-templates/03-01-SUMMARY.md
  modified:
    - src/Models/LineItem.php
    - src/Repositories/LineItemRepository.php
    - src/PostTypes/ClientPostType.php
    - src/PostTypes/InvoicePostType.php
    - src/Admin/Pages/SettingsPage.php
    - src/Admin/Assets.php
    - templates/admin/invoice-editor.php
    - templates/admin/client-editor.php
    - templates/admin/settings.php
    - assets/admin/js/admin.js
    - assets/admin/css/admin.css

key-decisions:
  - "Sentinel marker _template_tab_marker used in TAB_FIELDS to identify template tab saves without relying on $_GET['tab']"
  - "Template settings stored as nested array invoiceforge_settings[template] - requires deep-merge in getSettings() to avoid overwriting sub-keys"
  - "SortableJS bundled locally (not CDN) for offline/air-gapped compatibility"
  - "Payment method dropdown on invoice editor loads from settings with fallback to ['Bank transfer', 'Cash']"
  - "Client new fields (id_no, office, att_to) added to existing billing meta box to avoid adding a 4th meta box"

patterns-established:
  - "Sentinel marker pattern: TAB_FIELDS['template'] = ['_template_tab_marker'] triggers template branch in sanitizeSettings"
  - "Nested settings sub-array: array_merge($defaults['template'], $saved['template']) for deep merge"
  - "Repeater pattern: ul/li with hidden inputs for ordered arrays, JS add/remove for unordered arrays"

requirements-completed:
  - TMPL-01
  - TMPL-02
  - TMPL-03

# Metrics
duration: 27min
completed: 2026-03-19
---

# Phase 3 Plan 01: Data Models and Template Settings Foundation Summary

**Extended LineItem, Client, Invoice data models and built full Template Settings tab with SortableJS drag-and-drop section ordering, logo upload, and repeater UIs for payment methods and signature fields**

## Performance

- **Duration:** 27 min
- **Started:** 2026-03-19T20:54:25Z
- **Completed:** 2026-03-19T21:20:53Z
- **Tasks:** 3
- **Files modified:** 11

## Accomplishments

- LineItem model now exposes discount_type and discount_value from existing DB columns; LineItemRepository saves them in INSERT/UPDATE
- ClientPostType saves/renders three new meta fields (_client_id_no, _client_office, _client_att_to); client-editor.php shows them in the billing card
- SettingsPage gained a full Template tab: logo upload (wp_handle_upload), accent color picker with hex sync, payment methods repeater, section visibility checkboxes, SortableJS drag-and-drop section ordering, and signature fields repeater
- Six new company profile fields (id_no, office, att_to, bank_name, iban, bic) registered in General tab with sanitization
- SortableJS v1.15.6 bundled locally and enqueued only on settings page; admin.js wired with initSectionEditor(), initPaymentMethodsRepeater(), initSignatureFieldsRepeater()

## Task Commits

Each task was committed atomically:

1. **Task 1: Extend data models with discount and profile fields** - `f51e560` (feat)
2. **Task 2: Add Template settings backend** - `47e5f81` (feat)
3. **Task 3: Build Template tab UI, SortableJS, admin JS/CSS** - `126101a` (feat)

## Files Created/Modified

- `src/Models/LineItem.php` - Added discount_type/discount_value properties, fromRow/fromArray/toArray mappings
- `src/Repositories/LineItemRepository.php` - Added discount columns to INSERT/UPDATE data array
- `src/PostTypes/ClientPostType.php` - Added id_no, office, att_to to save fields and renderBillingMetaBox
- `src/PostTypes/InvoicePostType.php` - Added _invoice_payment_method save logic
- `src/Admin/Pages/SettingsPage.php` - Template tab in TAB_FIELDS/getTabs, template defaults, sanitizeTemplateSettings(), 6 new general fields
- `src/Admin/Assets.php` - Enqueue invoiceforge-sortable on settings page
- `templates/admin/invoice-editor.php` - Payment method dropdown loaded from settings
- `templates/admin/client-editor.php` - Three new fields (id_no, office, att_to) in billing card
- `templates/admin/settings.php` - enctype added, full Template tab HTML block
- `assets/admin/js/admin.js` - initSectionEditor, initPaymentMethodsRepeater, initSignatureFieldsRepeater
- `assets/admin/css/admin.css` - Section order list, drag handle, ghost state, repeater row styles
- `assets/admin/js/sortable.min.js` - SortableJS v1.15.6 bundled (created)

## Decisions Made

- Used sentinel marker `_template_tab_marker` in TAB_FIELDS to identify template tab saves, keeping the existing tab-detection logic intact
- Template settings stored as a nested sub-array under `invoiceforge_settings['template']`; added deep-merge in getSettings() to prevent array_merge from overwriting the entire sub-array with partial saves
- SortableJS bundled locally rather than loaded from CDN for compatibility with offline/firewalled admin environments
- Client new fields added to existing Billing Information meta box rather than creating a fourth meta box, keeping the UI clean
- Payment method dropdown on invoice editor has a fallback to ['Bank transfer', 'Cash'] so it works before settings are configured

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All data contracts are in place for Plan 02 (template rendering) and Plan 03 (email rendering)
- Template settings tab is functional; settings persist correctly on save
- Downstream plans can read `invoiceforge_settings['template']` for logo_url, accent_color, section_order, payment_methods, signature_fields

---
*Phase: 03-advanced-templates*
*Completed: 2026-03-19*
