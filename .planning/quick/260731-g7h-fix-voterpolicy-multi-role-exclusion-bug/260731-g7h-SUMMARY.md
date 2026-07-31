---
phase: quick
plan: 260731-g7h
subsystem: auth, ui
tags: [spatie-permission, policies, alpine, livewire, pest-browser, registraduria]

requires: []
provides:
  - "VoterPolicy mutating methods rewritten from exclusion to inclusion-based hasAnyRole(), matching InvitationPolicy's established convention"
  - "Resilient Alpine polling in registraduria-browser.blade.php: r.ok check, 15-consecutive-transient-failure escalation threshold, 200s elapsed-time safety cap"
  - "Genuine Pest v4 Browser test coverage (tests/Browser/RegistraduriaPollingResilienceTest.php) for both the transient-retry path and the unchanged genuine-error path"
affects: [voter-crud, registraduria-integration, reports-viewer-role]

tech-stack:
  added: []
  patterns:
    - "Real-browser Pest v4 tests that need an authenticated session must log in through the actual /login form (type + click), not actingAs() — Pest\\Browser\\Drivers\\LaravelHttpServer dispatches every browser request through a fresh real Laravel HTTP kernel call, so actingAs()'s in-process guard state never reaches the browser's cookie jar."
    - "Blade views rendered for a Pest v4 Browser test route must be rendered INSIDE the route closure (at actual-request time), not pre-rendered as a string before Route::get() — directives with request-scoped side effects (like @fluxScripts's app('livewire')->forceAssetInjection()) get reset by Livewire's per-request flush-state event if rendered too early, silently dropping Alpine/Livewire asset injection."

key-files:
  created:
    - tests/Browser/RegistraduriaPollingResilienceTest.php
  modified:
    - app/Policies/VoterPolicy.php
    - tests/Feature/Policies/VoterPolicyTest.php
    - resources/views/filament/registraduria-browser.blade.php

key-decisions:
  - "VoterPolicy's 10 mutating methods (create/update/delete/deleteAny/restore/restoreAny/forceDelete/forceDeleteAny/replicate/reorder) all now return hasAnyRole([SUPER_ADMIN, ADMIN_CAMPAIGN, COORDINATOR, LEADER, REVIEWER]) instead of !hasRole(REPORTS_VIEWER) — fixes a user holding an elevated role plus reports_viewer being wrongly denied all mutating Voter abilities."
  - "Polling loop escalates a transient (non-2xx) /registraduria/result/{id} response to a real error only after 15 consecutive failures (~30s), and independently caps total polling at 200s elapsed — both checks preserve the existing genuine HTTP-200 status:'done'/'error' contract untouched."
  - "Browser test authenticates via a real /login form submission with 2FA explicitly disabled on the test user (two_factor_secret/two_factor_confirmed_at set to null), since Pest's LaravelHttpServer routes every browser request through the real Laravel kernel and actingAs() does not populate the browser's session cookie."
  - "Test routes registered at runtime inside the test must explicitly declare ['web', 'auth'] middleware and render their Blade view inside the closure (not pre-rendered) to get real session/auth handling and correct Livewire/Alpine asset injection."

requirements-completed: []

duration: 35min
completed: 2026-07-31
---

# Quick Task 260731-g7h: Fix VoterPolicy Multi-Role Exclusion + Registraduría Polling Resilience Summary

**Rewrote VoterPolicy's 10 mutating methods from an exclusion check to InvitationPolicy's inclusion-based `hasAnyRole()` pattern, and hardened the Registraduría Alpine polling loop against transient non-2xx responses with a 15-failure escalation threshold and a 200s safety cap — both fixes backed by real, passing automated tests (Pest Feature + genuine Pest v4 Browser).**

## Performance

- **Duration:** ~35 min (includes worktree re-provisioning: composer install, npm install/build, Playwright chromium download)
- **Completed:** 2026-07-31
- **Tasks:** 3 (2 code+test tasks, 1 verification-only regression pass)
- **Files modified:** 3 modified, 1 created

## Accomplishments

- Fixed the multi-role exclusion bug: a user holding `super_admin` (or any elevated role) alongside `reports_viewer` can now create/edit/delete Voters again, with a passing regression test proving it.
- Confirmed the single-role `reports_viewer` (read-only) behavior and every other single-role's mutating access are completely unaffected (no regression).
- Fixed the Registraduría polling loop's false-error bug: a transient 503/network hiccup during a live lookup no longer collapses to `'error'` via the `d.status ?? 'error'` fallback — it now retries silently and only surfaces an error after 15 consecutive failures, with an independent 200s elapsed-time cap.
- Added genuine, real-browser-verified Pest v4 Browser test coverage for both the transient-retry path and the unchanged genuine-error path (not a documented manual-only fallback — the automated coverage was fully achieved).

