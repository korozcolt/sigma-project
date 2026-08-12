---
phase: 19-articulador-panel-human-uat-closure
plan: 02
subsystem: test-infrastructure
tags: [pest, browser-testing, playwright, refactor]
dependency_graph:
  requires: []
  provides:
    - "Shared loginRealBrowserUser(User $user, string $password = 'password') global function in tests/Pest.php"
  affects:
    - "tests/Browser/RegistraduriaPollingResilienceTest.php"
    - "Every future Browser test file in this phase (19-03, 19-04, 19-05) that needs a real-browser login as a specific fixture user"
tech_stack:
  added: []
  patterns:
    - "Global helper functions shared across a Pest test suite live in tests/Pest.php's Functions section, not duplicated per test file"
key_files:
  created: []
  modified:
    - tests/Pest.php
    - tests/Browser/RegistraduriaPollingResilienceTest.php
decisions:
  - "Left the pre-existing placeholder function something() in tests/Pest.php untouched — grep confirmed zero call sites anywhere in tests/, and removing unrelated placeholder code was explicitly out of this plan's scope."
  - "Did not modify phpunit.xml to add a Browser testsuite entry, even though the plan's own literal Task 2 verify command (php artisan test --testsuite=Browser) silently reports \"No tests found\" (phpunit.xml only defines Unit/Feature testsuites; Pest's ->in('Browser') grouping is a separate, unrelated mechanism). Verified the actual behavior instead by running the test file directly (php artisan test tests/Browser/RegistraduriaPollingResilienceTest.php), which correctly discovered and passed both tests. Logged as a deferred, out-of-scope pre-existing gap."
metrics:
  duration_minutes: 25
  tasks_completed: 2
  files_changed: 2
  completed_date: "2026-08-12"
---

# Phase 19 Plan 02: Shared Real-Browser Login Helper Summary

Promoted a real-browser Pest login helper from a file-local function (duplicated risk across future Browser test files) into a single, parameterized, shared function in `tests/Pest.php`, unblocking every later Wave-2 plan in this phase that needs to authenticate a specific fixture role (articulador) rather than a fixed throwaway user.

## What Was Built

### Task 1: Promote `loginRealBrowserUser()` into `tests/Pest.php`

- Added `use App\Models\User;` near the top of `tests/Pest.php`.
- Added a new global function in the `Functions` section:
  ```php
  function loginRealBrowserUser(User $user, string $password = 'password'): void
  {
      $page = visit('/login');
      $page->type('email', $user->email);
      $page->type('password', $password);
      $page->click('Ingresar');
      $page->wait(1);
  }
  ```
  This preserves the exact original login mechanism (real `/login` form submission through the real Laravel HTTP kernel, since Pest v4 Browser tests via `Pest\Browser\Drivers\LaravelHttpServer` never authenticate via `actingAs()`), but now accepts any `User` instance and an optional password instead of hardcoding a single throwaway user.
- Removed the file-local `function loginRealBrowserUser(): void { ... }` declaration from `tests/Browser/RegistraduriaPollingResilienceTest.php` entirely.
- Updated both `it(...)` blocks in that file to build their own fixture user via `User::factory()->withoutTwoFactor()->create([...])` (reusing the existing `UserFactory::withoutTwoFactor()` state instead of manually nulling 2FA columns) and call `loginRealBrowserUser($browserUser)` explicitly — preserving the exact prior behavior (fresh throwaway `browser-test@example.com` user, 2FA disabled, known password) while now going through the shared helper.
- The pre-existing placeholder `function something() {}` was left untouched after confirming (via `grep -rn "something(" tests/`) it has zero call sites anywhere in the suite — out of this plan's scope to clean up.

### Task 2: Confirm the shared helper works with zero redeclare errors

- Ran `vendor/bin/pint --dirty` on both touched files — 2 files, no style violations, no changes needed.
- Ran the Browser test suite. `php artisan test tests/Browser/RegistraduriaPollingResilienceTest.php` (running both existing tests in one process, the exact scenario that would surface a `Cannot redeclare loginRealBrowserUser()` fatal if the promotion were done incorrectly) passed cleanly: **2 passed (2 assertions)**, no PHP fatal of any kind.
- The Playwright Chromium binary was outdated/missing for this worktree's fresh `node_modules` install (`PlaywrightOutdatedException`); resolved per `19-RESEARCH.md`'s documented Pitfall 3 by running `npx playwright install chromium`, then re-running the tests successfully.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking issue] Playwright Chromium binary outdated/missing in this fresh worktree**
- **Found during:** Task 2
- **Issue:** `php artisan test tests/Browser/...` failed both tests with `PlaywrightOutdatedException: Playwright is outdated. Please run [npm install playwright@latest && npx playwright install]` — this worktree's `node_modules`/Playwright browser cache was freshly installed via `npm install` + `npm run build` as part of this session's environment setup (see Worktree Staleness decision below), and the locally cached Chromium binary didn't match the installed `playwright`/`@playwright/test` npm package version (1.58.2).
- **Fix:** Ran `npx playwright install chromium`, exactly as `19-RESEARCH.md`'s Pitfall 3 documented as the expected remedy for this environment. Re-ran the test suite; both tests passed.
- **Files modified:** None (browser binary cache only, outside the repo).
- **Commit:** N/A (no source change).

### Deferred, Out-of-Scope Items

Logged to `.planning/phases/19-articulador-panel-human-uat-closure/deferred-items.md`:

- `php artisan test --testsuite=Browser` (the plan's own literal Task 2 verify command) silently reports "No tests found" and exits 0 — `phpunit.xml`'s `<testsuites>` block only defines `Unit`/`Feature`, with no `Browser` (or `E2E`) entry, even though `tests/Pest.php` groups tests via Pest's own `->in('Browser')`/`->in('E2E')` (a separate, unrelated mechanism controlling `RefreshDatabase` binding, not PHPUnit's test-suite discovery). This is a pre-existing gap, not introduced by this plan, and `phpunit.xml` is not in this plan's `files_modified` list. Verified the plan's actual `<done>` criteria instead by running `php artisan test tests/Browser/RegistraduriaPollingResilienceTest.php` directly, which correctly discovered and passed both tests.

## Worktree Staleness (environment setup, not a plan deviation)

This worktree (`agent-ab63a345731d70ca2`) was 78 commits behind `main` at session start — missing Phases 16, 17, 18, and this phase's own PLAN.md files, plus `.env`, `vendor/`, `node_modules/`, and `public/build/`. Same recurring class documented repeatedly in prior phases' SUMMARY.md files. Resolved with the established workaround: confirmed fast-forward ancestry (`git merge-base --is-ancestor HEAD main`), `git merge --ff-only main`, `.env` copy from the main checkout, `composer install`, `npm install && npm run build`, then `npx playwright install chromium` (needed specifically for this plan's Browser-suite verification, see above). All 27 pending migrations (through `2026_08_10_120200_create_user_metadata_values_table`) were already applied to the shared `sigma_betha_backup` database from prior parallel-worktree sessions — `php artisan migrate:status` showed zero pending migrations, no `migrate` run was needed.

## Self-Check: PASSED

- FOUND: `tests/Pest.php` (modified, contains `loginRealBrowserUser`)
- FOUND: `tests/Browser/RegistraduriaPollingResilienceTest.php` (modified, no local declaration)
- FOUND: commit `21000e0` — `refactor(19-02): promote loginRealBrowserUser() into shared tests/Pest.php helper`
