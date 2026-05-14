---
phase: quick
plan: 260514-mng
subsystem: birthday-webhooks
tags: [webhooks, scheduler, filament, artisan-command, pest]
dependency_graph:
  requires: []
  provides: [birthday:dispatch-webhooks command, BirthdayWebhookService, CampaignForm webhook section, everyMinute schedule]
  affects: [CampaignForm, routes/console.php]
tech_stack:
  added: []
  patterns: [service-layer, artisan-command, Http-fake-testing]
key_files:
  created:
    - app/Services/BirthdayWebhookService.php
    - app/Console/Commands/DispatchBirthdayWebhooks.php
    - tests/Feature/DispatchBirthdayWebhooksTest.php
  modified:
    - app/Filament/Resources/Campaigns/Schemas/CampaignForm.php
    - routes/console.php
decisions:
  - Seed RoleSeeder in beforeEach for test isolation — coordinator role must exist before assignRole call
  - Filter campaigns at PHP level (Collection::filter) rather than raw JSON query for settings fields — simpler and reliable across SQLite/MySQL
metrics:
  duration: 4 min
  completed: "2026-05-14"
  tasks_completed: 2
  files_changed: 5
---

# Quick Task 260514-mng: Birthday Webhook Automation Summary

**One-liner:** Per-campaign HTTP webhook automation that posts today's birthday voters and coordinator/leader users as JSON at a configured Colombia-timezone time, fired every minute by the scheduler.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | BirthdayWebhookService + DispatchBirthdayWebhooks command (TDD) | 587d957 | app/Services/BirthdayWebhookService.php, app/Console/Commands/DispatchBirthdayWebhooks.php, tests/Feature/DispatchBirthdayWebhooksTest.php |
| 2 | CampaignForm birthday section + console schedule entry | 12262d1 | app/Filament/Resources/Campaigns/Schemas/CampaignForm.php, routes/console.php |

## What Was Built

**BirthdayWebhookService** (`app/Services/BirthdayWebhookService.php`): Queries voters and campaign users (coordinator/leader role) with today's birthday. If both collections are empty, logs and returns without HTTP call. Builds a structured JSON payload and posts via `Http::timeout(30)->post($url, $payload)->throw()`.

**DispatchBirthdayWebhooks** (`app/Console/Commands/DispatchBirthdayWebhooks.php`): Artisan command `birthday:dispatch-webhooks` with `--campaign` and `--force` options. Filters active campaigns with `birthday_webhook_enabled=true` and a non-null `birthday_webhook_url`. Compares `now()->timezone('America/Bogota')->format('H:i')` against `birthday_webhook_time` setting (default `08:00`) unless `--force`. Wraps dispatch in try/catch — logs warning on failure and continues loop.

**CampaignForm section** (`app/Filament/Resources/Campaigns/Schemas/CampaignForm.php`): Inserted `Section::make('Automatización de Cumpleaños')` before the existing `Configuración` section. Contains a `Toggle` to enable/disable, a `TextInput` for the webhook URL, and a `TimePicker` for the send time — URL and TimePicker are conditionally visible only when toggle is active.

**Schedule entry** (`routes/console.php`): `Schedule::command('birthday:dispatch-webhooks')->everyMinute()->withoutOverlapping()` appended after the existing birthday messages schedule.

## Test Results

All 5 Pest tests pass:
- dispatches webhook when voter has birthday today and time matches
- dispatches webhook when coordinator has birthday today
- skips when time does not match
- skips when birthday_webhook_enabled is false
- no HTTP call when nobody has birthday today

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing test setup] Added RoleSeeder to beforeEach**
- **Found during:** Task 1 GREEN phase (test 2 — coordinator test)
- **Issue:** `assignRole('coordinator')` throws `RoleDoesNotExist` because Spatie roles aren't seeded in test DB
- **Fix:** Added `$this->seed(\Database\Seeders\RoleSeeder::class)` to `beforeEach` — consistent with how other tests in the project handle role-dependent tests
- **Files modified:** tests/Feature/DispatchBirthdayWebhooksTest.php

## Known Stubs

None — all webhook functionality is fully wired with real Eloquent queries and Http dispatch.

## Self-Check: PASSED

- [x] app/Services/BirthdayWebhookService.php — exists
- [x] app/Console/Commands/DispatchBirthdayWebhooks.php — exists
- [x] tests/Feature/DispatchBirthdayWebhooksTest.php — exists
- [x] Commits 587d957 and 12262d1 — verified in git log
- [x] All 5 tests pass
- [x] `php artisan list | grep birthday` shows both commands
- [x] `vendor/bin/pint --dirty` reports no issues
