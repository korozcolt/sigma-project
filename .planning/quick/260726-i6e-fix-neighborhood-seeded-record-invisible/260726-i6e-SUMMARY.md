---
phase: quick
plan: 260726-i6e
subsystem: territorial-data-seeding
tags: [neighborhood, seeder, campaign-scope, regression-test]
requires: []
provides:
  - "RoleUsersSeeder Centro neighborhood is is_global=true, campaign_id=null, and self-repairing on re-seed"
  - "Regression test guarding CampaignContextScope global-record visibility for Neighborhood"
affects:
  - "database/seeders/RoleUsersSeeder.php"
  - "tests/Feature/NeighborhoodTest.php"
tech-stack:
  added: []
  patterns:
    - "updateOrCreate() over firstOrCreate() when a seeder must repair a previously-broken row, not just create-on-first-run"
key-files:
  created: []
  modified:
    - "database/seeders/RoleUsersSeeder.php"
    - "tests/Feature/NeighborhoodTest.php"
decisions:
  - "Used Neighborhood::updateOrCreate() (not firstOrCreate()) so re-running the seeder against an already-broken database repairs the existing row in place, matching the plan's explicit rationale."
  - "Test setup calls CampaignContext::setCampaignId(null) before creating the global/campaign-specific Neighborhood fixtures, because CampaignContext::enforceCampaignId() (a model 'creating' hook from HasCampaignContext) overwrites campaign_id at creation time whenever currentCampaignId() resolves to a real campaign -- even for a super admin with no explicit override -- which would otherwise silently clobber the factory's explicit campaign_id => null / campaign_id => $campaignA->id values and produce a false-negative test (Rule 1 auto-fix, discovered while running the new test)."
metrics:
  duration: 20min
  completed: 2026-07-26
---

# Quick Task 260726-i6e: Fix Neighborhood Seeded Record Invisible Summary

Fixed `RoleUsersSeeder`'s "Centro" neighborhood so it is actually global (`is_global => true`, `campaign_id => null`) and self-repairing on re-seed, using `updateOrCreate()` instead of `firstOrCreate()`; added a regression test proving global neighborhoods survive the real `CampaignContextScope` scope.

## What Changed

**Task 1 — `database/seeders/RoleUsersSeeder.php`:**
Replaced:
```php
$neighborhood = Neighborhood::firstOrCreate(
    ['name' => 'Centro', 'municipality_id' => $municipality->id]
);
```
with:
```php
$neighborhood = Neighborhood::updateOrCreate(
    ['name' => 'Centro', 'municipality_id' => $municipality->id],
    ['is_global' => true, 'campaign_id' => null]
);
```
`firstOrCreate()` only applies the values array on INSERT; it never touches a row that already matches the search criteria. Since the local dev database already had the broken "Centro" row (`id=6`, `is_global=false`), `updateOrCreate()` was required to actually repair it on re-seed, not just fix future fresh installs.

Ran `php artisan db:seed --class=RoleUsersSeeder --no-interaction` against the local dev database. Confirmed via tinker:
```
id=6 is_global=true campaign_id=NULL
```
Then confirmed the fix from the exact angle the bug manifested — the normal scoped query (global scope active, no `withoutGlobalScopes()`), authenticated as a real user:
```
Neighborhood::query()->where('municipality_id', 1007)->get()
=> count 1, [6 => Centro]
```

**Task 2 — `tests/Feature/NeighborhoodTest.php`:**
Added a new test (`it keeps a global neighborhood visible under CampaignContextScope for any campaign, matching the RECORD-SEEDED CENTRO bug`) exercising the real `CampaignContextScope` global scope directly (not the model's own `scopeGlobal()`/`scopeAvailableForCampaign()` helpers, which bypass `CampaignContextScope` entirely and would not have caught this bug). Follows the `CampaignScopeAuditTest.php` precedent: `actingAs()` a super_admin, `CampaignContext::setCampaignId()` to switch active campaign, `afterEach()` resetting `CampaignContext`'s static override properties via reflection so the override never leaks into other test files.

Asserts:
- A global neighborhood (`is_global=true`, `campaign_id=null`) is visible via `Neighborhood::query()` under campaign A's active context.
- The same global neighborhood remains visible when the active context switches to campaign B (proves it's visible to every campaign, not just the one active at creation).
- A campaign-A-specific neighborhood is correctly NOT visible when the active context is campaign B (control assertion — existing isolation still holds).

`vendor/bin/pint --dirty --test` reports no changes needed.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Test fixture creation clobbered by ambient CampaignContext::enforceCampaignId()**
- **Found during:** Task 2, first test run (`php artisan test --filter=NeighborhoodTest` failed on the new test — global neighborhood not visible under campaign B's context)
- **Issue:** `HasCampaignContext::bootHasCampaignContext()` registers a `creating` hook (`CampaignContext::enforceCampaignId()`) that unconditionally overwrites a model's `campaign_id` attribute whenever `CampaignContext::currentCampaignId()` resolves to a non-null value — even for a super admin with no explicit context override set, since `currentCampaignId()` falls back to "the first campaign by id" when no campaign is active/no override is set. In the test, `$this->actingAs($admin)` (super admin) plus two already-created `Campaign` fixtures meant `currentCampaignId()` resolved to `$campaignA->id` at the moment `Neighborhood::factory()->global()->create([...])` ran, silently overwriting the factory's explicit `campaign_id => null` with `$campaignA->id`. The neighborhood then only satisfied the scope's "matches active campaign_id" branch (coincidentally passing for campaign A), not the "is_global AND campaign_id IS NULL" branch — so it correctly failed visibility once the active context switched to campaign B, exposing the test bug rather than the seeder bug.
- **Fix:** Added `CampaignContext::setCampaignId(null)` (switches to "all campaigns" override mode) immediately before creating the `$global` and `$campaignSpecific` fixtures, so `enforceCampaignId()` sees `currentCampaignId() === null` and (being a super admin) skips the overwrite entirely, preserving the factory's explicit `campaign_id` values.
- **Files modified:** `tests/Feature/NeighborhoodTest.php`
- **Commit:** 35401a7

## Self-Check

```
FOUND: database/seeders/RoleUsersSeeder.php
FOUND: tests/Feature/NeighborhoodTest.php
```

```
FOUND: 91f9fa8
FOUND: 35401a7
```

## Self-Check: PASSED
