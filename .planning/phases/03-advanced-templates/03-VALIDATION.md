---
phase: 3
slug: advanced-templates
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-19
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | grep/test commands (no test suite — 0% coverage baseline) |
| **Config file** | none |
| **Quick run command** | `grep -n` pattern checks per task |
| **Full suite command** | per-task automated verify commands |
| **Estimated runtime** | ~1 second |

---

## Sampling Rate

- **After every task commit:** Run task's `<automated>` verify command
- **After every plan wave:** Run all verify commands for wave's tasks
- **Before `/gsd:verify-work`:** All automated verify commands green + human checkpoint (Plan 03-03 Task 2)
- **Max feedback latency:** 2 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | Status |
|---------|------|------|-------------|-----------|-------------------|--------|
| 03-01-T1 | 01 | 1 | TMPL-01, TMPL-03 | grep | `grep -n "discount_type\|discount_value" src/Models/LineItem.php && grep -n "_client_id_no" src/PostTypes/ClientPostType.php && grep -n "payment_method" templates/admin/invoice-editor.php` | ⬜ pending |
| 03-01-T2 | 01 | 1 | TMPL-02 | grep | `grep -n "template.*TAB_FIELDS\|_template_tab_marker\|accent_color" src/Admin/Pages/SettingsPage.php` | ⬜ pending |
| 03-01-T3 | 01 | 1 | TMPL-02 | grep | `grep -n "enctype\|multipart" templates/admin/settings.php && test -f assets/admin/js/sortable.min.js && grep -n "initSectionEditor" assets/admin/js/admin.js` | ⬜ pending |
| 03-02-T1 | 02 | 2 | TMPL-05 | grep | `grep -n "render_mode\|getTemplateContext\|renderEmailBody" src/Services/PdfService.php` | ⬜ pending |
| 03-02-T2 | 02 | 2 | TMPL-04 | grep+test | `test -f templates/pdf/invoice-default.php && grep -c "render_mode\|accent_color\|section_order\|esc_html\|__(" templates/pdf/invoice-default.php` | ⬜ pending |
| 03-03-T1 | 03 | 3 | TMPL-06 | grep | `grep -n "text/html\|renderEmailBody" src/Services/EmailService.php` | ⬜ pending |
| 03-03-T2 | 03 | 3 | Visual | checkpoint | Human visual verification of PDF + email output | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. Automated verification uses grep/test commands that require no framework installation.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| PDF visual layout matches reference | Template fidelity | Visual comparison required | Generate PDF, compare side-by-side with Bulgarian reference PDF |
| Email renders in Gmail/Outlook | Email compat | Client-specific rendering | Send test email, verify in at least 2 clients |
| Drag-and-drop section reorder | UX interaction | Browser interaction | Open Settings > Template tab, drag sections, save, verify order persisted |
| Logo displays in PDF at correct position | Logo rendering | mPDF visual output | Upload logo, generate PDF, verify top-left of seller section |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or checkpoint type
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] No MISSING references requiring Wave 0
- [x] No watch-mode flags
- [x] Feedback latency < 2s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-03-19
