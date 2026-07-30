---
phase: quick
plan: 260730-g2k
subsystem: filament-coordinators
tags: [filament, table, column-toggle, coordinators]
dependency-graph:
  requires: []
  provides:
    - "CoordinatorsTable column toggling matching LeadersTable/VotersTable pattern (260730-g0h)"
  affects:
    - app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php
tech-stack:
  added: []
  patterns:
    - "Filament TextColumn ->toggleable() / ->toggleable(isToggledHiddenByDefault: true)"
key-files:
  created: []
  modified:
    - app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php
decisions: []
metrics:
  duration: 5min
  completed: 2026-07-30
---

# Quick Task 260730-g2k: Add column toggle to CoordinatorsTable Summary

Added `->toggleable()` to all 5 columns of `CoordinatorsTable`, hiding Correo (email) and Creado (created_at) by default — the same column-visibility pattern just applied to LeadersTable/VotersTable in quick task 260730-g0h, per explicit user request that hidden columns apply to coordinators too.

## What Was Built

`app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php`:

- `name` (Nombre): `->toggleable()` — visible by default
- `email` (Correo): `->toggleable(isToggledHiddenByDefault: true)` — hidden by default
- `municipality.name` (Municipio): `->toggleable()` — visible by default
- `leaders_count` (Líderes): `->toggleable()` added alongside its existing `->visible(fn (): bool => Schema::hasColumn('users', 'coordinator_user_id'))` conditional, which was left untouched
- `created_at` (Creado): `->toggleable(isToggledHiddenByDefault: true)` — hidden by default

No other change made to the file. `searchable()`, `sortable()`, `copyable()`, `placeholder()`, and the `Schema::hasColumn` visibility check all stay exactly as they were.

## Verification

- `grep -c "toggleable" app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php` returns `5`
- `vendor/bin/pint --dirty` ran clean (no style changes needed)
- `git status --short` confirmed no other files were touched — the concurrently-running task's files (`LeadersTable.php`, `VotersTable.php`) were not present as dirty and were not modified

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

- FOUND: app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php
- FOUND: commit 56dd6a5
