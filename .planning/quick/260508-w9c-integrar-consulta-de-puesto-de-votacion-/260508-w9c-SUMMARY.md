---
phase: quick
plan: 260508-w9c
subsystem: voter-form
tags: [registraduria, filament, playwright, microservice, polling-place]
dependency_graph:
  requires: []
  provides: [registraduria-lookup, voter-form-autofill]
  affects: [VoterForm, CreateVoter, EditVoter, PollingPlace]
tech_stack:
  added: [Flask 3.1.1, Playwright 1.50.0 (Python)]
  patterns: [Python microservice, Filament suffixAction, Alpine.js polling, Livewire trait]
key_files:
  created:
    - registraduria-service/app.py
    - registraduria-service/requirements.txt
    - app/Services/RegistraduriaService.php
    - app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php
  modified:
    - config/services.php
    - .env.example
    - app/Filament/Resources/Voters/Schemas/VoterForm.php
    - app/Filament/Resources/Voters/Pages/CreateVoter.php
    - app/Filament/Resources/Voters/Pages/EditVoter.php
decisions:
  - Alpine x-data placed on Section extraAttributes (not TextInput extraAlpineAttributes) so the polling scope wraps the entire personal info section, not just the input element
  - PollingPlace find-or-create uses municipality_id + zone_code + place_code to avoid duplicates
  - pollRegistraduria() returns null for pending/waiting_captcha to let Alpine keep polling; returns result for done/error to stop the interval
metrics:
  duration: ~20 min
  completed: 2026-05-08
  tasks: 3 of 3 auto tasks (1 checkpoint skipped per constraint)
  files: 9
---

# Quick Task 260508-w9c: Integrar consulta de puesto de votación (Registraduría)

**One-liner:** Python Flask+Playwright microservice on port 5757 exposes async Registraduria lookup; PHP RegistraduriaService wraps HTTP calls; VoterForm document_number field gets a search-icon suffixAction that triggers the lookup, shows CAPTCHA notification, and polls every 2s via Alpine.js to auto-fill polling_place_id and polling_table_number when result arrives.

## What Was Built

### Task 1: Python Flask+Playwright microservice (commit: 6810e8c)

`registraduria-service/app.py` — Flask service on port 5757:
- `POST /lookup` — receives `{"cedula": "..."}`, spawns a background `threading.Thread` that opens a visible Chromium window (headless=False), navigates to the Registraduria portal, fills the cedula, clicks "Consultar", sets session status to `waiting_captcha`, then waits up to 120 seconds for a result element to appear. Returns `{"session_id": "..."}`.
- `GET /result/<session_id>` — returns `{"status": "pending"|"waiting_captcha"|"done"|"error", "data": {...}|null, "error": "..."|null}`.
- Result parsing: `inner_text("body")` line-by-line, extracting PUESTO, ZONA, MESA, DIRECCIÓN, MUNICIPIO, DEPARTAMENTO fields.
- Thread-safe `sessions` dict protected by `threading.Lock`.

`registraduria-service/requirements.txt`:
```
flask==3.1.1
playwright==1.50.0
```

Start service: `cd registraduria-service && pip install -r requirements.txt && playwright install chromium && python app.py`

### Task 2: PHP RegistraduriaService + config (commits: bf9a7e4, 3b628fd)

- `app/Services/RegistraduriaService.php` — `startLookup(string $cedula): string` posts to `/lookup`, returns session_id. `getResult(string $sessionId): array` polls `/result/{id}`. Both methods log errors and throw descriptive exceptions.
- `config/services.php` — added `'registraduria' => ['url' => env('REGISTRADURIA_SERVICE_URL', 'http://localhost:5757')]`.
- `.env.example` — added `REGISTRADURIA_SERVICE_URL=http://localhost:5757`.

### Task 3: Filament VoterForm integration (commit: 3732732)

