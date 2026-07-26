---
phase: quick-260726-kg8
plan: 01
subsystem: ui
tags: [livewire, volt, morphdom, wire-key, filament-forms, blade]

requires: []
provides:
  - "Stable wire:key wrapper around the conditional Registraduría/census banner on register-voter.blade.php and create-leader.blade.php"
  - "Regression tests documenting sibling-field independence after a document_number blur, with explicit server-side testing limitation notes"
affects: [leader-register-voter, coordinator-create-leader]

tech-stack:
  added: []
  patterns:
    - "wire:key on a wrapper div around a conditionally-rendered element that sits between two form inputs, to keep Livewire morphdom from losing sibling identity for the DOM nodes that follow it"

key-files:
  created: []
  modified:
    - resources/views/livewire/leader/register-voter.blade.php
    - resources/views/livewire/coordinator/create-leader.blade.php
    - tests/Feature/Leader/RegisterVoterRegistraduriaLookupTest.php
    - tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php

key-decisions:
  - "Used a static wire:key=\"document-status-banner\" (no interpolation) since each form has exactly one instance of this banner per component — matches the codebase's only other wire:key precedent (calls/queue.blade.php's per-row key) in style but not in structure."
  - "Wrapped the existing @if/@elseif block unchanged inside the new div rather than restructuring the conditional itself, per the plan's explicit no-other-markup-changes constraint."
  - "New regression tests exercise Livewire::test()->set() sibling-field assignments (first_name/last_name/birth_date, email/password) as a safety net, but explicitly document in both a code comment and the test name that this cannot reproduce the actual browser morphdom bug — that requires manual/Playwright browser verification."

requirements-completed: []

duration: 8min
completed: 2026-07-26
---

# Quick Task 260726-kg8: Fix Livewire DOM-morph field-value bleed Summary

**Wrapped the Registraduría/census status banner in a stable `wire:key="document-status-banner"` div on both the líder register-voter and coordinador create-leader forms, so Livewire's morphdom stops losing sibling identity for the fields that follow it (Nombres/Apellidos/Fecha de Nacimiento, or Correo/Contraseña) whenever the banner appears or disappears on a cédula blur.**

## Performance

- **Duration:** 8 min
- **Started:** 2026-07-26T19:46:00Z
- **Completed:** 2026-07-26T19:47:30Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- Both blade views now wrap the conditional `@if($registraduriaVerified) ... @elseif($censusNotFoundWarning) ... @endif` banner in a single, identity-stable `<div wire:key="document-status-banner">`, present on every render regardless of which (if any) branch is active.
- Added regression tests to both existing lookup test files asserting sibling fields (first_name/last_name/birth_date on the líder form; email/password on the coordinador form) keep their own independently-set values after a Registraduría-verified document_number blur, and that birth_date stays null when untouched.
- Both new tests carry an explicit code comment (and self-documenting test name) stating that `Livewire::test()`'s `->set()` calls operate purely on server-side component state and cannot exercise the browser's morphdom algorithm — real confirmation requires a manual/Playwright browser session.

## Task Commits

1. **Task 1: Wrap the conditional Registraduría/census banner in a stable wire:key container on both forms** - `0271dfa` (fix)
2. **Task 2: Add regression tests documenting the fix and its verification limits; run Pint** - `167ccc8` (test)

_Note: This plan's tasks did not use TDD; each commit corresponds 1:1 to the plan's task list._

## Files Created/Modified
- `resources/views/livewire/leader/register-voter.blade.php` - Wrapped the banner block in `<div wire:key="document-status-banner">`, no other markup changed.
- `resources/views/livewire/coordinator/create-leader.blade.php` - Same wrapper applied to the identical banner pattern between Número de Documento and Correo Electrónico.
- `tests/Feature/Leader/RegisterVoterRegistraduriaLookupTest.php` - New regression test for first_name/last_name/birth_date independence after a verified-document blur.
- `tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php` - New regression test for email/password independence after a verified-document blur.

## Decisions Made
- Static (non-interpolated) `wire:key` value chosen because each Volt component instance renders the banner at most once — no per-row/per-item identity needed, and no key-collision risk since each form is a separate Livewire component instance.
- Did not add `wire:key` to the individual first_name/last_name/birth_date/email/password inputs — the wrapper alone removes the ambiguous sibling position that caused the bug, avoiding an untested, unnecessary second change to already-working inputs.

## Deviations from Plan

None - plan executed exactly as written. Both tasks were implemented per the plan's exact instructions (wrapper div placement, static key value, test additions with explicit limitation comments), and `vendor/bin/pint --dirty` reported no style issues.

## Issues Encountered
None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Automated scope of this fix is complete and verified (grep confirms exactly one `wire:key="document-status-banner"` per file; both test files pass in full; Pint is clean). Per the plan's explicit verification limitations, the orchestrator still needs to perform a real browser check (type a cédula that triggers the green or amber banner, Tab out, type into Nombres/Apellidos with real keyboard events, save) to confirm the actual DOM-morph value-bleed no longer occurs client-side — Livewire::test() cannot exercise this.

---
*Phase: quick-260726-kg8*
*Completed: 2026-07-26*

## Self-Check: PASSED

All created/modified files confirmed present on disk; both task commits (`0271dfa`, `167ccc8`) confirmed present in git history.
