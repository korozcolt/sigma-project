---
phase: quick-260508-wze
plan: 01
subsystem: registraduria
tags: [headless-browser, screenshot-proxy, playwright, filament, alpine]
dependency_graph:
  requires: [registraduria-service, HasRegistraduriaPolling, VoterForm]
  provides: [headless-registraduria-modal, screenshot-proxy-endpoints]
  affects: [VoterResource, voter-create, voter-edit]
tech_stack:
  added: []
  patterns: [screenshot-proxy, alpine-modal, php-proxy-controller]
key_files:
  created:
    - app/Http/Controllers/RegistraduriaController.php
    - resources/views/filament/registraduria-browser.blade.php
    - tests/Feature/RegistraduriaControllerTest.php
  modified:
    - registraduria-service/app.py
    - routes/web.php
    - app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php
    - app/Filament/Resources/Voters/Schemas/VoterForm.php
decisions:
  - CSRF for POST /click handled via X-CSRF-TOKEN header in Alpine fetch (no exemption needed)
  - Placeholder component used in Filament schema to embed Blade modal (not a separate Livewire component)
  - session_contexts uses separate dict from sessions to avoid locking contention
  - Page stored in session_contexts before navigating so screenshots are available immediately
metrics:
  duration: 25 min
  completed: 2026-05-08
  tasks: 4
  files_changed: 7
---

# Phase quick-260508-wze Plan 01: Registraduria Headless Proxy Screenshots Summary

**One-liner:** Headless Playwright service with screenshot/click/viewport proxy, embedded Alpine.js modal inside SIGMA Filament voter form — operator never leaves the page.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Upgrade Python service to headless + screenshot/click/viewport endpoints | 768e592 | registraduria-service/app.py |
| 2 | Create RegistraduriaController PHP proxy + auth routes | 9beacdf | app/Http/Controllers/RegistraduriaController.php, routes/web.php |
| 3 | Update HasRegistraduriaPolling trait, VoterForm, create Blade modal | e573761 | HasRegistraduriaPolling.php, VoterForm.php, registraduria-browser.blade.php |
| 4 | Write Pest feature tests and run Pint | 55623e0 | tests/Feature/RegistraduriaControllerTest.php |

## What Was Built

**Python service (headless):**
- `headless=True` with stealth args: `--disable-blink-features=AutomationControlled`, `--no-sandbox`, `--disable-dev-shm-usage`
- Custom user agent matching Chrome 120 on Windows
- `session_contexts` dict tracks live `{page, browser}` objects per session, protected by `session_contexts_lock`
- Page stored in `session_contexts` before navigation — screenshots available immediately
- Thread fills cedula only, does NOT click any button — operator interacts via screenshot modal
- 180s result detection timeout (up from 120s)
- New endpoints: `GET /screenshot/<id>` (PNG), `POST /click/<id>` (mouse click), `GET /viewport/<id>` (dimensions)
- `finally` block closes browser then removes from `session_contexts`

**PHP controller (`RegistraduriaController`):**
- Proxies all 5 routes to Python service at `config('services.registraduria.url')`
- `screenshot()` returns `image/png` with `no-store` cache headers
- All methods catch exceptions and return clean JSON error responses with appropriate HTTP status codes
- No CSRF exemption needed — `click` POST uses `X-CSRF-TOKEN` header in Alpine fetch

**Routes (`routes/web.php`):**
- 5 routes inside `auth` middleware group: `registraduria.lookup`, `registraduria.result`, `registraduria.screenshot`, `registraduria.click`, `registraduria.viewport`

**Trait (`HasRegistraduriaPolling`):**
- `$registraduriaSessionId: string` and `$registraduriaOpen: bool` Livewire properties
- `openRegistraduriaBrowser(string $cedula)` — starts Python lookup, sets modal open
- `handleRegistraduriaResult(array $result)` — fills municipality, polling place, mesa fields; shows notification
- `closeRegistraduriaBrowser()` — resets modal state
- Removed old `pollRegistraduria()` method entirely

**VoterForm changes:**
- `Placeholder::make('_registraduria_modal')` added as first schema component — renders `registraduria-browser` Blade view when `$registraduriaOpen` is true
- `suffixAction` now calls `$livewire->openRegistraduriaBrowser($state)` — single line
- Removed `extraAttributes` Alpine polling from "Información Personal" Section
- Removed unused imports: `RegistraduriaService`, `Notification`
- Added imports: `Placeholder`, `HtmlString`

**Blade modal (`registraduria-browser.blade.php`):**
- Alpine `x-data` with `sessionId`, `status`, `viewport`, `screenshotSrc`
- Screenshot src updated every 400ms via `setInterval`
- Status polled every 2s via `fetch /registraduria/result`
- On `done`: 1.5s delay then `$wire.handleRegistraduriaResult(data)`
- On `error`: shows error state with close button
- Click on image: scales coordinates to viewport dimensions, POSTs to `/registraduria/click` with `X-CSRF-TOKEN`
- Escape key closes modal via `$wire.closeRegistraduriaBrowser()`

**Tests (`RegistraduriaControllerTest`):**
- 10 Pest tests, 18 assertions, all pass
- `Http::fake()` prevents real Python service calls
- Covers: unauthenticated redirect, lookup success/validation/503, result 200/404, screenshot PNG, click success/validation, viewport

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing critical behavior] Screenshot returns 404 when service returns 404**

The plan constraints specified screenshot proxying 404 when service returns 404 but the initial controller draft used a generic `abort(502)` for all non-successful responses.

- **Found during:** Task 2 review
- **Fix:** Added explicit 404 check before the generic error path in `screenshot()` method
- **Files modified:** app/Http/Controllers/RegistraduriaController.php
- **Commit:** 9beacdf

## Known Stubs

None — all data flows are wired. The modal renders real screenshot data from the Python service, form fields are filled from real Registraduria result data.

## Self-Check: PASSED

Files exist:
- app/Http/Controllers/RegistraduriaController.php: FOUND
- resources/views/filament/registraduria-browser.blade.php: FOUND
- app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php: FOUND
- app/Filament/Resources/Voters/Schemas/VoterForm.php: FOUND
- tests/Feature/RegistraduriaControllerTest.php: FOUND
- registraduria-service/app.py: FOUND

Commits exist:
- 768e592: FOUND
- 9beacdf: FOUND
- e573761: FOUND
- 55623e0: FOUND