## Task Commits

1. **Task 1: Fix VoterPolicy's multi-role exclusion bug + regression test** - `b875066` (fix)
2. **Task 2: Fix Registraduría polling resilience + genuine Pest v4 Browser test** - `f4ea2d5` (fix)
3. **Task 3: Full regression pass + pint** - verification only, no commit (all 4 targeted test files + pint confirmed green)

**Plan metadata:** (this commit, docs: complete plan)

## Files Created/Modified

- `app/Policies/VoterPolicy.php` - All 10 mutating methods (`create`, `update`, `delete`, `deleteAny`, `restore`, `restoreAny`, `forceDelete`, `forceDeleteAny`, `replicate`, `reorder`) rewritten to `hasAnyRole([SUPER_ADMIN, ADMIN_CAMPAIGN, COORDINATOR, LEADER, REVIEWER])`, mirroring `InvitationPolicy`'s established inclusion pattern. `viewAny`/`view` left untouched (`return true`).
- `tests/Feature/Policies/VoterPolicyTest.php` - Added a new regression test: a user holding both `SUPER_ADMIN` and `REPORTS_VIEWER` simultaneously now passes every mutating ability check (previously false, the actual bug scenario).
- `resources/views/filament/registraduria-browser.blade.php` - Added `transientFailures` counter to Alpine state; `start()`'s polling `fetch()` now checks `r.ok` first (transient path: increment counter, no status write, escalate to error only at 15 consecutive failures), plus a `this.elapsed >= 200` safety-cap branch checked before every fetch.
- `tests/Browser/RegistraduriaPollingResilienceTest.php` (new) - Two genuine Pest v4 Browser tests: (A) 2 transient 503s followed by a real `status:'done'` response never surfaces `'error'` and eventually reaches `'done'`; (B) a genuine HTTP-200 `status:'error'` response still reaches `'error'` immediately (unchanged behavior).

## Decisions Made

