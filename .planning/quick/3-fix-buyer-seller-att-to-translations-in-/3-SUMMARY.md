---
phase: quick
plan: 3
subsystem: i18n / translations
tags: [bugfix, translation, bulgarian, locale, i18n]
dependency_graph:
  requires: []
  provides: [correct-bg-BG-invoice-terms]
  affects: [invoice-pdf-template, invoice-email-template]
tech_stack:
  added: []
  patterns: [GNU gettext PO/MO format, compile-mo.cjs binary recompile]
key_files:
  modified:
    - languages/invoiceforge-bg_BG.po
    - languages/invoiceforge-bg_BG.mo
decisions:
  - "Used standard Bulgarian accounting terms: ПОЛУЧАТЕЛ (buyer/recipient), ДОСТАВЧИК (seller/supplier), МОЛ (financially responsible person)"
metrics:
  duration: "< 5 minutes"
  completed: "2026-03-20"
  tasks_completed: 1
  tasks_total: 1
  files_modified: 2
---

# Quick Task 3: Fix Bulgarian Invoice Terms Summary

**One-liner:** Replaced three informal Bulgarian translations with standard accounting terms (ПОЛУЧАТЕЛ, ДОСТАВЧИК, МОЛ) and recompiled the .mo binary.

## What Was Done

Corrected three msgstr values in `languages/invoiceforge-bg_BG.po` that were using informal/literal Bulgarian words instead of the standard accounting terminology required on Bulgarian business invoices:

| msgid | Before | After | Meaning |
|-------|--------|-------|---------|
| BUYER | КУПУВАЧ | ПОЛУЧАТЕЛ | Recipient / buyer (standard invoice term) |
| SELLER | ПРОДАВАЧ | ДОСТАВЧИК | Supplier / seller (standard invoice term) |
| Att To | На вниманието на | МОЛ | Материално-отговорно лице (financially responsible person) |

After editing the .po source, `node scripts/compile-mo.cjs` was run to recompile all locale .mo binaries. The bg_BG locale compiled to 148 strings / 8695 bytes successfully.

## Tasks

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Fix Bulgarian invoice terms and recompile .mo | 6bdba85 | languages/invoiceforge-bg_BG.po, languages/invoiceforge-bg_BG.mo |

## Verification

- .po file confirmed: `msgstr "ПОЛУЧАТЕЛ"` for `msgid "BUYER"`
- .po file confirmed: `msgstr "ДОСТАВЧИК"` for `msgid "SELLER"`
- .po file confirmed: `msgstr "МОЛ"` for `msgid "Att To"`
- compile-mo.cjs exited with code 0; bg_BG.mo recompiled at 8695 bytes

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

- `languages/invoiceforge-bg_BG.po` exists and contains all three corrected terms
- `languages/invoiceforge-bg_BG.mo` exists (recompiled by compile-mo.cjs)
- Commit `6bdba85` confirmed in git log
