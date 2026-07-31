---
phase: quick
plan: 260731-ezk
type: execute
wave: 1
depends_on: []
files_modified:
  - registraduria-service/app.py
  - app/Services/ConsultaCensoService.php
  - app/Providers/AppServiceProvider.php
  - config/services.php
  - .env.example
  - tests/Feature/Services/ConsultaCensoServiceTest.php
  - tests/Feature/Services/PollingPlaceResolverPriorityTest.php
autonomous: false
requirements: []

must_haves:
  truths:
    - "ConsultaCensoService becomes a THIRD, coexisting LiveSourceAdapter, registered last in PollingPlaceResolver's priority-ordered liveAdapters array, tried only when both InfovotantesService and RegistraduriaService are unreachable or fail"
    - "The Python microservice serves a new POST /lookup/censo route from the same Flask process (port 5757), sharing the existing sessions dict and GET /result/<session_id> route, without modifying a single line of the existing /lookup or /lookup/infovotantes flows"
    - "ConsultaCensoService has its own independent isReachable() probe against the real https://consultacenso.registraduria.gov.co/ site, gated by its own consulta_censo.live_enabled kill switch, separate from wsp's and infovotantes' probes"
    - "The Python flow solves consultacenso's standard reCAPTCHA v2 checkbox via 2captcha (no enterprise=1 flag) and calls the site's real JSON API (/back/api/elecciones + /back/api/consulta) directly, no HTML parsing, matching the infovotantes pattern more than the wsp pattern"
    - "Existing RegistraduriaService and InfovotantesService behavior and their existing Pest coverage remain completely unchanged (zero regression)"
    - "A human has manually confirmed, using one of 2 real test cedulas and real 2captcha balance against the live https://consultacenso.registraduria.gov.co/ site, that the new source actually resolves a polling place end-to-end, before this task is considered done"
  artifacts:
    - path: "registraduria-service/app.py"
      provides: "New POST /lookup/censo route + _lookup_censo_async()/_run_censo() functions, sharing the existing sessions dict/lock/_set() helper and /result/<session_id> route. Existing /lookup and /lookup/infovotantes routes/functions untouched."
    - path: "app/Services/ConsultaCensoService.php"
      provides: "New LiveSourceAdapter implementation: startLookup() posts to /lookup/censo, getResult() passes through already-structured fields (no HTML parsing), isReachable() probes consultacenso.registraduria.gov.co independently."
    - path: "app/Providers/AppServiceProvider.php"
      provides: "liveAdapters bound as [InfovotantesService, RegistraduriaService, ConsultaCensoService] -- consultacenso as third-priority fallback."
    - path: "config/services.php"
      provides: "New 'consulta_censo' config block (url, live_enabled, probe_url) independent from the existing 'registraduria'/'infovotantes' blocks."
    - path: ".env.example"
      provides: "New CONSULTA_CENSO_SERVICE_URL / CONSULTA_CENSO_LIVE_ENABLED / CONSULTA_CENSO_PROBE_URL trio, consistent (no probe/env desync introduced)."
    - path: "tests/Feature/Services/ConsultaCensoServiceTest.php"
      provides: "Coverage for ConsultaCensoService's isReachable()/startLookup()/getResult(), mirroring InfovotantesServiceTest's structure."
    - path: "tests/Feature/Services/PollingPlaceResolverPriorityTest.php"
      provides: "Updated to prove the 3-adapter cascade: consultacenso is used only when both infovotantes AND wsp are unreachable, plus an updated 3-element binding-order regression test."
  key_links:
    - from: "app/Providers/AppServiceProvider.php liveAdapters array"
      to: "app/Services/ConsultaCensoService.php (third element)"
      via: "[$app->make(InfovotantesService::class), $app->make(RegistraduriaService::class), $app->make(ConsultaCensoService::class)]"
    - from: "app/Services/ConsultaCensoService.php startLookup()"
      to: "registraduria-service/app.py POST /lookup/censo"
      via: "Http::post baseUrl + /lookup/censo with {cedula}"
    - from: "app/Services/ConsultaCensoService.php isReachable()"
      to: "config('services.consulta_censo.probe_url')"
      via: "Http::connectTimeout(2)->timeout(3)->withoutRedirecting()->get(...)"
    - from: "registraduria-service/app.py _lookup_censo_async()"
      to: "https://consultacenso.registraduria.gov.co/back/api/consulta (real JSON API)"
      via: "page.request.fetch() POST with {documento, eleccionId, captchaToken} after 2captcha solves the standard v2 checkbox for sitekey 6LeLRw0tAAAAAOfUZZ34vi2KKHjukhLhQ5lfzuLM"
