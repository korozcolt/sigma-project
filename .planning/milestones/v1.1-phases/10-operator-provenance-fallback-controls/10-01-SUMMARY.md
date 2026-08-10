---
phase: 10-operator-provenance-fallback-controls
plan: 01
subsystem: filament-voters
tags: [filament, voters, provenance, badge, filter]
requires: []
provides:
  - "Voters table polling_place_source badge column + resolved-at column + multi-select filter"
  - "ViewVoter infolist polling_place_source badge entry + resolved-at entry"
affects:
  - app/Filament/Resources/Voters/Tables/VotersTable.php
  - app/Filament/Resources/Voters/Pages/ViewVoter.php
tech-stack:
  added: []
  patterns:
    - "Filament badge columns/entries auto-resolve color/icon/label from an enum implementing HasColor/HasIcon/HasLabel — no closure needed, unlike VoterStatus which requires a manual ->color() match."
key-files:
  created: []
  modified:
    - app/Filament/Resources/Voters/Tables/VotersTable.php
    - app/Filament/Resources/Voters/Pages/ViewVoter.php
    - tests/Feature/Filament/VoterResourceTest.php
decisions:
  - "No new color/label mapping code added anywhere — PollingPlaceSource's existing HasColor/HasIcon/HasLabel contracts are consumed directly by ->badge(), exactly as planned."
metrics:
  duration: 10min
  completed: 2026-07-25
---

# Phase 10 Plan 01: Operator Provenance Display (Table + Infolist) Summary

Voters table and the Voter view page now surface `polling_place_source` (En Vivo / Reconstruido en Base de Datos / Snapshot Nacional / Manual) as a colored badge plus its `polling_place_resolved_at` freshness timestamp, and operators can multi-select filter the table down to any subset of sources including the three fallback (non-live) ones.

## What Was Built

- **`VotersTable.php`**: added `polling_place_source` badge `TextColumn` (sortable, toggleable) right after the `status` column; added `polling_place_resolved_at` date `TextColumn` (`d/m/Y H:i`, sortable, toggleable, hidden by default) right after `census_validated_at`; added `polling_place_source` multi-select `SelectFilter` (`->options(PollingPlaceSource::class)->multiple()->preload()`) right after the `status` filter. Imported `App\Enums\PollingPlaceSource` alphabetically before `UserRole`.
- **`ViewVoter.php`**: added `polling_place_source` badge `TextEntry` and `polling_place_resolved_at` date `TextEntry` (both with a `'Sin resolver'` placeholder) right after the `census_validated_at` entry, before `last_validation_source`.
- **`tests/Feature/Filament/VoterResourceTest.php`**: added 3 Pest tests — table shows the badge's Spanish label, filter restricts to selected source(s) (SNAPSHOT visible, LIVE excluded), and the infolist page renders the badge's Spanish label for a `DB_RECONSTRUCTION` voter.

No `->color()` callback was added anywhere — `PollingPlaceSource` already implements `HasColor`/`HasIcon`/`HasLabel`, so `->badge()` alone resolves everything, matching the plan's explicit instruction and Filament's documented enum-badge behavior.

## Verification

- `php artisan test --filter=VoterResourceTest` — 28 passed (96 assertions): all 25 pre-existing tests plus the 3 new ones, zero regressions.
- `vendor/bin/pint --dirty --test` — no violations.
- Grep-based acceptance criteria for all 3 tasks (import counts, column/filter/entry presence, no stray `->color(` calls) all confirmed.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Worktree was stale, pre-dating Phases 6-10 entirely**

- **Found during:** Task setup, before Task 1 — `app/Enums/PollingPlaceSource.php` (a Phase 7 artifact this plan's interfaces section depends on) did not exist in the worktree.
- **Issue:** The worktree (`agent-a5c845faa24c90d58`) was checked out at commit `78c1f69` ("chore: complete v1.0 milestone"), a fast-forward ancestor of `main`'s current HEAD (`8de7b48`). It was missing all of Phases 6-10's code, migrations, and planning docs, plus `vendor/` and `.env`. This is the same class of issue already documented in STATE.md's Blockers/Concerns section for prior phases (06, 07) in different worktrees.
- **Fix:** `git merge --ff-only main` to fast-forward the worktree to `main`'s HEAD (safe: worktree had zero uncommitted changes and HEAD was a strict ancestor of main), then copied `.env` from the main checkout and ran `composer install`.
- **Files modified:** None beyond the merge itself (74 files pulled in from the phases 6-10 history, none touched by this plan's own edits).
- **Commit:** Not separately committed — this was a worktree-state fix (fast-forward merge), not a task deliverable; the merge commit is `8de7b48` (already on `main`, now also the worktree's base).

## Self-Check: PASSED

All 4 claimed files exist on disk (VotersTable.php, ViewVoter.php, VoterResourceTest.php, this SUMMARY.md) and all 3 task commits (`c827d49`, `fd88705`, `e014591`) exist in git history.
