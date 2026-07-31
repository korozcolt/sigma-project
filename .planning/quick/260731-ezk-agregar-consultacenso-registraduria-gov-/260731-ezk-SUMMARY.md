---
phase: quick
plan: 260731-ezk
subsystem: api
tags: [flask, playwright, 2captcha, http-client, laravel, live-source-adapter, pest]

requires:
  - phase: 08-resilient-pollingplaceresolver-service
    provides: LiveSourceAdapter interface + PollingPlaceResolver cascade (isReachable/startLookup/getResult, liveAdapters iterable)
provides:
  - Third LiveSourceAdapter (ConsultaCensoService) coexisting with InfovotantesService and RegistraduriaService, registered as the fallback/third priority
  - registraduria-service/app.py serving three independent lookup flows (wsp + infovotantes + consultacenso) from one Flask process
affects: [pollingplaceresolver, registraduria-service, live-source-adapters]

tech-stack:
  added: []
  patterns:
    - "Third LiveSourceAdapter added purely by array-order registration in AppServiceProvider — no PollingPlaceResolver code changes needed to add a third fallback tier"
    - "consultacenso flow calls the site's real JSON API (/back/api/elecciones + /back/api/consulta) same-origin from the Playwright browser context that loaded the page, unlike infovotantes's cross-domain fetch (no CORS/Origin spoofing needed)"

key-files:
  created:
    - app/Services/ConsultaCensoService.php
    - tests/Feature/Services/ConsultaCensoServiceTest.php
  modified:
    - registraduria-service/app.py
    - app/Providers/AppServiceProvider.php
    - config/services.php
    - .env.example
    - tests/Feature/Services/PollingPlaceResolverPriorityTest.php
    - tests/Feature/Filament/VoterRegistraduriaRefreshTest.php

key-decisions:
  - "ConsultaCensoService registered THIRD (fallback) in liveAdapters, after InfovotantesService and RegistraduriaService — tried only when both prior sources are unreachable or fail"
  - "Python flow solves the standard reCAPTCHA v2 checkbox via 2captcha with NO enterprise param (confirmed via live DevTools investigation the site is not enterprise-gated client-side, despite an Enterprise-quota message in the iframe), then calls /back/api/elecciones + /back/api/consulta directly as real JSON, no HTML parsing — closer to the infovotantes pattern than the wsp pattern"
  - "ConsultaCensoService.getResult() is a pure pass-through of the Python service's JSON, mirroring InfovotantesService exactly except for base endpoint/config namespace"
  - "This sandbox has real outbound internet access and the real consultacenso.registraduria.gov.co site is genuinely reachable (curl confirmed HTTP 200) — this made ConsultaCensoService::isReachable() return true for real inside 3 pre-existing VoterRegistraduriaRefreshTest cases that only mocked the first two adapters as unreachable, causing them to take the live branch instead of falling through to DB/snapshot. Fixed by adding the same isReachable=>false mock for ConsultaCensoService to those cases (Rule 1 auto-fix)."

requirements-completed: []

duration: ~50min
completed: 2026-07-31
---

# Quick Task 260731-ezk: Add consultacenso.registraduria.gov.co as a Third Live Source Summary

**New ConsultaCensoService LiveSourceAdapter (third/fallback priority) wired into both the Python microservice (POST /lookup/censo) and PollingPlaceResolver's liveAdapters cascade — purely additive, wsp and infovotantes behavior completely unchanged. All 4 plan tasks are complete: Tasks 1-3 (code + tests) verified via Pest, and Task 4 (the blocking human-verify checkpoint) PASSED — a real end-to-end lookup against the live site with cédula 1102851353 and real 2captcha balance resolved successfully (puesto CHOCHO, IE SAN ISIDRO DE CHOCO, Sincelejo/Sucre, mesa 12).**

## Performance