---

<objective>
Add https://consultacenso.registraduria.gov.co/ as a third, coexisting live source for polling-place lookups, implementing a new `ConsultaCensoService` LiveSourceAdapter following the exact pattern established by `RegistraduriaService` (wsp) and `InfovotantesService`. Registered as the third/fallback priority in `PollingPlaceResolver`'s `liveAdapters` array, tried only when both existing sources are unreachable or fail.

Purpose: consultacenso.registraduria.gov.co has already been confirmed (via live DevTools investigation) to expose a real structured JSON API behind a standard reCAPTCHA v2 checkbox, no HTML parsing needed, closer in shape to the infovotantes flow than the wsp flow. Adding it as a third fallback increases the odds the automated/interactive cascade resolves a cedula live before falling back to the national census snapshot.

Output:
- `registraduria-service/app.py` gains a third route (`POST /lookup/censo`), sharing the existing sessions dict and `/result/<session_id>` route. The existing `/lookup` (wsp) and `/lookup/infovotantes` routes/functions are byte-for-byte untouched.
- `app/Services/ConsultaCensoService.php`, new `LiveSourceAdapter` implementation.
- `app/Providers/AppServiceProvider.php`, `liveAdapters: [InfovotantesService, RegistraduriaService, ConsultaCensoService]`.
- New `services.consulta_censo` config block + `.env.example` entries.
- Pest coverage for the new adapter and for the 3-adapter cascade/fallback ordering.
- A final, explicit MANUAL verification step against the real site with 2 real cedulas and real 2captcha balance, before considering this task complete.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

<interfaces>
Key contracts extracted from the codebase. Use directly, no exploration needed.

From app/Services/LiveSourceAdapter.php (the contract ConsultaCensoService must implement, unchanged):

    interface LiveSourceAdapter
    {
        /** @throws \Exception if the service is unreachable or returns an error */
        public function startLookup(string $cedula): string;

        /** @return array{status: string, data: array<string,string>|null, error: string|null} */
        public function getResult(string $sessionId): array;

        /** Cheap reachability check, no captcha cost. */
        public function isReachable(): bool;
    }

From app/Services/InfovotantesService.php (closest pattern to mirror, pass-through JSON, no HTML parsing, DO NOT MODIFY THIS FILE):

    class InfovotantesService implements LiveSourceAdapter
    {
        protected string $baseUrl;

        public function __construct()
        {
            $this->baseUrl = config('services.infovotantes.url', 'http://localhost:5757');
        }

        public function startLookup(string $cedula): string
        {
            $response = Http::timeout(10)->post("{$this->baseUrl}/lookup/infovotantes", ['cedula' => $cedula]);
            // ... error handling (Log::error + throw \Exception on failure), returns session_id
        }

        public function getResult(string $sessionId): array
        {
            $response = Http::timeout(5)->get("{$this->baseUrl}/result/{$sessionId}");
            // 404 -> ['status' => 'error', 'data' => null, 'error' => 'Sesion no encontrada'];
            // non-successful -> ['status' => 'error', 'data' => null, 'error' => 'Error comunicandose con el servicio'];
            // otherwise return $response->json() directly, no HTML parsing
        }

        public function isReachable(): bool
        {
            if (! config('services.infovotantes.live_enabled')) { return false; }
            try {
                $response = Http::connectTimeout(2)->timeout(3)->withoutRedirecting()->get(config('services.infovotantes.probe_url'));
                return $response->successful() || $response->redirect();
            } catch (ConnectionException) { return false; }
        }
    }

From app/Providers/AppServiceProvider.php (current binding, to be changed, add ConsultaCensoService as THIRD element):

    $this->app->bind(PollingPlaceResolver::class, fn ($app) => new PollingPlaceResolver(
        liveAdapters: [
            $app->make(InfovotantesService::class),
            $app->make(RegistraduriaService::class),
        ],
    ));

