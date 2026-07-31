---
phase: quick-260730-uzx
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/filament/components/saldos-badge.blade.php
autonomous: true
requirements: [UZX-01]
must_haves:
  truths:
    - "The Saldos dropdown trigger has visibly larger spacing from the adjacent Cambiar button in the topbar"
  artifacts:
    - path: "resources/views/filament/components/saldos-badge.blade.php"
      provides: "Saldos badge dropdown with ms-4 spacing"
      contains: 'class="ms-4"'
  key_links:
    - from: "resources/views/filament/components/saldos-badge.blade.php"
      to: "topbar layout"
      via: "x-filament::dropdown margin class"
      pattern: 'class="ms-4"'
---

<objective>
Increase the horizontal spacing between the "Saldos" dropdown trigger and the adjacent orange "Cambiar" button in the topbar. The current `ms-2` (0.5rem/8px) margin is too small — the user confirmed via screenshot the elements are still "muyyyy pegado" (way too close). Bump to `ms-4` (1rem/16px).

Purpose: Restore clear visual separation in the topbar so the Saldos icon does not appear stuck to the Cambiar button.
Output: Updated `saldos-badge.blade.php` with a single class-name change.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/filament/components/saldos-badge.blade.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Bump saldos-badge margin from ms-2 to ms-4</name>
  <files>resources/views/filament/components/saldos-badge.blade.php</files>
  <action>On line 25, change the `class="ms-2"` attribute to `class="ms-4"` on the `<x-filament::dropdown id="saldos-badge" ...>` tag. The line becomes:
`<x-filament::dropdown id="saldos-badge" class="ms-4" width="xs" placement="bottom-end" teleport="true">`
Do not modify any other attribute, line, or content in the file. This is a single class-name change only.</action>
  <verify>grep -n 'class="ms-4"' resources/views/filament/components/saldos-badge.blade.php returns line 25; grep -n 'class="ms-2"' returns no match</verify>
  <done>Line 25 of saldos-badge.blade.php reads `class="ms-4"` on the dropdown tag; no `ms-2` remains in the file; rest of file unchanged</done>
</task>

</tasks>

<verification>
- `grep -n 'ms-4' resources/views/filament/components/saldos-badge.blade.php` confirms the new class is present on the dropdown tag.
- `grep -n 'ms-2' resources/views/filament/components/saldos-badge.blade.php` returns nothing.
- After `npm run build` (or dev server running), the topbar shows visibly larger spacing between the Saldos icon and the Cambiar button.
</verification>

<success_criteria>
- The `<x-filament::dropdown id="saldos-badge">` tag uses `class="ms-4"`.
- No other changes were made to the file.
</success_criteria>

<output>
After completion, create `.planning/quick/260730-uzx-increase-saldos-badge-topbar-spacing-fro/260730-uzx-SUMMARY.md`
</output>