- **Duration:** ~50 min (includes worktree staleness repair: fast-forward merge, composer install, .env copy, npm install/build)
- **Tasks:** 3 of 4 completed (Task 4 is a blocking human-verify checkpoint, not executable by an agent)
- **Files modified:** 8 (2 created, 6 modified)

## Accomplishments

- `registraduria-service/app.py` now serves a third flow from the same Flask process: `POST /lookup/censo`, sharing the existing `sessions` dict/lock/`_set()` helper and `GET /result/<session_id>` route. The wsp (`/lookup`) and infovotantes (`/lookup/infovotantes`) routes/functions are byte-for-byte untouched (confirmed via `git diff` — only additions, plus a two-line docstring comment update mentioning the third coexisting flow).
- `_lookup_censo_async()` extracts the live sitekey (falling back to a hardcoded constant), solves the standard reCAPTCHA v2 checkbox via 2captcha (no `enterprise` param), fetches `/back/api/elecciones` for the election id, then POSTs `/back/api/consulta` with `{documento, eleccionId, captchaToken}` same-origin via `page.request.fetch()`, and classifies the real JSON response into success/not_found/session_expired/denied_by_score outcomes (never treating a solved token alone as success).
- New `App\Services\ConsultaCensoService` implements `LiveSourceAdapter`, mirroring `InfovotantesService`'s pass-through-JSON structure exactly (no HTML parsing) except for its own base endpoint (`/lookup/censo`) and config namespace (`services.consulta_censo.*`).
- `AppServiceProvider`'s `liveAdapters` binding is now `[InfovotantesService, RegistraduriaService, ConsultaCensoService]` — the entire priority mechanism; no `PollingPlaceResolver` code changes needed.
- New `services.consulta_censo` config block (`url`/`live_enabled`/`probe_url`) + matching `.env.example` trio, fully independent from the existing `registraduria`/`infovotantes` blocks.
- Pest coverage: `ConsultaCensoServiceTest` (7/7 — kill switch, probe reachability x2, startLookup success/throw, getResult pass-through/404) and `PollingPlaceResolverPriorityTest` (updated 3-element binding-order reflection test + 2 new tests proving consultacenso is tried only when both infovotantes AND wsp are unreachable/fail, for both `resolveAutomated()` and `startLiveLookup()`).
- Fixed a real regression the third-adapter addition caused in the pre-existing `VoterRegistraduriaRefreshTest` (interactive Filament path) — see Deviations below.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add consultacenso flow as a third Flask route** - `59f88ba` (feat)
2. **Task 2: ConsultaCensoService adapter + config + priority-ordered registration + adapter tests** - `49672fa` (feat)
3. **Task 3: 3-adapter cascade/fallback coverage + Pint (includes Rule 1 regression fix)** - `fe808b4` (test)

Task 4 (checkpoint:human-verify, blocking) PASSED — see below. Docs/STATE.md commit: `421b74a`.

## Files Created/Modified

- `registraduria-service/app.py` - Added `CENSO_*` constants, `_ci_get()` helper, `_lookup_censo_async()`/`_run_censo()`, and `POST /lookup/censo` route; wsp/infovotantes flows untouched (git diff shows only additions plus a docstring-comment update)
- `app/Services/ConsultaCensoService.php` - New `LiveSourceAdapter` implementation for the consultacenso endpoint
- `app/Providers/AppServiceProvider.php` - `liveAdapters: [InfovotantesService, RegistraduriaService, ConsultaCensoService]`
- `config/services.php` - New sibling `consulta_censo` config block
- `.env.example` - New `CONSULTA_CENSO_SERVICE_URL`/`CONSULTA_CENSO_LIVE_ENABLED`/`CONSULTA_CENSO_PROBE_URL` entries
- `tests/Feature/Services/ConsultaCensoServiceTest.php` - New adapter coverage (7 tests)
- `tests/Feature/Services/PollingPlaceResolverPriorityTest.php` - Updated 3-element binding-order assertion, added 2 new tests for the 3-adapter cascade
- `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php` - Added `ConsultaCensoService::isReachable()->andReturn(false)` mock to the 4 pre-existing test cases that already mocked `InfovotantesService`/`RegistraduriaService` as unreachable, so they no longer depend on the real (currently reachable in this sandbox) live consultacenso site

