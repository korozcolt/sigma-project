---
status: awaiting_human_verify
trigger: "2captcha-duplicate-spend"
created: 2026-08-14T00:00:00Z
updated: 2026-08-14T01:30:00Z
---

## Current Focus

hypothesis: CONFIRMED. All 5 findings validated against code with direct evidence. Fix implemented, self-verified via full Pest suite (1557 passed) + Pint. Awaiting human browser/production verification per user's standing preference (Pest/Livewire tests aren't enough for prod-bound changes).
test: n/a — implementation complete.
expecting: n/a
next_action: User runs `php artisan migrate` (new registraduria_live_sessions table) on sigma-betha/Aldemar, confirms queue worker (scripts/worker.sh) is running so CollectRegistraduriaLookupResult actually gets processed, then browser-verifies the interactive "consulta en curso" cooldown and watches next hourly census:reconcile-live/validation runs for reduced reconciliation_attempts churn and a populated SaldosBadge widget.

## Symptoms

expected: Cada cédula se paga en 2captcha como máximo una vez por resultado útil. Ningún resultado ya pagado se pierde. El "costo promedio (últimos 7 días)" del widget SaldosBadge refleja el gasto real dividido entre consultas realmente hechas.
actual:
1. TwoCaptchaDailyCostService::forDay() infiere costo dividiendo delta de saldo (numerador correcto) entre conteo de filas nuevas en registraduria_lookups source='live' (denominador subcontado) porque attemptLiveAutomated() hace timeout (~40-45s) antes de que el microservicio Python termine de resolver (hasta ~150s), y nadie vuelve a consultar el resultado huérfano.
2. Mismo timeout hace que ReconcileFallbackPollingPlaces y DispatchCensusRevalidation cuenten timeouts artificiales como fracasos genuinos vía reconciliation_attempts (MAX=5) -> reconciliation_exhausted_at tras pagar 5 veces.
3. census:reconcile-live y census:reconcile-validation ambos ->hourly(), sin lock por cédula -> posible doble pago por carrera.
4. Flujo interactivo HasRegistraduriaPolling::startLiveLookup() sin verificar sesión en curso; refresh/reabrir modal/retry generan pago duplicado.
5. forceRefreshFromRegistraduria() sin cooldown -> clics repetidos pagan cada vez.
errors: Ninguno — problema de lógica de negocio, confirmado por lectura de código en rondas previas. Debe validarse leyendo código directamente.
reproduction:
- Escenario 1: cédula que tarda >45s en resolver captcha, background job.
- Escenario 2: misma cédula repetida cada hora hasta agotar reconciliation_attempts=5.
- Escenario 3: cédula pendiente en el mismo minuto que corren ambos reconcile commands.
- Escenario 4: admin abre modal, refresca/reintenta antes de resolver.
started: Comportamiento existente desde implementación del flujo live/2captcha, notado 2026-08-14 vía guiones en widget de saldo.

## Eliminated

- hypothesis: Dispatch CollectRegistraduriaLookupResult unconditionally from PollingPlaceResolver::startLiveLookup() too (interactive/force-refresh path), to also recover an orphaned INTERACTIVE result, not just the automated cascade's.
  evidence: tests/Feature/Services/PollingPlaceResolverPriorityTest.php calls startLiveLookup() directly against REAL, container-bound, named adapter classes (RegistraduriaService) with only a `/lookup` Http::fake() registered (no `/result/{id}` fake). phpunit.xml sets QUEUE_CONNECTION=sync, so any dispatch() would run the collector job INLINE within the test, calling the real adapter's getResult() against an unfaked URL -> Laravel's default stray-request stub (empty 200 body) -> RegistraduriaService::normalizeResultPayload(null) -> TypeError (strict `array` param). Confirmed this is a real, not theoretical, risk to 7 existing passing tests. Scoped the fix down to only dispatch from attemptLiveAutomated()'s exhausted-timeout branch (never exercised by PollingPlaceResolverPriorityTest, which only tests immediate 'done' responses), which is also where the actual escenario-1 money-losing bug lives per the user's own framing.
  timestamp: 2026-08-14T01:00:00Z