- See `key-decisions` in frontmatter above for the substantive ones (policy rewrite rationale, polling thresholds, browser-test auth approach, and the render-timing fix for Blade directives with request-scoped side effects).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Worktree was stale (missing `vendor/`, `.env`, `node_modules/`, `public/build/`) and one commit behind main**
- **Found during:** Task setup (before Task 1)
- **Issue:** Same class of issue logged repeatedly in STATE.md's Blockers/Concerns — this worktree was checked out at `458c675`, one commit behind main's `a8f2be8` (which created this task's own PLAN.md), and had no `vendor/`, `.env`, `node_modules/`, or built Vite assets.
- **Fix:** Confirmed `458c675` is a fast-forward ancestor of `a8f2be8`, ran `git merge --ff-only`, copied `.env` from the main checkout, ran `composer install`, `npm install`, and `npm run build`.
- **Files modified:** None tracked (environment-only; the `package-lock.json` name-field diff from `npm install` was discarded via `git checkout --`, not committed).
- **Commit:** N/A (environment setup, not a code change)

**2. [Rule 3 - Blocking] Playwright browser binaries were missing/outdated for the Pest v4 Browser plugin**
- **Found during:** Task 2 (first browser test run)
- **Issue:** `PlaywrightOutdatedException` — the npm `playwright` package was present and current (1.58.2) but no Chromium browser binary was installed for it, which the plugin's error message reports as "outdated."
- **Fix:** Ran `npx playwright install chromium` to download the matching browser build.
- **Files modified:** None (browser binary cached under `~/Library/Caches/ms-playwright`, not part of the repo).
- **Commit:** N/A

**3. [Rule 1 - Bug] Plan's example browser test code used a non-existent `Webpage::evaluate()` method**
- **Found during:** Task 2 (first browser test run)
- **Issue:** `Pest\Browser\Api\Webpage` has no `evaluate()` method; the real method for executing JS in the page context is `script(string $content)` (which internally calls `Page::evaluate()` as an *expression*, not a function-wrapped callback).
- **Fix:** Changed both `$page->evaluate("() => Alpine.$data(...).status")` calls to `$page->script("Alpine.$data(...).status")` (a plain expression, no arrow-function wrapper).
- **Files modified:** `tests/Browser/RegistraduriaPollingResilienceTest.php`
- **Commit:** `f4ea2d5`

**4. [Rule 1 - Bug] Plan's example browser test pre-rendered the Blade view before registering the route, silently dropping Alpine/Livewire asset injection**
- **Found during:** Task 2 (debugging why `Alpine` was undefined in the browser)
- **Issue:** `Blade::render()` was called once, immediately, to build a `$html` string, which was then returned by a `Route::get()` closure. Because `@fluxScripts`'s directive calls `app('livewire')->forceAssetInjection()` as inline PHP *inside the rendered template*, that call executed at test-setup time (before any HTTP request), and Livewire's per-request `flush-state` event (which resets `forceAssetInjection` to `false`) fired before the real request was ever dispatched — so no Livewire/Alpine `<script>` tag was ever injected into the response, and `Alpine` was undefined in the browser (`ReferenceError: Alpine is not defined`).
- **Fix:** Moved the `Blade::render(...)` call to execute *inside* the route closure, so `forceAssetInjection()` runs during the actual request's lifecycle, right before Livewire's `RequestHandled` listener checks the flag.
- **Files modified:** `tests/Browser/RegistraduriaPollingResilienceTest.php`
- **Commit:** `f4ea2d5`

**5. [Rule 3 - Blocking] `/registraduria/result/{id}` requires `auth` middleware; the plan's test routes and assertions never authenticated the real browser session**
- **Found during:** Task 2 (status stuck at `'pending'` for the full test loop)
- **Issue:** The plan's browser test registered its scratch `Route::get('/__test/...')` with no middleware and never authenticated. The polling loop's `fetch('/registraduria/result/...')` hit the real, `auth`-protected production route; an unauthenticated request redirects (fetch follows redirects transparently, landing on the login page HTML with `r.ok === true`), so `r.json()` threw a parse error silently caught by `.catch(() => {})`, and status never advanced past `'pending'`. `Pest\Browser\Drivers\LaravelHttpServer` dispatches every real browser request through a genuine `HttpKernel::handle()` call using the browser's actual cookies — `actingAs()` (which only mutates the test process's in-memory Auth guard) never reaches that cookie jar, so it could not be used to authenticate the browser session.
- **Fix:** Added a `loginRealBrowserUser()` helper that creates a test user with 2FA explicitly disabled (`two_factor_secret`/`two_factor_confirmed_at` set to `null`, overriding the factory's confirmed-2FA default) and performs a genuine browser-driven login: `visit('/login')`, `type('email', ...)`, `type('password', ...)`, `click('Ingresar')`. Also added explicit `['web', 'auth']` middleware to both scratch test routes so the session/auth stack actually runs for a runtime-registered route (routes registered outside `routes/web.php`'s file-load lifecycle don't automatically get the `web` group).
- **Files modified:** `tests/Browser/RegistraduriaPollingResilienceTest.php`
- **Commit:** `f4ea2d5`

---

**Total deviations:** 5 (2 environment/blocking setup issues, 3 test-code bugs in the plan's own example — all found and fixed during genuine TDD execution of Task 2, none touching production application code beyond the two files the plan specified).
**Impact on plan:** Zero scope creep — every fix was required to make the plan's own specified deliverable (a genuinely passing, real-browser-verified Pest v4 Browser test) actually work as intended. No fallback to a manual-verification checklist was needed; full automated coverage was achieved.

## Issues Encountered

None beyond the deviations documented above — all were resolved without needing user input, and the plan's documented fallback (manual verification checklist) was never invoked because the genuine browser tests were made to pass.

## User Setup Required

None - no external service configuration required. (Playwright's Chromium binary download was a one-time local dev-environment step, not a production dependency.)

## Next Phase Readiness

- Both confirmed production bugs are fixed and covered by automated tests (8 Feature tests in `VoterPolicyTest`, 2 genuine Pest v4 Browser tests in `RegistraduriaPollingResilienceTest`), plus a full targeted regression pass (38 tests total across `VoterPolicyTest`, `ReportsPanelTest`, `VoterRegistraduriaRefreshTest`, and the new browser test) all green.
- `vendor/bin/pint --test` confirmed clean on all 4 touched files.
- Per user's explicit "zero known bugs before deploying" requirement, this task's fixes are ready to ship; per the established project lesson ("browser-verify before prod"), a final real-browser click-through in a running dev/staging environment before production deploy is still recommended, though the automated Pest v4 Browser tests already exercise the real polling JS in a real Chromium instance against the real (faked-HTTP) backend route.
- No blockers for subsequent work.

---
*Phase: quick*
*Completed: 2026-07-31*

## Self-Check: PASSED

All 4 modified/created files confirmed present on disk; both task commits (`b875066`, `f4ea2d5`) confirmed present in git history.