**VoterForm.php:**
- Added `use` statements: `Action`, `Notification`, `RegistraduriaService` (kept alphabetical order).
- `document_number` TextInput gains a `suffixAction` with a magnifying-glass icon ("Consultar Registraduría"). The action validates the cedula is not blank, calls `RegistraduriaService::startLookup()`, sends a persistent info notification about the CAPTCHA, and dispatches `registraduria-start-polling` Livewire event with the session_id.
- "Información Personal" Section gains `extraAttributes` with `x-data="{ registraduriaPolling: null }"` and `x-init` that listens for `registraduria-start-polling`, starts a `setInterval` every 2 seconds calling `$wire.call("pollRegistraduria", sessionId)`, and clears the interval when status is done or error.

**HasRegistraduriaPolling trait** (`app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php`):
- `pollRegistraduria(string $sessionId): ?array` — polls `RegistraduriaService::getResult()`.
- On `done`: resolves Municipality (case-insensitive), resolves Department via municipality or direct name match, find-or-creates PollingPlace (municipality_id + zone_code + place_code), sets `$this->data['municipality_id']`, `$this->data['polling_place_id']`, `$this->data['polling_table_number']`, sends success notification.
- On `error`: sends danger notification, returns result.
- On `pending`/`waiting_captcha`: returns null (Alpine keeps polling).

**CreateVoter.php** and **EditVoter.php**: both `use HasRegistraduriaPolling;`.

## Deviations from Plan

### 1. [Rule 2 - Missing detail] Alpine x-data on Section extraAttributes instead of TextInput extraAlpineAttributes

The plan suggested `extraAlpineAttributes` on the TextInput. However, the Filament v4 blade template places `extraAlpineAttributes` on the `<input>` element, not the wrapper div. The `x-data`/`x-init` with `$wire.on()` needs to be on a container element for proper Alpine scope. Using the Section's `extraAttributes` puts the polling state on the section wrapper div, which is a more appropriate container.

### 2. [Rule 1 - Bug] session_id extraction handles both array and object formats

The Alpine.js `$wire.on()` callback in Livewire 3 may receive the dispatched data as an array (`data[0].session_id`) or an object (`data.session_id`), depending on the Livewire version behavior. The x-init code handles both: `data[0].session_id ?? data.session_id`.

### 3. Checkpoint Task 4 skipped

Per execution constraints: the `checkpoint:human-verify` gate was noted but not processed. End-to-end verification requires the Python service to be running and a real CAPTCHA to be solved.

## Checkpoint Skipped

Task 4 is a `checkpoint:human-verify` gate requiring the operator to start the Python service, navigate to Create/Edit Voter, type a cedula, click the search button, and verify the CAPTCHA flow end-to-end. This was skipped per plan execution constraints (note in summary, don't block).

To verify manually:
1. `cd registraduria-service && pip install -r requirements.txt && playwright install chromium && python app.py`
2. Navigate to `/admin/voters/create`
3. Enter cedula in "Número de Documento", click the magnifying glass icon
4. Solve CAPTCHA in the browser window that opens
5. Within ~10s, the polling_place_id and polling_table_number fields should auto-fill

## Known Stubs

None. All data flows are wired: the action calls the live service, the trait resolves and persists data, and form fields are populated via `$this->data`.

## Self-Check: PASSED

Files verified:
- registraduria-service/app.py — FOUND (Python syntax ok)
- registraduria-service/requirements.txt — FOUND
- app/Services/RegistraduriaService.php — FOUND (php -l clean)
- app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php — FOUND (php -l clean)
- app/Filament/Resources/Voters/Schemas/VoterForm.php — FOUND (php -l clean)
- app/Filament/Resources/Voters/Pages/CreateVoter.php — FOUND (php -l clean)
- app/Filament/Resources/Voters/Pages/EditVoter.php — FOUND (php -l clean)

Commits verified:
- 6810e8c — FOUND (Python microservice)
- bf9a7e4 — FOUND (PHP service + config)
- 3b628fd — FOUND (.env.example)
- 3732732 — FOUND (VoterForm + trait + pages)
