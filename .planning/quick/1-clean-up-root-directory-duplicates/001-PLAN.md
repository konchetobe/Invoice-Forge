---
phase: quick-1-clean-up-root-directory-duplicates
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - AGENTS.pdf
  - ROADMAP.pdf
  - ROADMAP.md
autonomous: true
requirements: [CLEANUP-01]

must_haves:
  truths:
    - "Root directory contains no PDF files"
    - "Root ROADMAP.md no longer exists"
    - ".planning/ROADMAP.md remains intact and is the authoritative roadmap"
  artifacts:
    - path: ".planning/ROADMAP.md"
      provides: "Authoritative project roadmap (must survive deletion)"
  key_links:
    - from: "root directory"
      to: ".planning/ROADMAP.md"
      via: "No duplicate ROADMAP.md at root causes confusion"
      pattern: "ROADMAP.md exists only in .planning/"
---

<objective>
Remove three redundant files from the repository root: AGENTS.pdf (PDF export of AGENTS.md), ROADMAP.pdf (PDF export of .planning/ROADMAP.md), and the root ROADMAP.md (superseded by .planning/ROADMAP.md).

Purpose: Eliminate stale duplicates that create confusion about which ROADMAP.md is authoritative and that unnecessarily bloat the repository with binary PDF files.
Output: Clean root directory; .planning/ROADMAP.md remains as the sole roadmap.
</objective>

<execution_context>
@C:/Users/Ananaska/.claude/get-shit-done/workflows/execute-plan.md
@C:/Users/Ananaska/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/ROADMAP.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Delete root-level duplicate files</name>
  <files>AGENTS.pdf, ROADMAP.pdf, ROADMAP.md</files>
  <action>
    Delete the following three files from the repository root using git rm so the removal is tracked:

    ```bash
    cd C:/GitHubRepos/Invoice-Forge
    git rm AGENTS.pdf ROADMAP.pdf ROADMAP.md
    ```

    All three files are confirmed present:
    - AGENTS.pdf (55 KB) — binary PDF export of AGENTS.md; the source AGENTS.md remains at root
    - ROADMAP.pdf (52 KB) — binary PDF export; .planning/ROADMAP.md is the authoritative copy
    - ROADMAP.md (root, 15 KB) — superseded; .planning/ROADMAP.md is the live planning document

    Do NOT delete:
    - AGENTS.md (the actual AI instructions file, kept at root)
    - .planning/ROADMAP.md (authoritative roadmap, must remain)

    After deletion verify .planning/ROADMAP.md still exists:
    ```bash
    test -f .planning/ROADMAP.md && echo "OK: .planning/ROADMAP.md intact" || echo "ERROR: missing"
    ```
  </action>
  <verify>
    <automated>cd C:/GitHubRepos/Invoice-Forge && git status --short | grep -E "^D.*(AGENTS\.pdf|ROADMAP\.pdf|ROADMAP\.md)" | wc -l</automated>
  </verify>
  <done>
    All three files removed from git index; root contains no .pdf files and no ROADMAP.md; .planning/ROADMAP.md exists and is unmodified.
  </done>
</task>

</tasks>

<verification>
After task completes, confirm:
- `ls C:/GitHubRepos/Invoice-Forge/*.pdf` returns no results
- `ls C:/GitHubRepos/Invoice-Forge/ROADMAP.md` returns no such file
- `ls C:/GitHubRepos/Invoice-Forge/.planning/ROADMAP.md` returns the file
- `git status` shows three deletions staged
</verification>

<success_criteria>
Root directory has no PDF files and no ROADMAP.md. The file .planning/ROADMAP.md remains intact as the sole authoritative roadmap. Changes are staged for commit.
</success_criteria>

<output>
After completion, create `.planning/quick/1-clean-up-root-directory-duplicates/quick-1-001-SUMMARY.md`
</output>