From config/services.php (current infovotantes block, DO NOT MODIFY, add a sibling consulta_censo block directly after it):

    'infovotantes' => [
        'url' => env('INFOVOTANTES_SERVICE_URL', env('REGISTRADURIA_SERVICE_URL', 'http://localhost:5757')),
        'live_enabled' => env('INFOVOTANTES_LIVE_ENABLED', true),
        'probe_url' => env('INFOVOTANTES_PROBE_URL', 'https://eleccionescolombia.registraduria.gov.co/identificacion'),
    ],

From app/Services/PollingPlaceResolver.php (NOT modified by this plan, confirms the cascade already iterates $liveAdapters generically in isLiveReachable(), startLiveLookup(), attemptLiveAutomated()/resolveAutomated(). attemptLiveAutomated() gates on $adapter->isReachable() before startLookup() and moves to the NEXT adapter on any give-up: unreachable, startLookup throws, status waiting_captcha/error, or timeout after 5 polls with backoff [200,400,800,1200,1600]ms. That give-up path is the fallback mechanism this plan's new tests exercise for the 3rd adapter. Adding ConsultaCensoService as the array's third element is the entire priority mechanism; no resolver code changes needed).

From registraduria-service/app.py (current file, shared Flask process/sessions dict/lock/_set() helper, add new constants/functions/route as pure ADDITIONS, do not touch WSP_PAGE_URL, INFOVOTANTES_*, _lookup_async, _lookup_infovotantes_async, _run, _run_infovotantes, or either existing route):

    TWO_CAPTCHA_KEY = os.environ["TWO_CAPTCHA_KEY"]  # already loaded via load_env(), reuse as-is
    sessions: dict = {}
    sessions_lock = threading.Lock()

    def _set(session_id: str, **kwargs) -> None:
        with sessions_lock:
            sessions[session_id].update(kwargs)