## Evidence

- timestamp: 2026-08-14T00:15:00Z
  checked: registraduria-service/app.py (full file)
  found: sessions dict (module-level, in-memory only, thread-safe via sessions_lock) is the SOLE store for a lookup's progress/result. `/result/<session_id>` (line 519) just reads from this dict — no expiry, no cleanup, works for as long as the Flask process lives and the session_id is known. `_lookup_async()` (wsp flow) polls 2captcha's own res.php every 5s for up to 30 attempts (150s) in a background thread started by `/lookup` (daemon=True), completely decoupled from whether/how long Laravel keeps polling `/result/{id}`. Confirms: an orphaned result (Laravel gives up polling) is NOT actually lost on the Python side — it sits in `sessions` dict, fully retrievable via a LATER `/result/{same_session_id}` call, as long as the same Python process is still running. This is what makes the "keep the session_id and re-poll later via a background job" fix (vs. discarding it) both correct and sufficient — no Python-side changes needed.
  implication: Confirms escenario 1's root cause AND confirms the fix direction (b) from additional_context is viable purely from the Laravel side.

- timestamp: 2026-08-14T00:20:00Z
  checked: app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php, resources/views/filament/registraduria-browser.blade.php, app/Http/Controllers/RegistraduriaController.php
  found: openRegistraduriaBrowser() (interactive) and forceRefreshFromRegistraduria() both call PollingPlaceResolver::startLiveLookup($cedula) directly with no check for an already-in-flight session for that cedula. registraduriaSessionId/registraduriaOpen (lines 18-20) are plain Livewire public properties — survive AJAX round-trips within the SAME mounted component instance, but NOT a full page reload (Livewire remounts fresh on reload, losing them). registraduria-browser.blade.php's Alpine `start()` (line 49) has its own independent ~200s client-side cap (line 57) that force-closes the modal and gives up, with NO server-side awareness that the underlying live/2captcha attempt might still resolve after that. forceRefreshFromRegistraduria() (line 152) has zero throttle/cooldown beyond the superadmin role gate and the REGISTRADURIA_LIVE_ENABLED kill switch. Confirms escenario 4 (refresh/reopen/retry -> duplicate paid startLookup call) and finding 5 (repeated force-refresh clicks) exactly as described.
  implication: Both findings need the SAME mechanism as the automated cascade's race guard — a claim keyed by document_number, checked before any $adapter->startLookup() call, anywhere in the codebase.

