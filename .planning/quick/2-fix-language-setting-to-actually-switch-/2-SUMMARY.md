---
phase: quick-2
plan: "01"
subsystem: i18n / translations
tags: [translations, i18n, gettext, po, mo, languages]
dependency_graph:
  requires: []
  provides: [languages/invoiceforge-*.mo]
  affects: [src/Core/Plugin.php loadTextDomain, invoice PDF labels, email template labels, settings UI]
tech_stack:
  added: [scripts/compile-mo.cjs (Node.js .mo binary compiler)]
  patterns: [GNU gettext .po/.mo format, load_plugin_textdomain]
key_files:
  created:
    - languages/invoiceforge-de_DE.po
    - languages/invoiceforge-de_DE.mo
    - languages/invoiceforge-fr_FR.po
    - languages/invoiceforge-fr_FR.mo
    - languages/invoiceforge-es_ES.po
    - languages/invoiceforge-es_ES.mo
    - languages/invoiceforge-it_IT.po
    - languages/invoiceforge-it_IT.mo
    - languages/invoiceforge-nl_NL.po
    - languages/invoiceforge-nl_NL.mo
    - languages/invoiceforge-pl_PL.po
    - languages/invoiceforge-pl_PL.mo
    - languages/invoiceforge-pt_PT.po
    - languages/invoiceforge-pt_PT.mo
    - languages/invoiceforge-ro_RO.po
    - languages/invoiceforge-ro_RO.mo
    - languages/invoiceforge-ru_RU.po
    - languages/invoiceforge-ru_RU.mo
    - scripts/compile-mo.cjs
  modified:
    - languages/invoiceforge-bg_BG.po
    - languages/invoiceforge-bg_BG.mo
decisions:
  - "compile-mo.cjs uses zero npm dependencies: pure Node.js Buffer writes the .mo binary following the GNU gettext little-endian spec"
  - "Strings are sorted by original string byte order as required by the .mo spec"
  - "Duplicate msgid entries (Invoice, Due Date) from prior bg_BG.po kept at original location; new entries omitted to avoid parser conflicts"
metrics:
  duration: "~15 minutes"
  completed_date: "2026-03-20"
  tasks_completed: 2
  files_changed: 21
---

# Quick Task 2: Fix Language Setting to Actually Switch - Summary

**One-liner:** Complete GNU gettext .po/.mo translation files for all 10 supported locales (bg_BG + 9 others), compiled via a dependency-free Node.js .mo binary writer.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Update Bulgarian .po and create all locale .po files | 887ef76 | 10 .po files |
| 2 | Compile all .po files to .mo binary format | 3bb670e | scripts/compile-mo.cjs + 10 .mo files |

## What Was Built

**Task 1 — Translation source files (.po)**

- Updated `languages/invoiceforge-bg_BG.po`: added all missing invoice/email template strings (BUYER, SELLER, INVOICE, column headers, totals, payment section, signatures, Amount Due, Pay Invoice) and language name strings for all 9 other locales.
- Created .po files for: de_DE (German), fr_FR (French), es_ES (Spanish), it_IT (Italian), nl_NL (Dutch), pl_PL (Polish), pt_PT (Portuguese), ro_RO (Romanian), ru_RU (Russian).
- Each file contains 148 translated strings covering: dashboard, invoices, clients, addresses, settings, actions, messages, countries, empty states, and invoice/email template labels.
- Tax terminology uses locale conventions: MwSt (German), TVA (French/Romanian), IVA (Spanish/Italian/Portuguese), BTW (Dutch), VAT/podatek (Polish), НДС (Russian).
- Plural-Forms are correct per locale: Western European use nplurals=2; Polish and Russian use nplurals=3 with correct complex plural rules; Romanian uses nplurals=3 with its own rule.

**Task 2 — Binary .mo files and compiler**

- Created `scripts/compile-mo.cjs`: a zero-dependency Node.js script implementing the GNU gettext .mo binary format.
  - Parser handles multiline strings (continuation lines starting with `"`), escape sequences (`\n`, `\t`, `\\`, `\"`), and skips untranslated entries.
  - Binary writer builds the 28-byte header, two offset tables (originals + translations), and null-terminated string data; strings sorted by byte order as the spec requires.
- Compiled all 10 .po files to .mo; each file: 148 strings, 7013–8807 bytes, valid magic number 0x950412de.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Removed duplicate msgid entries in bg_BG.po**
- **Found during:** Task 1
- **Issue:** `msgid "Invoice"` and `msgid "Due Date"` already existed in the original bg_BG.po at lines 48 and 66. The plan's additions would have created duplicates which cause .mo parser warnings.
- **Fix:** Removed the redundant `msgid "Invoice"` and `msgid "Due Date"` from the newly added "Invoice/Email Template" section (kept the originals that already had correct Bulgarian translations).
- **Files modified:** languages/invoiceforge-bg_BG.po
- **Commit:** 887ef76

## Verification Results

All automated checks passed:

- All 10 .po files contain required strings: BUYER, SELLER, INVOICE, Description, Subtotal, Payment Details, Signatures.
- All 10 .mo files exist with valid magic number (0x950412de) and size > 100 bytes.
- `src/Core/Plugin.php` is unchanged — `loadTextDomain()` continues to call `load_plugin_textdomain('invoiceforge', false, dirname(plugin_basename(__FILE__)) . '/languages/')` which will now find the compiled .mo files for any of the 10 supported locales.

## Self-Check: PASSED

Files confirmed present:
- languages/invoiceforge-de_DE.po: FOUND
- languages/invoiceforge-de_DE.mo: FOUND
- languages/invoiceforge-ru_RU.po: FOUND
- languages/invoiceforge-ru_RU.mo: FOUND
- scripts/compile-mo.cjs: FOUND

Commits confirmed:
- 887ef76: feat(quick-2): create complete .po translation files for all 10 locales
- 3bb670e: feat(quick-2): add .mo compiler script and compile all 10 locale .mo files
