---
status: resolved
trigger: "registraduria-interactive-result-not-parsed"
created: 2026-07-26T00:00:00Z
updated: 2026-07-26T18:30:00Z
---

## Current Focus

hypothesis: CONFIRMED and FIXED (both bugs). Human verification complete.
test: N/A — user confirmed in real browser (Herd, sigma-project.test) that both fixes work end-to-end with real data.
expecting: N/A
next_action: none — session resolved and archived.

## Symptoms

expected: Al completar la consulta interactiva de Registraduría (captcha resuelto, outcome "success"), el formulario de Apoyo debe autocompletar NUIP, DEPARTAMENTO, MUNICIPIO, PUESTO, DIRECCIÓN y MESA.
actual: El modal muestra "Error desconocido al consultar la Registraduría" aunque el microservicio Python devolvió outcome "success" con HTML válido conteniendo los datos reales del votante.
errors: Mensaje de error genérico "Error desconocido al consultar la Registraduría" mostrado en el modal Alpine.js, sin excepción visible en logs de Laravel (la petición nunca llega a fallar del lado del servidor, solo el parsing de campos queda vacío).
reproduction: Crear un nuevo Apoyo, usar el botón "Consultar Registraduría", esperar a que el polling llegue a outcome success. El polling del navegador consulta directamente RegistraduriaController::result() (routes/web.php, prefijo registraduria/), no RegistraduriaService::getResult().
started: Nunca se verificó en navegador real hasta esta sesión; el bug pudo existir desde que se implementó el flujo interactivo.

## Eliminated

## Evidence

- timestamp: 2026-07-26T00:05:00Z
  checked: app/Http/Controllers/RegistraduriaController.php::result()
  found: "`return response()->json($response->json());` — raw passthrough of the Python microservice's /result/{id} JSON, no parsing of raw_message_html into structured fields."
  implication: Confirms controller never calls parseConsultaHtml.

- timestamp: 2026-07-26T00:05:00Z
  checked: app/Services/RegistraduriaService.php::getResult() and private parseConsultaHtml()
  found: "getResult() DOES call parseConsultaHtml() when status==done and data.raw_message_html present, converting HTML table into 7 structured fields (puesto_nombre, puesto_codigo, zona_codigo, mesa_numero, departamento, municipio, direccion). parseConsultaHtml() uses no $this state — pure function of $html."
  implication: The parsing logic exists and is correct/tested (RegistraduriaServiceParserTest.php) but lives only in the class method that the browser polling loop never calls.

- timestamp: 2026-07-26T00:05:00Z
  checked: resources/views/filament/registraduria-browser.blade.php (Alpine.js start())
  found: "`fetch('/registraduria/result/' + this.sessionId)` — hits the Laravel route directly (registraduria.result -> RegistraduriaController::result()), bypassing RegistraduriaService entirely. On status 'done', dispatches `window.Livewire.dispatch('registraduria-result', { data: d.data })` with the UNPARSED data (which only contains raw_message_html, not puesto_nombre)."
  implication: Confirms the reproduction path in symptoms — browser polling never touches RegistraduriaService::getResult().

- timestamp: 2026-07-26T00:05:00Z
  checked: app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php::handleRegistraduriaResult()
  found: "`if (empty($data) || empty($data['puesto_nombre'] ?? '')) { ... 'Error desconocido al consultar la Registraduría' ... }` — since unparsed data has no puesto_nombre key, this branch always triggers on a real successful outcome."
  implication: Root cause fully confirmed end-to-end: Python success -> Controller raw passthrough -> Alpine dispatches unparsed data -> Livewire trait sees missing puesto_nombre -> shows generic error despite real success.

- timestamp: 2026-07-26T00:10:00Z
  checked: routes/web.php registraduria prefix group + tests/Feature/RegistraduriaControllerTest.php + tests/Feature/Services/RegistraduriaServiceParserTest.php
  found: "Existing controller test 'proxies result status from python service' only asserts top-level status passthrough for waiting_captcha (no data), so it doesn't currently cover/lock-in the raw-passthrough bug for a done+raw_message_html payload. Service parser test only exercises RegistraduriaService::getResult(), not the controller route."
  implication: No existing test would break by fixing the controller to parse; a new regression test is needed to cover the controller path with real fixture HTML.