- timestamp: 2026-08-14T00:30:00Z
  checked: app/Jobs/ReconcileFallbackPollingPlaces.php, app/Jobs/DispatchCensusRevalidation.php, app/Services/VoterValidationService.php, routes/console.php
  found: routes/console.php:17,20 confirms both `census:reconcile-live` and `census:reconcile-validation` are `->hourly()->withoutOverlapping(10)` — withoutOverlapping is scoped per-command (self-overlap only), NOT cross-command, so nothing prevents both from running in the same minute. `grep -rn "Cache::lock|lockForUpdate"` across app/ confirms the ONLY lockForUpdate() usage in the codebase is VoterDuplicateAssignmentService (unrelated to Registraduria). DispatchCensusRevalidation::handle() -> VoterValidationService::validateAndUpdate() -> validateAgainstCensus() -> PollingPlaceResolver::resolveAutomated() (VoterValidationService.php:52) — confirms BOTH jobs funnel through the exact same resolveAutomated()/attemptLiveAutomated() cascade and can select overlapping voter sets (DispatchCensusRevalidation additionally selects on `status IN (...) OR polling_place_source IS NULL`, which overlaps ReconcileFallbackPollingPlaces's `polling_place_source != LIVE OR NULL` selection for a voter with e.g. status=PENDING_REVIEW and polling_place_source=NULL). Confirms escenario 3 exactly as described.
  implication: A single claim/lock mechanism placed inside PollingPlaceResolver (the shared chokepoint both jobs funnel through) fixes escenario 3 without needing to touch scheduling/withoutOverlapping at all.

- timestamp: 2026-08-14T01:15:00Z
  checked: Full Pest suite after implementing the fix (php artisan test) + vendor/bin/pint --dirty
  found: 1557 passed (4187 assertions), 0 failures, 0 regressions across the entire existing suite — including the 46 PollingPlaceResolverTest cases, 7 PollingPlaceResolverPriorityTest cases (which use REAL, container-bound, named adapter classes + Http::fake(), the exact scenario that would have broken under the eliminated approach above), both reconciliation Job test files, and all VoterRegistraduriaRefreshTest interactive-flow cases. Pint reports 14 files checked, PASS, no style changes needed.
  implication: The fix is additive/non-breaking against the pre-existing extensive regression-test coverage for this subsystem.

## Evidence

- timestamp: 2026-08-14T00:05:00Z
  checked: app/Services/TwoCaptchaDailyCostService.php:17-68
  found: Docblock on forDay() (lines 17-24) already documents the exact undercounting bias claimed in finding 1: "Un force-refresh de un documento ya resuelto hace updateOrCreate sobre la fila existente sin cambiar created_at, por lo que este conteo puede subestimar los solves reales de 2captcha ese día." Denominator query (lines 57-61) counts registraduria_lookups rows with source='live' created within the day window.
  implication: Confirms denominator undercounting is a known, real, already-partially-diagnosed issue. Need to verify root mechanism (timeout causing lost/orphaned results) via PollingPlaceResolver + app.py, not just via the force-refresh updateOrCreate case already documented.

- timestamp: 2026-08-14T00:10:00Z
  checked: app/Services/PollingPlaceResolver.php full file
  found: LIVE_POLL_ATTEMPTS=9, LIVE_POLL_INTERVAL_MS=5000 confirmed (lines 19-21). attemptLiveAutomated() (317-352) starts a session via adapter->startLookup(), then polls up to 9x every 5s (~40s), returns null on any non-'done' outcome after exhausting polls -- no further attempt to reclaim the result later. resolveAutomated() (366-402): on non-success from a reachable adapter, `break`s out of the live loop entirely (falls to snapshot tier), and does NOT persist anything for that cedula, meaning if the Python microservice finishes solving later (paid), nothing in Laravel ever asks for that result again -- confirmed orphaned-result mechanism for finding 1. persistPermanentLookup() (182-198) is the ONLY path that writes to registraduria_lookups with source=LIVE, and it's only called after attemptLiveAutomated() returns a successful array within the 40s window.
  implication: Finding 1 root mechanism confirmed at the Laravel side: timeout->give up->no persistence->no re-check ever. Need to confirm Python side actually continues resolving in background per additional_context claim (app.py) and doesn't expose a way to re-poll conveniently, and confirm HasRegistraduriaPolling has same abandon-on-timeout behavior for the interactive flow.

## Resolution

root_cause: |
  Five confirmed, related root causes, all sharing one missing primitive: nothing in the
  system tracked "a real, already-paid 2captcha attempt is currently in flight for cédula
  X" as durable, shared state.
  1. PollingPlaceResolver::attemptLiveAutomated()'s synchronous ~40s poll window
     (LIVE_POLL_ATTEMPTS=9 x 5s) gives up and returns null when the real microservice
     (registraduria-service/app.py) is still solving via 2captcha (documented up to 150s).
     The Python side keeps resolving in a background daemon thread and the result sits
     retrievable in its in-memory `sessions` dict, but Laravel never asks again — the
     already-paid result is silently discarded, and registraduria_lookups (the widget's
     denominator) never gets a row for it.
  2. The same artificial timeout is indistinguishable, to ReconcileFallbackPollingPlaces
     and DispatchCensusRevalidation, from a genuine failure — both bump
     Voter.reconciliation_attempts (shared counter) on it, so a cédula that's simply slow
     (not actually unresolvable) gets paid for and discarded up to 5 times over 5 hourly
     runs before reconciliation_exhausted_at permanently excludes it.
  3. census:reconcile-live and census:reconcile-validation are both ->hourly() with only
     per-command withoutOverlapping(10) (no cross-command coordination) and can select
     overlapping voters; both funnel through the same resolveAutomated() cascade with no
     lock, so they can both pay for the same cédula in the same run.
  4. The interactive modal (HasRegistraduriaPolling::openRegistraduriaBrowser()) has no
     awareness of an already-in-flight attempt for the same cédula — a page refresh
     (loses the Livewire component's in-memory session state), a modal close/reopen, or
     a retry after the browser's own ~200s client-side cap all trigger a fresh paid
     startLookup() call while the first may still be genuinely resolving.
  5. forceRefreshFromRegistraduria() (superadmin-only "Actualizar datos") has no
     cooldown/throttle beyond the role gate and the live-enabled kill switch — repeated
     clicks pay every time.
  TwoCaptchaDailyCostService's widget showing dashes was a downstream SYMPTOM of #1, not
  a bug in the widget's own arithmetic (its docblock already documented the narrower,
  separate force-refresh updateOrCreate/created_at approximation) — the real problem was
  that registraduria_lookups was systematically missing rows for slow-but-successful
  live resolutions. Left TwoCaptchaDailyCostService.php untouched; fixing collection (#1)
  fixes the widget's undercounting at the source.

fix: |
  Added App\Models\RegistraduriaLiveSession (migration:
  2026_08_14_000000_create_registraduria_live_sessions_table, unique index on
  document_number) as a single, durable claim/lock + collection-breadcrumb shared by
  every 2captcha-spending code path:
  - PollingPlaceResolver::claimLiveSession()/releaseLiveSession() wrap EVERY call to
    $adapter->startLookup($cedula), both in attemptLiveAutomated() (automated cascade)
    and startLiveLookup() (interactive "consultar"/"actualizar datos" buttons). The
    claim is an atomic INSERT relying on the unique index (no check-then-insert race),
    so a concurrent second attempt for the same cédula fails to claim and is turned
    away BEFORE ever calling a live adapter again — fixes #3, #4, #5.
    startLiveLookup() now throws a new App\Exceptions\RegistraduriaLookupInProgressException
    (a plain \RuntimeException subclass, so every pre-existing generic
    `catch (\Exception $e)` call site keeps working) when a claim already exists;
    HasRegistraduriaPolling now shows a distinct "Consulta en curso" warning
    notification for it instead of the generic connection-error message.
  - attemptLiveAutomated() releases the claim immediately on every terminal outcome
    reachable within its own sync window (success, done-but-blank, waiting_captcha,
    error). On the ONE remaining exit — every poll returned 'pending' and all 9 attempts
    are exhausted — it now KEEPS the claim and dispatches the new
    App\Jobs\CollectRegistraduriaLookupResult onto the REAL queue (ShouldQueue,
    Job::dispatch(), never dispatchSync — picked up by scripts/worker.sh's separate
    `queue:work` process, never blocking schedule:run) with a 90s initial delay. This
    job re-checks GET /result/{session_id} (self-redispatching every 90s) for up to a
    10-minute window (RegistraduriaLiveSession.expires_at): on success it persists the
    permanent lookup, updates the voter (resets reconciliation_attempts, syncs status
    via VoterValidationService — mirroring ReconcileFallbackPollingPlaces's existing
    upgrade branch) and releases the claim; on a genuine terminal failure (error/
    waiting_captcha/blank-done) or true window expiry, it bumps
    reconciliation_attempts ONCE (the first and only bump for that real attempt) and
    releases the claim. Fixes #1 and #2 directly.
  - ReconcileFallbackPollingPlaces and DispatchCensusRevalidation now check for an
    existing RegistraduriaLiveSession row for the voter's document_number before
    bumping reconciliation_attempts on a non-LIVE result — if one exists (this run's own
    just-dispatched collector, the sibling cron's claim, or an interactive claim), the
    bump is skipped entirely since a real attempt is still being collected, not counted
    as a failure.
  - HasRegistraduriaPolling::handleRegistraduriaResult() (the browser's own fast-path,
    definitive-result handler) releases the claim immediately on any `done` outcome,
    so a legitimate follow-up lookup isn't blocked for the full 10-minute window.
  - Dispatch of CollectRegistraduriaLookupResult is gated by
    PollingPlaceResolver::isDispatchableAdapter() (real, named, container-resolvable
    class only, never `@anonymous`) — deliberately scoped out of startLiveLookup()
    (interactive path) after confirming it would have broken
    PollingPlaceResolverPriorityTest.php under phpunit.xml's QUEUE_CONNECTION=sync (see
    Eliminated). The interactive path still gets the full concurrency-guard fix (#4,
    #5); only the narrower "recover a result from an abandoned/closed interactive modal
    after the fact" nice-to-have is out of scope — documented residual gap below.

verification: |
  Self-verified: full Pest suite (php artisan test) — 1557 passed, 4187 assertions, 0
  failures, 0 regressions, including all pre-existing PollingPlaceResolver/
  reconciliation-job/interactive-flow coverage. Added 7 new tests in
  PollingPlaceResolverLiveSessionTest.php (claim/release/dispatch semantics against the
  REAL RegistraduriaService adapter class + Http::fake), 7 in
  CollectRegistraduriaLookupResultTest.php (success/genuine-failure/still-pending/
  window-expiry/no-op paths), 2 in ReconcileFallbackPollingPlacesTest.php +
  DispatchCensusRevalidationTest.php (skip-bump-on-pending-collection), and 3 in
  VoterRegistraduriaRefreshTest.php ("consulta en curso" notification for both entry
  points + claim release on a definitive interactive result). vendor/bin/pint --dirty:
  PASS, 14 files, no changes needed.
  NOT YET verified: could not run `php artisan migrate` against the real sigma_betha_backup
  MySQL database in this sandbox (local MySQL server unreachable/not running here) — the
  migration's schema was exercised indirectly via every RefreshDatabase-based test run
  (1557 passes) but never against real MySQL. Per the user's own standing preference,
  this needs a real browser/production verification pass before being considered done —
  see Current Focus's next_action.
  Known residual gap (accepted, documented): an interactive lookup abandoned before
  resolving (modal closed/refreshed, browser's ~200s cap hit) is NOT retroactively
  recovered by a background collector (that would have required dispatching
  CollectRegistraduriaLookupResult from startLiveLookup() too, which was eliminated —
  see Eliminated). Its claim still fully prevents a duplicate PAYMENT for up to 10
  minutes (finding 4/5 fixed), but if the underlying captcha does resolve after
  abandonment, that specific paid result is not captured — the cédula simply becomes
  payable again after the claim naturally expires. This is strictly better than the
  pre-fix status quo (which had zero protection against re-paying at all) and is a much
  narrower gap than the automated cascade's (fully fixed).

files_changed:
  - database/migrations/2026_08_14_000000_create_registraduria_live_sessions_table.php
  - app/Models/RegistraduriaLiveSession.php
  - database/factories/RegistraduriaLiveSessionFactory.php
  - app/Exceptions/RegistraduriaLookupInProgressException.php
  - app/Jobs/CollectRegistraduriaLookupResult.php
  - app/Services/PollingPlaceResolver.php
  - app/Jobs/ReconcileFallbackPollingPlaces.php
  - app/Jobs/DispatchCensusRevalidation.php
  - app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php
  - tests/Feature/Services/PollingPlaceResolverLiveSessionTest.php
  - tests/Feature/Jobs/CollectRegistraduriaLookupResultTest.php
  - tests/Feature/Jobs/ReconcileFallbackPollingPlacesTest.php
  - tests/Feature/Jobs/DispatchCensusRevalidationTest.php
  - tests/Feature/Filament/VoterRegistraduriaRefreshTest.php