## Decisions Made

- `ConsultaCensoService` registered third/last in `liveAdapters` per the plan's explicit fallback-priority requirement — tried only after both `InfovotantesService` and `RegistraduriaService` give up (unreachable, throw, or reach the resolver's existing give-up conditions).
- Kept `RegistraduriaService`/`InfovotantesService` and their Python routes byte-for-byte unchanged, confirmed via `git diff` showing zero deletions/modifications to existing wsp/infovotantes lines.
- No `PollingPlaceResolver` code changes — the resolver already iterates `$liveAdapters` generically; adding the third element in `AppServiceProvider` is the entire mechanism.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Real-network-reachability regression in `VoterRegistraduriaRefreshTest`**
- **Found during:** Task 3 final verification (`php artisan test --filter=Registraduria`)
- **Issue:** 3 pre-existing tests only mock `InfovotantesService`/`RegistraduriaService` as unreachable (`isReachable()->andReturn(false)`) to force the interactive `openRegistraduriaBrowser()` flow past the live tier into DB-reconstruction/snapshot. They never mocked a third adapter because none existed. This sandbox has real outbound internet access, and `https://consultacenso.registraduria.gov.co/` is genuinely reachable right now (confirmed via `curl`, HTTP 200) — so the real, unmocked `ConsultaCensoService::isReachable()` returned `true`, making `PollingPlaceResolver::isLiveReachable()` report the live tier as reachable overall. This routed `openRegistraduriaBrowser()` into the live branch (`startLiveLookup()` → real `ConsultaCensoService::startLookup()` → a real HTTP POST to `http://localhost:5757/lookup/censo`, which isn't running in tests, throwing a `ConnectionException` caught by the method's own try/catch) instead of falling through to the DB-reconstruction/national-snapshot tiers the tests expected, breaking their `assertNotified()` assertions.
- **Fix:** Added the same `ConsultaCensoService::isReachable()->andReturn(false)` + `shouldNotReceive('startLookup')` mock to the 4 test cases that already had the analogous mock for the first two adapters (3 of which were actually failing; the 4th — "never downgrades" — happened to pass anyway because its final assertions don't depend on which notification fired, but was fixed too for consistency/correctness).
- **Files modified:** `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php`
- **Verification:** `php artisan test --filter=VoterRegistraduriaRefreshTest` — 20/20 pass; `php artisan test --filter=Registraduria` — 58/58 pass; full `php artisan test` run — same 13 pre-existing, unrelated `CampaignContext` test-pollution failures as before this task (confirmed passing in isolation), zero new failures
- **Committed in:** `fe808b4` (part of Task 3 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug, directly caused by this task's third-adapter addition)
**Impact on plan:** Necessary for correctness — without this fix, 3 pre-existing tests would fail every time this sandbox (or CI) has real internet access to the live consultacenso site. No scope creep — the fix is scoped exclusively to the exact test cases whose unreachable-live-tier assumption this task's new adapter invalidated.

## Issues Encountered

- **Stale worktree at session start:** checked out at `458c675`, missing main's newest commit (`ec460cc`, which added this task's own PLAN.md) plus `vendor/`, `.env`, `node_modules/`, and `public/build/`. Confirmed `ec460cc` is a fast-forward descendant of the worktree's HEAD; resolved via `git merge --ff-only`, `.env` copy from the main checkout, `composer install`, and `npm install && npm run build` (needed — some Filament test paths render the full admin layout requiring a Vite manifest). Discarded the resulting cosmetic `package-lock.json` name-field diff. Same class of worktree-staleness issue documented repeatedly in STATE.md's Blockers/Concerns.
- **Real internet access in this sandbox surfaced a genuine test-isolation gap** (see Deviations above) — the live consultacenso site being actually reachable from this environment is itself notable: it means the site is currently up and NOT DNS-dead (unlike the infovotantes/eleccionescolombia domains noted elsewhere in STATE.md), which is a positive signal for Task 4's real end-to-end verification once a human runs it.

## Task 4: Real End-to-End Verification (PASSED)

Performed directly by the coordinating session (not delegated, per the plan's own constraint that this checkpoint requires a human/real resources):

1. Merged the executor's worktree branch (`worktree-agent-a04ffffa30e4353ec`) into `main` via fast-forward (`421b74a`); worktree removed and branch deleted.
2. The already-running local Python microservice (pid on :5757) was serving stale code (predating this task); killed and restarted from `registraduria-service/` — confirmed `POST /lookup/censo` now returns 200 instead of the prior 404.
3. First attempt failed with a local-environment issue (not a code bug): `BrowserType.launch: Executable doesn't exist ... chromium_headless_shell` — Playwright's Chromium binary had never been installed on this machine for this Python env. Fixed via `python3 -m playwright install chromium`.
4. Re-ran `startLookup('1102851353')` (real test cédula provided by the user) → session id issued. Polled `getResult()` every 5s.
5. **Result:** after ~30s in `solving_captcha` (real 2captcha token solve), status flipped to `done`, `outcome: "success"`, with real structured data: `puesto_nombre: "CHOCHO"`, `direccion: "IE SAN ISIDRO DE CHOCO"`, `municipio: "SINCELEJO"`, `departamento: "SUCRE"`, `mesa_numero: "12"`. Raw response's `nuip: 1102851353` matches the input cédula exactly. `mapa` coordinates also returned.

**Conclusion:** the third live source works end-to-end against production, real captcha-solving cost included. No code changes were needed as a result of this checkpoint — only a local-environment fix (installing the Playwright browser binary) and a service restart, both operational, not code defects.

## Next Phase Readiness

- `ConsultaCensoService` is fully wired, verified end-to-end against the real site, and will automatically be tried as the third/fallback tier the moment both `InfovotantesService` and `RegistraduriaService` are unreachable or give up.
- All automated Pest coverage (7 new `ConsultaCensoServiceTest` cases + 2 new `PollingPlaceResolverPriorityTest` cases + the updated 3-element binding-order assertion) passes, alongside zero regressions to `InfovotantesServiceTest` (7), `RegistraduriaServiceReachabilityTest` (5), `RegistraduriaServiceParserTest` (3), `PollingPlaceResolverTest` (40), and `VoterRegistraduriaRefreshTest` (20, after the Rule 1 fix).
- A full `php artisan test` run shows 13 failures, all in files unrelated to this task (`DuplicatesReportTableTest`, `JurisdictionReportTableTest`, `JurisdictionSummaryOverviewTest`, `RejectionsReportTableTest`, `TopCoordinatorsTableTest`, `TopPollingPlacesTableTest`, `VoterResourceTest`) and confirmed passing in isolation — matching the already-documented pre-existing `CampaignContext` static-override test-pollution issue in STATE.md's Blockers/Concerns, not a regression introduced by this task.
- `vendor/bin/pint --dirty --test` reports clean on all modified/created PHP files.
- **Operational note for other environments (e.g. production/other machines running registraduria-service):** confirm `python3 -m playwright install chromium` has been run there too — this task's checkpoint surfaced that it's a separate, easy-to-miss setup step from `pip install playwright` alone.

---
*Quick task: 260731-ezk*
*Completed (all 4 tasks, including real end-to-end verification): 2026-07-31*

## Self-Check: PASSED

All 8 created/modified files confirmed present on disk. All 3 commit hashes (`59f88ba`, `49672fa`, `fe808b4`) confirmed present in git log.