- timestamp: 2026-07-26T00:40:00Z
  checked: "Human verify checkpoint response — real browser save attempt"
  found: "QueryException 1366 Incorrect integer value: '' for column 'zone_code' on INSERT into polling_places(municipality_id=1007, zone_code='', place_code='', name='IE SAN JOSE C I P', address='CL 22 No. 10A-380', department_id=37, max_tables=0). Prior query log shows municipality resolved fine (id 1007), department resolved fine (id 37), and a SELECT on polling_places with zone_code='' AND place_code='' found no match, triggering the INSERT half of firstOrCreate."
  implication: "The ORIGINAL parsing fix (normalizeResultPayload) is confirmed working end-to-end in the real browser — puesto_nombre/departamento/municipio/direccion/mesa all populated correctly. This is a distinct, previously-undiscovered bug in the polling_places firstOrCreate step, only reachable once real fields flow all the way through (which the interactive path had never done before this session's fix)."

- timestamp: 2026-07-26T00:45:00Z
  checked: "app/Services/RegistraduriaService.php parseConsultaHtml() docblock (lines 99-105) + $labelToField map (lines 141-150)"
  found: "Explicit comment: 'header labels are NUIP, DEPARTAMENTO, MUNICIPIO, PUESTO, DIRECCIÓN, MESA — there is no \"CODIGO PUESTO\"/\"ZONA\" column in the real response, so puesto_codigo and zona_codigo remain empty unless a future response shape includes them.' Default $fields array always sets 'puesto_codigo' => '' and 'zona_codigo' => ''."
  implication: "This is not a parsing bug — the real Registraduría response genuinely never carries these codes. puesto_codigo/zona_codigo will be '' for EVERY live lookup, always. The bug is entirely in how downstream code (HasRegistraduriaPolling, PollingPlaceResolver) handles that always-blank case."

- timestamp: 2026-07-26T00:48:00Z
  checked: "app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php:316-332 (fillPollingPlaceFields) and app/Services/PollingPlaceResolver.php:263-288 (resolveOrCreatePollingPlace)"
  found: "Both methods independently implement the exact same buggy pattern: `$placeCode = $data['puesto_codigo'] ?? substr($data['puesto_nombre'] ?? '', 0, 2);` and `'zone_code' => $data['zona_codigo'] ?? null`. Since the keys always exist (never absent, just ''), `??` never falls back — '' passes straight through into PollingPlace::firstOrCreate()'s where/create arrays. The substr() fallback is dead code (never reachable since the key is never truly absent) and is itself nonsensical for an unsignedSmallInteger column (would produce a 2-letter string, not a number)."
  implication: "Confirms duplicated logic in two places, both broken identically — matches the same pattern this whole debug session already found once (parsing logic existing in one place but not being reused by the other consumer). Also confirms `PollingPlaceResolver::resolveAutomated()` (used by the scheduled `ReconcileFallbackPollingPlaces` job) hits the identical QueryException on every real live success for a municipality/puesto combo not already in polling_places — this is a systemic bug, not modal-specific."

- timestamp: 2026-07-26T00:52:00Z
  checked: "database/migrations/2026_01_22_000001_create_polling_places_table.php + 2026_05_09_052304_make_dane_codes_nullable_in_polling_places.php; app/Models/PollingPlace.php; database/factories/PollingPlaceFactory.php"
  found: "zone_code/place_code are unsignedSmallInteger NOT NULL with unique(municipality_id, zone_code, place_code). Only dane_department_code/dane_municipality_code were made nullable in the 05-09 migration; zone_code/place_code were left NOT NULL. No cast on the model. All existing factories/tests always supply non-blank zone_code/place_code (real DIVIPOLE-style ints or numeric strings) — none exercise the blank-live-lookup case."
  implication: "A migration is required to make zone_code/place_code nullable so a live-sourced puesto without real DIVIPOLE codes can be stored without violating the NOT NULL constraint. MySQL's unique index treats multiple NULLs as distinct, so this doesn't reintroduce false collisions between different codeless puestos, PROVIDED the matching logic also switches to name-based lookup when codes are blank (a bare zone_code IS NULL AND place_code IS NULL match would otherwise incorrectly reuse the first codeless row for every subsequent different-named puesto in the same municipality)."