Real-site findings from today's live DevTools investigation (architecture context, do not re-research):
- Page: `https://consultacenso.registraduria.gov.co/`, form `#consultaForm`, input `#documento`, select `#eleccion` (populated from `/back/api/elecciones`, currently one option: `id=0, nombre="LUGAR DE VOTACION ACTUAL"`), standard reCAPTCHA v2 checkbox (NOT enterprise flow client-side, despite the Enterprise-quota message shown in the iframe, same client flow as wsp).
- Sitekey observed: `6LeLRw0tAAAAAOfUZZ34vi2KKHjukhLhQ5lfzuLM` (use as a fallback constant only, prefer extracting live from the DOM via the `.g-recaptcha[data-sitekey]` element, same selector pattern wsp uses, since staleness across page loads was not 100% confirmed).
- `GET https://consultacenso.registraduria.gov.co/back/api/elecciones` -> `{ok:true, data:[{id, nombre, mensaje}]}`.
- `POST https://consultacenso.registraduria.gov.co/back/api/consulta` with JSON body `{documento, eleccionId, captchaToken}` (captchaToken = solved reCAPTCHA token) -> `{ok: bool, error?: string, encontrado: bool, data?: {nuip, nom_depto, nom_mun, nom_puesto, direccion, mesa} (case may vary, upper or lower), mapa?: {...}, mensaje?: string}`.
- Both API calls are same-origin with the page (unlike infovotantes's cross-domain call), no special CORS headers/Origin spoofing needed, just call them from the same Playwright browser context that loaded the page (carries the F5 BIGip cookie).
- Site sits behind F5 BIG-IP but showed no active WAF blocking of headless traffic during investigation, unlike wsp.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add consultacenso flow as a third Flask route (registraduria-service/app.py)</name>
  <files>registraduria-service/app.py</files>
  <action>
Add a new, fully independent flow to registraduria-service/app.py as pure ADDITIONS, do not modify a single existing line belonging to the wsp or infovotantes flows (confirm with git diff before finishing: only additions should appear).

1. Add new module-level constants directly below the existing INFOVOTANTES_API constant:

    CENSO_PAGE_URL = "https://consultacenso.registraduria.gov.co/"
    CENSO_SITEKEY_FALLBACK = "6LeLRw0tAAAAAOfUZZ34vi2KKHjukhLhQ5lfzuLM"
    CENSO_ELECCIONES_API = "https://consultacenso.registraduria.gov.co/back/api/elecciones"
    CENSO_CONSULTA_API = "https://consultacenso.registraduria.gov.co/back/api/consulta"

2. Add a small case-insensitive dict-lookup helper (the consulta API's data fields can come back upper or lower case):

    def _ci_get(d: dict, key: str) -> str:
        return str(d.get(key) or d.get(key.lower()) or d.get(key.upper()) or "")

3. Add `async def _lookup_censo_async(session_id: str, cedula: str) -> None:` modeled on `_lookup_infovotantes_async` (headless chromium, same desktop UA, ignore_https_errors=True):
   - `page.goto(CENSO_PAGE_URL, timeout=30_000)`.
   - Extract sitekey: `sitekey = await page.get_attribute(".g-recaptcha", "data-sitekey") or CENSO_SITEKEY_FALLBACK`.
   - `_set(session_id, status="solving_captcha", sitekey=sitekey)`.
   - Solve via 2captcha exactly like the wsp flow's aiohttp submit/poll loop (method=userrecaptcha, googlekey=sitekey, pageurl=CENSO_PAGE_URL, invisible=0, json=1). NO enterprise param (confirmed standard v2 checkbox, not the enterprise escalation path). Same 30x/5s poll loop, same source_unreachable error shape on submit/poll failure or no-token-after-150s.
   - `_set(session_id, status="waiting_result")`.
   - Fetch elections list via the SAME page's context: `elecciones_resp = await page.request.fetch(CENSO_ELECCIONES_API, method="GET", timeout=15_000)`; parse `data[0]["id"]` as eleccion_id, defaulting to 0 (today's only real option) inside a try/except that swallows failures and falls through to the default.
   - POST the real consulta: `api_response = await page.request.fetch(CENSO_CONSULTA_API, method="POST", headers={"Content-Type": "application/json"}, data=_json.dumps({"documento": cedula, "eleccionId": eleccion_id, "captchaToken": token}), timeout=20_000)`; `result = await api_response.json()`. Wrap in try/except, on exception `_set(session_id, status="error", outcome="source_unreachable", error=f"no response from /back/api/consulta: {exc}")`, return.
   - Classify result into outcomes (never treat "token obtained" as success, only classify after this real response):
     - `result.get("ok") and result.get("encontrado")` -> outcome "success", data mapped to the 7-field structured shape: puesto_nombre=_ci_get(data,"nom_puesto"), puesto_codigo="", zona_codigo="", mesa_numero=_ci_get(data,"mesa"), departamento=_ci_get(data,"nom_depto"), municipio=_ci_get(data,"nom_mun"), direccion=_ci_get(data,"direccion").
     - `result.get("ok") and not result.get("encontrado")` -> outcome "not_found", message=result.get("mensaje", "").
     - otherwise (ok falsy), inspect result.get("error", "") lowercased: if it contains "captcha", "token", or "expir" -> outcome "session_expired"; else -> outcome "denied_by_score". Always `_set(session_id, status="done", outcome=outcome, raw_response=result, error=error_msg)`.
   - Wrap the whole page-interaction section in try/finally: await browser.close(), matching the existing flows' structure.

4. Add `def _run_censo(session_id: str, cedula: str) -> None:` mirroring _run(): wraps asyncio.run(_lookup_censo_async(session_id, cedula)) in try/except, `_set(session_id, status="error", outcome="source_unreachable", error=str(exc).split("\n")[0])` on exception.

5. Add the new Flask route:

    @app.route("/lookup/censo", methods=["POST"])
    def lookup_censo():
        body = request.get_json(silent=True) or {}
        cedula = str(body.get("cedula", "")).strip()
        if not cedula:
            return jsonify({"error": "El campo 'cedula' es requerido."}), 400

        session_id = str(uuid.uuid4())
        with sessions_lock:
            sessions[session_id] = {
                "status": "pending", "outcome": None, "data": None,
                "error": None, "sitekey": None, "message": None, "raw_response": None,
            }

        threading.Thread(target=_run_censo, args=(session_id, cedula), daemon=True).start()
        return jsonify({"session_id": session_id}), 200

6. Do NOT add a second /result/<session_id> route, the existing shared one already serves any session_id regardless of which flow populated it.

7. Update the module docstring's coexistence sentence to mention all three flows now share the process (wsp at /lookup, infovotantes at /lookup/infovotantes, consultacenso at /lookup/censo), without altering the existing Phase 9 documentation block.

No new pip dependencies required, aiohttp, flask, playwright are already imported and used.
  </action>
  <verify>
    <automated>python3 -c "import ast; ast.parse(open('registraduria-service/app.py').read()); print('syntax OK')" && grep -c "WSP_PAGE_URL" registraduria-service/app.py && grep -c "INFOVOTANTES_API" registraduria-service/app.py && grep -c "/lookup/censo" registraduria-service/app.py && git diff --stat registraduria-service/app.py</automated>
  </verify>
  <done>
    registraduria-service/app.py has a working /lookup/censo route producing sessions with the same 7-field structured data shape the other adapters use. python3 ast.parse confirms syntactic validity. git diff shows only additions, no line belonging to the wsp or infovotantes flows is modified or removed.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: ConsultaCensoService adapter + config + priority-ordered registration + adapter tests</name>
  <files>
    app/Services/ConsultaCensoService.php
    config/services.php
    .env.example
    app/Providers/AppServiceProvider.php
    tests/Feature/Services/ConsultaCensoServiceTest.php
  </files>
  <behavior>
    - Test 1: live_enabled false -> isReachable() returns false with zero HTTP calls (Http::assertNothingSent()).
    - Test 2: probe responds 200 -> isReachable() returns true.
    - Test 3: probe fails to connect -> isReachable() returns false.
    - Test 4: startLookup() posts to /lookup/censo and returns the session_id from the response.
    - Test 5: startLookup() propagates ConnectionException when the lookup endpoint is unreachable.
    - Test 6: getResult() passes through an already-structured "done" payload verbatim, no HTML parsing performed.
    - Test 7: getResult() returns status "error" when the session is not found (404).
  </behavior>
  <action>
Create app/Services/ConsultaCensoService.php implementing LiveSourceAdapter, mirroring InfovotantesService's structure exactly (pass-through JSON, no HTML parsing) except for the base endpoint and config namespace:

    <?php

    declare(strict_types=1);

    namespace App\Services;

    use Illuminate\Http\Client\ConnectionException;
    use Illuminate\Support\Facades\Http;
    use Illuminate\Support\Facades\Log;

    class ConsultaCensoService implements LiveSourceAdapter
    {
        protected string $baseUrl;

        public function __construct()
        {
            $this->baseUrl = config('services.consulta_censo.url', 'http://localhost:5757');
        }

        public function startLookup(string $cedula): string
        {
            $response = Http::timeout(10)->post("{$this->baseUrl}/lookup/censo", ['cedula' => $cedula]);

            if (! $response->successful()) {
                Log::error('ConsultaCensoService: lookup failed', [
                    'cedula' => $cedula,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('El servicio de consultacenso no esta disponible. Inicia el servicio Python primero.');
            }

            $sessionId = $response->json('session_id');

            if (! $sessionId) {
                throw new \Exception('El servicio de consultacenso no devolvio un session_id valido.');
            }

            return $sessionId;
        }

        /**
         * @return array{status: string, data: array<string, string>|null, error: string|null}
         */
        public function getResult(string $sessionId): array
        {
            $response = Http::timeout(5)->get("{$this->baseUrl}/result/{$sessionId}");

            if ($response->status() === 404) {
                return ['status' => 'error', 'data' => null, 'error' => 'Sesion no encontrada'];
            }

            if (! $response->successful()) {
                return ['status' => 'error', 'data' => null, 'error' => 'Error comunicandose con el servicio'];
            }

            return $response->json();
        }

        public function isReachable(): bool
        {
            if (! config('services.consulta_censo.live_enabled')) {
                return false;
            }

            try {
                $response = Http::connectTimeout(2)->timeout(3)->withoutRedirecting()->get(config('services.consulta_censo.probe_url'));

                return $response->successful() || $response->redirect();
            } catch (ConnectionException) {
                return false;
            }
        }
    }

Use declare(strict_types=1) and explicit use statements per CLAUDE.md's import rule, no aliases, no inline namespace paths.

In config/services.php, add a sibling 'consulta_censo' block directly after the existing 'infovotantes' block (do not modify 'registraduria'/'infovotantes'):

    'consulta_censo' => [
        'url' => env('CONSULTA_CENSO_SERVICE_URL', 'http://localhost:5757'),
        'live_enabled' => env('CONSULTA_CENSO_LIVE_ENABLED', true),
        'probe_url' => env('CONSULTA_CENSO_PROBE_URL', 'https://consultacenso.registraduria.gov.co/'),
    ],

In .env.example, add three lines directly after the existing INFOVOTANTES_PROBE_URL line:

    CONSULTA_CENSO_SERVICE_URL=http://localhost:5757
    CONSULTA_CENSO_LIVE_ENABLED=true
    CONSULTA_CENSO_PROBE_URL=https://consultacenso.registraduria.gov.co/

In app/Providers/AppServiceProvider.php, add "use App\Services\ConsultaCensoService;" alphabetically ordered with the other use App\Services\* imports (C before I before R). Change the liveAdapters binding to register consultacenso THIRD:

    $this->app->bind(PollingPlaceResolver::class, fn ($app) => new PollingPlaceResolver(
        liveAdapters: [
            $app->make(InfovotantesService::class),
            $app->make(RegistraduriaService::class),
            $app->make(ConsultaCensoService::class),
        ],
    ));

Do not touch anything else in this file (boot(), User::observe(), PAGE_SCOPED_WIDGETS all stay exactly as-is).

Create tests/Feature/Services/ConsultaCensoServiceTest.php, mirroring tests/Feature/Services/InfovotantesServiceTest.php exactly (same 7 cases, swap infovotantes -> consulta_censo config keys and /lookup/censo endpoint):

    <?php

    use App\Services\ConsultaCensoService;
    use Illuminate\Http\Client\ConnectionException;
    use Illuminate\Support\Facades\Http;

    it('returns false with no HTTP call when the live kill switch is off', function () {
        config(['services.consulta_censo.live_enabled' => false]);
        Http::fake();
        $service = new ConsultaCensoService;
        expect($service->isReachable())->toBeFalse();
        Http::assertNothingSent();
    });

    it('returns true when the probe responds successfully', function () {
        config(['services.consulta_censo.live_enabled' => true]);
        Http::fake([config('services.consulta_censo.probe_url').'*' => Http::response('', 200)]);
        $service = new ConsultaCensoService;
        expect($service->isReachable())->toBeTrue();
    });

    it('returns false when the probe is unreachable', function () {
        config(['services.consulta_censo.live_enabled' => true]);
        Http::fake([config('services.consulta_censo.probe_url').'*' => Http::failedConnection()]);
        $service = new ConsultaCensoService;
        expect($service->isReachable())->toBeFalse();
    });

    it('starts a lookup against the /lookup/censo endpoint and returns the session_id', function () {
        Http::fake(['*/lookup/censo' => Http::response(['session_id' => 'censo-session-abc'], 200)]);
        $service = new ConsultaCensoService;
        expect($service->startLookup('1102812122'))->toBe('censo-session-abc');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/lookup/censo'));
    });

    it('throws when the lookup endpoint is unreachable', function () {
        Http::fake(['*/lookup/censo' => fn () => throw new ConnectionException('Connection refused')]);
        $service = new ConsultaCensoService;
        expect(fn () => $service->startLookup('1102812122'))->toThrow(ConnectionException::class);
    });

    it('passes through already-structured fields from getResult without any HTML parsing', function () {
        Http::fake(['*/result/*' => Http::response([
            'status' => 'done',
            'data' => [
                'puesto_nombre' => 'IE LA CAMPINA', 'puesto_codigo' => '', 'zona_codigo' => '',
                'mesa_numero' => '05', 'departamento' => 'SUCRE', 'municipio' => 'SINCELEJO',
                'direccion' => 'CALLE FALSA 123',
            ],
            'error' => null,
        ])]);
        $service = new ConsultaCensoService;
        $result = $service->getResult('censo-session-abc');
        expect($result['status'])->toBe('done')
            ->and($result['data']['puesto_nombre'])->toBe('IE LA CAMPINA');
    });

    it('returns an error status when the session is not found', function () {
        Http::fake(['*/result/*' => Http::response(['error' => 'not found'], 404)]);
        $service = new ConsultaCensoService;
        $result = $service->getResult('missing-session');
        expect($result['status'])->toBe('error');
    });
  </action>
  <verify>
    <automated>php -l app/Services/ConsultaCensoService.php && php -l app/Providers/AppServiceProvider.php && php artisan config:clear && php artisan test --filter=ConsultaCensoServiceTest</automated>
  </verify>
  <done>
    app/Services/ConsultaCensoService.php exists, implements LiveSourceAdapter, php -l passes on both modified PHP files. config('services.consulta_censo.*') resolves url/live_enabled/probe_url. AppServiceProvider's liveAdapters array is [InfovotantesService, RegistraduriaService, ConsultaCensoService] in that order. tests/Feature/Services/ConsultaCensoServiceTest.php passes (7/7).
  </done>
</task>

<task type="auto">
  <name>Task 3: 3-adapter cascade/fallback coverage + Pint</name>
  <files>tests/Feature/Services/PollingPlaceResolverPriorityTest.php</files>
  <action>
Update tests/Feature/Services/PollingPlaceResolverPriorityTest.php:

1. Add "use App\Services\ConsultaCensoService;" to the existing use-statement block (alphabetically, before InfovotantesService).

2. Update the existing reflection test ("AppServiceProvider registers InfovotantesService ahead of RegistraduriaService in liveAdapters") to also assert the third element:

    expect($adapters[0])->toBeInstanceOf(InfovotantesService::class)
        ->and($adapters[1])->toBeInstanceOf(RegistraduriaService::class)
        ->and($adapters[2])->toBeInstanceOf(ConsultaCensoService::class);

   Update the test's own name/description to mention all three adapters if it currently references only two.

3. Add a new test: "resolveAutomated falls back to consultacenso when both infovotantes and wsp are unreachable, without calling their lookup endpoints":

    config([
        'services.infovotantes.live_enabled' => true,
        'services.registraduria.live_enabled' => true,
        'services.consulta_censo.live_enabled' => true,
    ]);

    Http::fake([
        config('services.infovotantes.probe_url').'*' => Http::failedConnection(),
        config('services.registraduria.probe_url').'*' => Http::failedConnection(),
        config('services.consulta_censo.probe_url').'*' => Http::response('', 200),
        '*/lookup/censo' => Http::response(['session_id' => 'censo-session'], 200),
        '*/result/censo-session' => Http::response([
            'status' => 'done',
            'data' => [
                'puesto_nombre' => 'IE LA CAMPINA', 'puesto_codigo' => '', 'zona_codigo' => '',
                'mesa_numero' => '05', 'departamento' => 'SUCRE', 'municipio' => 'SINCELEJO',
                'direccion' => 'CALLE FALSA 123',
            ],
            'error' => null,
        ]),
    ]);

    $voter = Voter::factory()->create(['polling_place_source' => null]);
    $resolver = app(PollingPlaceResolver::class);

    $result = $resolver->resolveAutomated('1102812122', $voter);

    expect($result)->not->toBeNull()->and($result->source)->toBe(PollingPlaceSource::LIVE);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/lookup/infovotantes'));
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/lookup') && ! str_contains($request->url(), '/lookup/infovotantes') && ! str_contains($request->url(), '/lookup/censo'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/lookup/censo'));

4. Add a new test: "startLiveLookup skips two unreachable adapters (infovotantes, wsp) and uses consultacenso":

    config([
        'services.infovotantes.live_enabled' => true,
        'services.registraduria.live_enabled' => true,
        'services.consulta_censo.live_enabled' => true,
    ]);

    Http::fake([
        config('services.infovotantes.probe_url').'*' => Http::failedConnection(),
        config('services.registraduria.probe_url').'*' => Http::failedConnection(),
        config('services.consulta_censo.probe_url').'*' => Http::response('', 200),
        '*/lookup/censo' => Http::response(['session_id' => 'censo-session'], 200),
    ]);

    $resolver = app(PollingPlaceResolver::class);
    $sessionId = $resolver->startLiveLookup('1102812122');

    expect($sessionId)->toBe('censo-session');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/lookup/infovotantes'));

After writing, run the full targeted suite plus Pint per CLAUDE.md's mandatory pre-finalize check.
  </action>
  <verify>
    <automated>php artisan test --filter=PollingPlaceResolverPriorityTest && php artisan test --filter=InfovotantesServiceTest && php artisan test --filter=RegistraduriaServiceReachabilityTest && php artisan test --filter=ConsultaCensoServiceTest && vendor/bin/pint --dirty --test</automated>
  </verify>
  <done>
    tests/Feature/Services/PollingPlaceResolverPriorityTest.php passes with 6 tests (4 original + 2 new), including the updated 3-element reflection assertion. All pre-existing wsp/infovotantes/resolver test files still pass unmodified, proving zero regression. Pint reports no changes needed.
  </done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <what-built>ConsultaCensoService (third LiveSourceAdapter, https://consultacenso.registraduria.gov.co/) fully wired into the Python microservice (POST /lookup/censo) and PollingPlaceResolver's liveAdapters cascade (third/fallback priority), with Pest coverage proving the fallback ordering with faked HTTP responses. This step is the one thing Pest CANNOT verify: a real end-to-end lookup against the live site, solving a real reCAPTCHA via real 2captcha balance.</what-built>
  <how-to-verify>
    1. Ensure registraduria-service/.env has a valid TWO_CAPTCHA_KEY with real balance loaded.
    2. Start (or restart) the Python microservice: `cd registraduria-service && python3 app.py` (or however it's normally run/deployed locally), confirm it listens on port 5757.
    3. Confirm Laravel's .env has CONSULTA_CENSO_LIVE_ENABLED=true and CONSULTA_CENSO_SERVICE_URL pointing at the running microservice.
    4. Using tinker or a direct HTTP call, trigger a real lookup against ConsultaCensoService with ONE of your 2 real test cedulas: `php artisan tinker --execute="dd((new App\Services\ConsultaCensoService)->startLookup('REAL_CEDULA_HERE'));"` to get a session_id, then poll `(new App\Services\ConsultaCensoService)->getResult('SESSION_ID')` every few seconds until status is no longer "pending"/"solving_captcha"/"waiting_result".
    5. Confirm the final result: status "done", outcome "success", and data containing a real, non-empty puesto_nombre/departamento/municipio/direccion/mesa_numero for that real cedula. If the first cedula returns not_found/denied_by_score/session_expired, try the second real cedula before concluding there is a real bug.
    6. If it fails, report the exact status/outcome/error/raw_response returned so the flow can be diagnosed and fixed before this task is considered done — do NOT mark this done on a failed real attempt.
  </how-to-verify>
  <resume-signal>Type "approved" once a real cedula resolves successfully end-to-end against the live site, or describe the exact failure (status/outcome/error) if it does not.</resume-signal>
</task>

</tasks>

<verification>
Run the full targeted suite one more time to confirm nothing else broke:
php artisan test --filter=Registraduria
php artisan test --filter=Infovotantes
php artisan test --filter=ConsultaCenso
php artisan test --filter=PollingPlaceResolver
git diff registraduria-service/app.py -- confirm zero modifications to existing wsp/infovotantes lines, only additions.
vendor/bin/pint --dirty --test -- confirm no PSR-12 issues on new/modified PHP files.
</verification>

<success_criteria>
- ConsultaCensoService exists, implements LiveSourceAdapter, and is registered as the THIRD (fallback) element in PollingPlaceResolver's liveAdapters binding.
- registraduria-service/app.py serves all three flows (wsp at /lookup, infovotantes at /lookup/infovotantes, consultacenso at /lookup/censo) from the same Flask process, sharing /result/session_id, with zero modification to existing wsp/infovotantes code.
- Automated tests prove: consultacenso is tried only when both infovotantes AND wsp are unreachable/fail, and all pre-existing wsp/infovotantes/resolver tests still pass unmodified.
- A human has confirmed a REAL end-to-end lookup (real cedula, real 2captcha balance, real consultacenso.registraduria.gov.co site) succeeds before this task is marked complete.
- No dependency changes, no resolver code changes -- purely additive per the established three-adapter coexistence pattern.
</success_criteria>

<output>
After completion, create `.planning/quick/260731-ezk-agregar-consultacenso-registraduria-gov-/260731-ezk-SUMMARY.md`
</output>