- timestamp: 2026-07-26T00:55:00Z
  checked: "app/Services/PollingPlaceResolver.php:55-104 resolveFromCampaignCensus() (existing precedent) + grep across tests for puesto_codigo/zona_codigo/zone_code/place_code"
  found: "resolveFromCampaignCensus() already matches an existing PollingPlace by (municipality_id, name) when DIVIPOLE codes aren't known from campaign census data — an established precedent in this codebase for name-based matching in the absence of codes. Confirmed via grep that no test ever passes blank puesto_codigo/zona_codigo through resolveAutomated() or the interactive handleRegistraduriaResult() event."
  implication: "Fix should follow the same name-based-matching precedent already used elsewhere in this class, and needs new regression tests covering the blank-code shape for both the automated and interactive paths — since none currently exist."

- timestamp: 2026-07-26T01:15:00Z
  checked: "Whether Livewire::test(...)->dispatch('registraduria-result', ...)->call('save') persists polling_place_id to the voters table (tried with BOTH blank and valid non-blank puesto_codigo/zona_codigo via a throwaway scratch test)."
  found: "polling_place_id remains null in the DB after ->call('save') in the Livewire test harness EVEN with fully valid, non-blank codes — i.e. this is a pre-existing behavior unrelated to the zone_code/place_code fix, most likely because the Select::make('polling_place_id') form field is conditionally disabled() based on municipality_id and Filament does not dehydrate disabled fields unless the browser's own reactive re-render (which the test harness's chained dispatch()+call() doesn't fully replicate) has already flipped it to enabled before submission."
  implication: "Out of scope for this debug session. Not a regression introduced by this fix. The regression test for the zone_code bug asserts the Livewire form's in-memory `data.polling_place_id` (which IS correctly populated) and the PollingPlace row itself (created with null codes, matched by name), rather than asserting a DB-persisted voter.polling_place_id via ->call('save'), since that persistence gap is independent and pre-existing."

## Resolution

root_cause: |
  BUG 1 (human-confirmed FIXED — parsing): RegistraduriaController::result() (the endpoint the Alpine.js polling loop in registraduria-browser.blade.php calls via fetch('/registraduria/result/'+id) for the interactive 'Consultar Registraduría' modal) returned `response()->json($response->json())` — a raw passthrough of the Python microservice's JSON, including the unparsed `raw_message_html` field. The HTML-to-structured-fields parsing (`parseConsultaHtml()`) only existed inside `RegistraduriaService::getResult()`, which this controller/route never calls. So on a real successful outcome, the browser received data without `puesto_nombre`, and `HasRegistraduriaPolling::handleRegistraduriaResult()` treated the missing `puesto_nombre` as an error, showing 'Error desconocido al consultar la Registraduría' even though the lookup genuinely succeeded.

  BUG 2 (found during human verification of Bug 1's fix — save-time crash): once parsing worked, saving surfaced a NEW crash: `QueryException 1366 Incorrect integer value: '' for column 'zone_code'`. Real Registraduría HTML responses NEVER include a "CODIGO PUESTO"/"ZONA" column (documented in parseConsultaHtml()'s own docblock), so `puesto_codigo`/`zona_codigo` are ALWAYS empty string for every live lookup — not an edge case, the normal case. Both `HasRegistraduriaPolling::fillPollingPlaceFields()` (interactive path) and `PollingPlaceResolver::resolveOrCreatePollingPlace()` (automated/reconciliation path — duplicated logic) used `$data['zona_codigo'] ?? null` / `$data['puesto_codigo'] ?? substr(...)`; since the keys always exist (set to '' by the parser, never absent), `??` never fell back, so '' was passed straight into `PollingPlace::firstOrCreate()` against NOT NULL `unsignedSmallInteger` columns. This affected BOTH the interactive modal AND the scheduled `ReconcileFallbackPollingPlaces` job (same underlying resolver method), meaning every real live success for a not-yet-known puesto would have crashed either path.

fix: |
  BUG 1: Extracted the done+raw_message_html normalization into a new public static `RegistraduriaService::normalizeResultPayload(array $payload): array`, which calls the existing (now static) private `parseConsultaHtml()`. `RegistraduriaService::getResult()` now delegates to it, and `RegistraduriaController::result()` now calls `RegistraduriaService::normalizeResultPayload($response->json())` before returning JSON, so both the Livewire cascade path and the browser's direct polling path return identically parsed fields.

  BUG 2: (1) New migration `2026_07_26_164628_make_zone_and_place_code_nullable_in_polling_places.php` makes `polling_places.zone_code`/`place_code` nullable (MySQL treats multiple NULLs in a unique index as distinct, so real DIVIPOLE-code uniqueness is unaffected). (2) `PollingPlaceResolver::resolveOrCreatePollingPlace()` made `public` (was `private`) and rewritten: computes `zoneCode`/`placeCode` via `filled()` checks instead of `??` (so blank strings correctly become `null`); when both codes are present, keeps the original match/create-by-DIVIPOLE-codes behavior; when either is blank, matches/creates by `(municipality_id, name)` instead — following the existing precedent already used by `resolveFromCampaignCensus()` elsewhere in the same class — avoiding both the NOT NULL violation and a false "first codeless row wins" collision between differently-named puestos in the same municipality. (3) `HasRegistraduriaPolling::fillPollingPlaceFields()` no longer duplicates the buggy inline `firstOrCreate()` — it now delegates to `app(PollingPlaceResolver::class)->resolveOrCreatePollingPlace($data)`, so the interactive and automated paths can no longer drift out of sync with each other again.

verification: |
  BUG 1: human-confirmed working in the real browser (this checkpoint) — parsed fields (puesto/mesa/etc.) now populate correctly, modal no longer shows "Error desconocido".

  BUG 2: Automated — added regression tests: `tests/Feature/Services/PollingPlaceResolverTest.php` (3 new tests: creates a codeless PollingPlace without throwing when codes are blank; matches an existing codeless PollingPlace by name instead of duplicating; creates a separate codeless PollingPlace for a different-named puesto in the same municipality) and `tests/Feature/Filament/VoterRegistraduriaRefreshTest.php` (1 new test: dispatching the real blank-code payload through `handleRegistraduriaResult()` no longer throws, `polling_place_source` transitions to LIVE, and the created PollingPlace has null zone_code/place_code). Full related suites pass: PollingPlaceResolverTest (22), VoterRegistraduriaRefreshTest (19), RegistraduriaControllerTest (11), RegistraduriaServiceParserTest (3), RegistraduriaServiceReachabilityTest (5), PollingPlaceResolverPriorityTest (5), MaxTablesForPollingPlaceTest (1), ImportNationalCensusTest (7), InfovotantesServiceTest (7), ReconcileFallbackPollingPlacesTest (9). Full suite run: 993 passed, 1 pre-existing unrelated flaky failure (`UserResourceTest > can update user campaigns` — fails only when run as part of the full suite, passes in isolation; a faker-generated phone number occasionally fails phone-format validation; unrelated to any file touched in this session). Pint clean.
  Manual/real: HUMAN-CONFIRMED (2026-07-26, checkpoint response: "ahora si, resuelto") — verified in real browser (Herd, sigma-project.test) that after both fixes: the modal no longer shows "Error desconocido al consultar la Registraduría", the fields autocomplete correctly, and saving no longer throws the zone_code QueryException. The full interactive "Consultar Registraduría" flow works end-to-end with real data.

files_changed:
  - app/Services/RegistraduriaService.php
  - app/Http/Controllers/RegistraduriaController.php
  - app/Services/PollingPlaceResolver.php
  - app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php
  - database/migrations/2026_07_26_164628_make_zone_and_place_code_nullable_in_polling_places.php
  - tests/Feature/RegistraduriaControllerTest.php
  - tests/Feature/Services/PollingPlaceResolverTest.php
  - tests/Feature/Filament/VoterRegistraduriaRefreshTest.php
