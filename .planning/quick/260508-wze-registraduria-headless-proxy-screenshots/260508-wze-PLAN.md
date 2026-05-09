---
phase: quick-260508-wze
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - registraduria-service/app.py
  - registraduria-service/requirements.txt
  - app/Http/Controllers/RegistraduriaController.php
  - routes/web.php
  - app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php
  - app/Filament/Resources/Voters/Schemas/VoterForm.php
  - resources/views/filament/registraduria-browser.blade.php
autonomous: true
requirements: [WZE-01]

must_haves:
  truths:
    - "Operator types cedula and clicks the search button — modal appears inside SIGMA without leaving the page"
    - "Live screenshot of the Registraduria page updates every 400ms inside the modal"
    - "Clicking on the screenshot image forwards the click to the headless browser at the correct scaled coordinates"
    - "Once the operator solves the captcha and the result is detected, the modal closes and form fields fill automatically"
    - "Python service runs headless (no display required) — works on a VPS"
  artifacts:
    - path: "registraduria-service/app.py"
      provides: "Headless Playwright service with screenshot, click, viewport endpoints"
    - path: "app/Http/Controllers/RegistraduriaController.php"
      provides: "PHP proxy for all Registraduria service routes"
    - path: "resources/views/filament/registraduria-browser.blade.php"
      provides: "Alpine.js modal with screenshot polling and click forwarding"
    - path: "app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php"
      provides: "Livewire state management for modal open/close and field filling"
  key_links:
    - from: "resources/views/filament/registraduria-browser.blade.php"
      to: "/registraduria/screenshot/{id}"
      via: "img src with timestamp query param updated by setInterval"
      pattern: "setInterval.*screenshot"
    - from: "resources/views/filament/registraduria-browser.blade.php"
      to: "$wire.handleRegistraduriaResult"
      via: "fetch /registraduria/result/{id} polling on status=done"
      pattern: "handleRegistraduriaResult"
    - from: "app/Http/Controllers/RegistraduriaController.php"
      to: "http://localhost:5757"
      via: "Http::get/post proxy"
      pattern: "Http::.*baseUrl"
---

<objective>
Refactor the Registraduria integration from visible-Chrome (local-only) to headless Playwright
with a screenshot-proxy modal embedded in SIGMA. Operator stays inside SIGMA for the entire flow:
modal shows a live screenshot, clicks on the image are forwarded to the headless browser, form
fields auto-fill when the result is ready.

Purpose: Make the Registraduria consultation work on a VPS (no display) while keeping the operator
experience to 3 clicks total without leaving the voter form.

Output:
- Modified Python microservice (headless + screenshot/click/viewport endpoints)
- New RegistraduriaController (PHP proxy)
- New Blade view (Alpine.js interactive modal)
- Updated HasRegistraduriaPolling trait (modal state management)
- Updated VoterForm (Placeholder modal + updated suffixAction)
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/quick/260508-wze-registraduria-headless-proxy-screenshots/260508-wze-PLAN.md

<!-- Key interfaces already understood from reading source files -->
<interfaces>
<!-- RegistraduriaService.php (unchanged) -->
namespace App\Services;
class RegistraduriaService {
    public function startLookup(string $cedula): string  // returns session_id
    public function getResult(string $sessionId): array  // returns {status, data, error}
}

<!-- HasRegistraduriaPolling (current — to be replaced) -->
trait HasRegistraduriaPolling {
    public function pollRegistraduria(string $sessionId): ?array  // REMOVE this
}

<!-- VoterForm suffixAction currently dispatches Livewire event "registraduria-start-polling" -->
<!-- VoterForm Section "Información Personal" has x-data / x-init Alpine polling — REMOVE -->

<!-- Existing controller convention (see PublicPollingPlaceOptionsController) -->
namespace App\Http\Controllers;
class SomeController extends Controller {
    public function __invoke(Request $request) { ... }
}
<!-- New controller is NOT invokable — uses named methods -->
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Upgrade Python microservice to headless with screenshot/click/viewport endpoints</name>
  <files>registraduria-service/app.py, registraduria-service/requirements.txt</files>
  <action>
Rewrite registraduria-service/app.py with these changes:

1. ADD module-level dict alongside `sessions`:
   ```python
   session_contexts: dict = {}
   session_contexts_lock = threading.Lock()
   ```

2. REPLACE the `run_lookup` function body:
   - Launch with `headless=True` and stealth args:
     ```python
     browser = p.chromium.launch(
         headless=True,
         args=['--disable-blink-features=AutomationControlled', '--no-sandbox']
     )
     context = browser.new_context(
         viewport={"width": 1280, "height": 800},
         user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
     )
     page = context.new_page()
     ```
   - After creating the page, store it in session_contexts BEFORE navigating:
     ```python
     with session_contexts_lock:
         session_contexts[session_id] = {"page": page, "browser": browser}
     ```
   - Navigate to URL, fill cedula (keep existing try/except selector logic)
   - Set status to "waiting_captcha"
   - MONITOR loop (same 120s deadline, same body_text check) — do NOT click any button
   - On success: set status=done and data; on timeout/error: set status=error
   - In `finally`: close browser, then remove from session_contexts:
     ```python
     finally:
         if browser:
             try:
                 browser.close()
             except Exception:
                 pass
         with session_contexts_lock:
             session_contexts.pop(session_id, None)
     ```
   - Keep `_parse_result_text` function unchanged.

3. ADD three new Flask routes:

   ```python
   @app.route("/screenshot/<session_id>", methods=["GET"])
   def screenshot(session_id: str) -> tuple:
       with session_contexts_lock:
           ctx = session_contexts.get(session_id)
       if ctx is None:
           return jsonify({"error": "Sesión no encontrada o ya cerrada."}), 404
       try:
           png_bytes = ctx["page"].screenshot()
           response = app.response_class(png_bytes, mimetype="image/png")
           response.headers["Cache-Control"] = "no-store, no-cache, must-revalidate"
           response.headers["Pragma"] = "no-cache"
           return response
       except Exception as exc:
           return jsonify({"error": str(exc)}), 500


   @app.route("/click/<session_id>", methods=["POST"])
   def click(session_id: str) -> tuple:
       with session_contexts_lock:
           ctx = session_contexts.get(session_id)
       if ctx is None:
           return jsonify({"error": "Sesión no encontrada o ya cerrada."}), 404
       body = request.get_json(silent=True) or {}
       x = body.get("x")
       y = body.get("y")
       if x is None or y is None:
           return jsonify({"error": "Se requieren x e y."}), 400
       try:
           ctx["page"].mouse.click(float(x), float(y))
           return jsonify({"ok": True}), 200
       except Exception as exc:
           return jsonify({"error": str(exc)}), 500


   @app.route("/viewport/<session_id>", methods=["GET"])
   def viewport(session_id: str) -> tuple:
       with session_contexts_lock:
           ctx = session_contexts.get(session_id)
       if ctx is None:
           return jsonify({"error": "Sesión no encontrada o ya cerrada."}), 404
       try:
           size = ctx["page"].viewport_size
           return jsonify(size), 200
       except Exception as exc:
           return jsonify({"error": str(exc)}), 500
   ```

4. Keep `requirements.txt` as-is (flask + playwright — no new deps needed). Verify it contains ONLY:
   ```
   flask==3.1.1
   playwright==1.50.0
   ```
  </action>
  <verify>
    <automated>cd /Volumes/NAS(MAC)/Data/Herd/sigma-project/registraduria-service && python -c "import ast, sys; ast.parse(open('app.py').read()); print('syntax OK')"</automated>
  </verify>
  <done>
    - app.py passes Python syntax check
    - headless=True present in launch call
    - session_contexts dict exists at module level
    - /screenshot/, /click/, /viewport/ routes defined
    - finally block removes from session_contexts AFTER browser.close()
    - requirements.txt unchanged (flask + playwright only)
  </done>
</task>

<task type="auto">
  <name>Task 2: Create PHP proxy controller and register authenticated routes</name>
  <files>app/Http/Controllers/RegistraduriaController.php, routes/web.php</files>
  <action>
Create app/Http/Controllers/RegistraduriaController.php. Use explicit `use` statements per CLAUDE.md rules. The controller proxies all traffic from SIGMA to the Python service.

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegistraduriaController extends Controller
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.registraduria.url', 'http://localhost:5757');
    }

    public function lookup(Request $request): \Illuminate\Http\JsonResponse
    {
        $cedula = $request->input('cedula', '');

        if (blank($cedula)) {
            return response()->json(['error' => 'El campo cedula es requerido.'], 422);
        }

        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/lookup", ['cedula' => $cedula]);

            if (! $response->successful()) {
                Log::error('RegistraduriaController: lookup failed', ['status' => $response->status()]);
                return response()->json(['error' => 'El servicio de Registraduría no está disponible.'], 502);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            Log::error('RegistraduriaController: lookup exception', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'El servicio de Registraduría no está disponible. Inicia el servicio Python primero.'], 503);
        }
    }

    public function result(string $id): \Illuminate\Http\JsonResponse
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/result/{$id}");

            if ($response->status() === 404) {
                return response()->json(['error' => 'Sesión no encontrada.'], 404);
            }

            if (! $response->successful()) {
                return response()->json(['error' => 'Error comunicándose con el servicio.'], 502);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Servicio no disponible.'], 503);
        }
    }

    public function screenshot(string $id): Response
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/screenshot/{$id}");

            if (! $response->successful()) {
                abort(502, 'No se pudo obtener el screenshot.');
            }

            return response($response->body(), 200)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->header('Pragma', 'no-cache');
        } catch (\Exception $e) {
            abort(503, 'Servicio no disponible.');
        }
    }

    public function click(string $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $x = $request->input('x');
        $y = $request->input('y');

        if ($x === null || $y === null) {
            return response()->json(['error' => 'Se requieren x e y.'], 422);
        }

        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/click/{$id}", [
                'x' => (float) $x,
                'y' => (float) $y,
            ]);

            if (! $response->successful()) {
                return response()->json(['error' => 'Error al enviar clic.'], 502);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Servicio no disponible.'], 503);
        }
    }

    public function viewport(string $id): \Illuminate\Http\JsonResponse
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/viewport/{$id}");

            if (! $response->successful()) {
                return response()->json(['error' => 'Error al obtener viewport.'], 502);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Servicio no disponible.'], 503);
        }
    }
}
```

In routes/web.php:
1. Add import at the top: `use App\Http\Controllers\RegistraduriaController;`
2. Inside the existing `Route::middleware(['auth'])->group(function () {` block (after line 39),
   add the Registraduria route group:

```php
    Route::prefix('registraduria')->name('registraduria.')->group(function () {
        Route::post('lookup', [RegistraduriaController::class, 'lookup'])->name('lookup');
        Route::get('result/{id}', [RegistraduriaController::class, 'result'])->name('result');
        Route::get('screenshot/{id}', [RegistraduriaController::class, 'screenshot'])->name('screenshot');
        Route::post('click/{id}', [RegistraduriaController::class, 'click'])->name('click');
        Route::get('viewport/{id}', [RegistraduriaController::class, 'viewport'])->name('viewport');
    });
```

NOTE: All routes are inside `auth` middleware. CSRF is handled via `X-CSRF-TOKEN` header in the
Alpine.js fetch calls for the POST /click route — no CSRF exemption needed.
  </action>
  <verify>
    <automated>cd /Volumes/NAS(MAC)/Data/Herd/sigma-project && php artisan route:list --name=registraduria 2>&1</automated>
  </verify>
  <done>
    - `php artisan route:list --name=registraduria` shows 5 routes: lookup, result, screenshot, click, viewport
    - All routes have `auth` middleware
    - Controller file passes `php artisan` without errors
    - No namespace aliases in controller (per CLAUDE.md import rules)
  </done>
</task>

<task type="auto">
  <name>Task 3: Update HasRegistraduriaPolling trait and VoterForm for modal-based flow</name>
  <files>
    app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php,
    app/Filament/Resources/Voters/Schemas/VoterForm.php,
    resources/views/filament/registraduria-browser.blade.php
  </files>
  <action>
### A. Rewrite HasRegistraduriaPolling trait

Replace entire file content (keep namespace, keep all `use` imports, add new ones):

```php
<?php

namespace App\Filament\Resources\Voters\Concerns;

use App\Models\Department;
use App\Models\Municipality;
use App\Models\PollingPlace;
use App\Services\RegistraduriaService;
use Filament\Notifications\Notification;

trait HasRegistraduriaPolling
{
    public string $registraduriaSessionId = '';

    public bool $registraduriaOpen = false;

    /**
     * Called by the suffixAction on the document_number field.
     * Starts the Python lookup, opens the screenshot modal.
     */
    public function openRegistraduriaBrowser(string $cedula): void
    {
        if (blank($cedula)) {
            Notification::make()
                ->title('Número de documento requerido')
                ->body('Ingresa el número de cédula antes de consultar.')
                ->warning()
                ->send();

            return;
        }

        try {
            $service = new RegistraduriaService;
            $sessionId = $service->startLookup($cedula);

            $this->registraduriaSessionId = $sessionId;
            $this->registraduriaOpen = true;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al conectar con el servicio')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Called from Alpine.js via $wire.handleRegistraduriaResult(data)
     * when the screenshot modal detects status=done.
     *
     * @param  array{status: string, data: array<string, string>|null, error: string|null}  $result
     */
    public function handleRegistraduriaResult(array $result): void
    {
        $this->registraduriaOpen = false;
        $this->registraduriaSessionId = '';

        if ($result['status'] === 'done' && isset($result['data'])) {
            $data = $result['data'];

            $municipality = Municipality::query()
                ->whereRaw('LOWER(name) = ?', [strtolower($data['municipio'] ?? '')])
                ->first();

            $department = null;

            if ($municipality) {
                $department = $municipality->department;
            } else {
                $department = Department::query()
                    ->whereRaw('LOWER(name) = ?', [strtolower($data['departamento'] ?? '')])
                    ->first();
            }

            $placeCode = $data['puesto_codigo'] ?? substr($data['puesto_nombre'] ?? '', 0, 2);
            $pollingPlace = null;

            if ($municipality) {
                $pollingPlace = PollingPlace::query()
                    ->where('municipality_id', $municipality->id)
                    ->where('zone_code', $data['zona_codigo'] ?? null)
                    ->where('place_code', $placeCode)
                    ->first();

                if (! $pollingPlace) {
                    $pollingPlace = PollingPlace::create([
                        'municipality_id' => $municipality->id,
                        'department_id' => $department?->id,
                        'zone_code' => $data['zona_codigo'] ?? null,
                        'place_code' => $placeCode,
                        'name' => $data['puesto_nombre'] ?? 'Desconocido',
                        'address' => $data['direccion'] ?? null,
                    ]);
                }
            }

            if ($municipality) {
                $this->data['municipality_id'] = $municipality->id;
            }

            if ($pollingPlace) {
                $this->data['polling_place_id'] = $pollingPlace->id;
            }

            $this->data['polling_table_number'] = ltrim($data['mesa_numero'] ?? '', '0') ?: null;

            Notification::make()
                ->title('Puesto de votación encontrado')
                ->body("Puesto: {$data['puesto_nombre']} — Mesa: {$data['mesa_numero']}")
                ->success()
                ->send();
        }

        if ($result['status'] === 'error') {
            Notification::make()
                ->title('Error al consultar Registraduría')
                ->body($result['error'] ?? 'Error desconocido')
                ->danger()
                ->send();
        }
    }

    /**
     * Called from Alpine.js close button via $wire.closeRegistraduriaBrowser().
     */
    public function closeRegistraduriaBrowser(): void
    {
        $this->registraduriaOpen = false;
        $this->registraduriaSessionId = '';
    }
}
```

### B. Update VoterForm.php

Two changes only — do NOT alter anything else:

1. Add these imports at the top (alphabetical order with existing `use` statements):
   ```php
   use Filament\Schemas\Components\Placeholder;
   use Illuminate\Support\HtmlString;
   ```

2. In the `configure()` method, insert a `Placeholder` as the FIRST component in the
   `->components([...])` array, before `Hidden::make('campaign_scope_state')`:
   ```php
   Placeholder::make('_registraduria_modal')
       ->hiddenLabel()
       ->dehydrated(false)
       ->columnSpanFull()
       ->content(fn ($livewire): HtmlString => $livewire->registraduriaOpen && $livewire->registraduriaSessionId
           ? new HtmlString(view('filament.registraduria-browser', ['sessionId' => $livewire->registraduriaSessionId])->render())
           : new HtmlString('')
       ),
   ```

3. In the `suffixAction` callback on `document_number`, replace the entire action closure:
   - Remove the old try/catch that called `$service->startLookup()` + dispatched Livewire event
   - Replace with a single call: `$livewire->openRegistraduriaBrowser($state);`
   The validation check for blank($state) is now handled inside the trait method, but keep the
   method call unconditional (the trait handles it gracefully).

   The new action closure:
   ```php
   ->action(function ($state, $livewire): void {
       $livewire->openRegistraduriaBrowser($state);
   })
   ```

4. Remove the `extraAttributes()` call from the "Información Personal" Section entirely —
   delete the entire `->extraAttributes([...])` chain that contains the Alpine x-data/x-init
   polling logic. The Section should end at `->columns(2)` with no extraAttributes.

5. Remove unused imports from VoterForm.php (after the changes above these are no longer needed):
   - `use App\Services\RegistraduriaService;` — only used in the old action closure
   - `use Filament\Notifications\Notification;` — only used in the old action closure

### C. Create resources/views/filament/registraduria-browser.blade.php

Create this new Blade view file:

```blade
{{--
    Registraduria headless browser modal.
    Variables:
        $sessionId  string  — Python service session ID
    Alpine.js handles:
        - Screenshot polling (img src updated every 400ms)
        - Status polling (fetch /registraduria/result every 2s)
        - Click forwarding (scaled coordinates POSTed to /registraduria/click)
        - Viewport fetch on init (for coordinate scaling)
--}}
<div
    x-data="{
        sessionId: @js($sessionId),
        status: 'pending',
        error: null,
        viewport: { width: 1280, height: 800 },
        screenshotSrc: '',
        statusInterval: null,
        screenshotInterval: null,

        init() {
            this.screenshotSrc = '/registraduria/screenshot/' + this.sessionId + '?t=' + Date.now();

            // Fetch viewport dimensions once for coordinate scaling
            fetch('/registraduria/viewport/' + this.sessionId)
                .then(r => r.json())
                .then(data => { if (data && data.width) this.viewport = data; })
                .catch(() => {});

            // Screenshot polling every 400ms — just change the src (no fetch overhead)
            this.screenshotInterval = setInterval(() => {
                this.screenshotSrc = '/registraduria/screenshot/' + this.sessionId + '?t=' + Date.now();
            }, 400);

            // Status polling every 2s
            this.statusInterval = setInterval(() => {
                fetch('/registraduria/result/' + this.sessionId)
                    .then(r => r.json())
                    .then(data => {
                        this.status = data.status ?? 'error';

                        if (data.status === 'done' || data.status === 'error') {
                            clearInterval(this.statusInterval);
                            clearInterval(this.screenshotInterval);
                            this.statusInterval = null;
                            this.screenshotInterval = null;

                            // Give a short pause so the screenshot updates to the result page
                            setTimeout(() => {
                                $wire.handleRegistraduriaResult(data);
                            }, 1500);
                        }
                    })
                    .catch(() => { this.error = 'Error de comunicación con el servicio.'; });
            }, 2000);
        },

        destroy() {
            if (this.statusInterval) clearInterval(this.statusInterval);
            if (this.screenshotInterval) clearInterval(this.screenshotInterval);
        },

        forwardClick(event) {
            const img = event.target;
            const rect = img.getBoundingClientRect();
            const scaleX = this.viewport.width / rect.width;
            const scaleY = this.viewport.height / rect.height;
            const x = Math.round((event.clientX - rect.left) * scaleX);
            const y = Math.round((event.clientY - rect.top) * scaleY);

            fetch('/registraduria/click/' + this.sessionId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: JSON.stringify({ x, y })
            }).catch(() => {});
        },

        statusLabel() {
            const labels = {
                pending: 'Cargando...',
                waiting_captcha: 'Esperando captcha',
                done: 'Completado',
                error: 'Error',
            };
            return labels[this.status] ?? this.status;
        },

        statusColor() {
            const colors = {
                pending: 'bg-gray-100 text-gray-700',
                waiting_captcha: 'bg-yellow-100 text-yellow-800',
                done: 'bg-green-100 text-green-800',
                error: 'bg-red-100 text-red-800',
            };
            return colors[this.status] ?? 'bg-gray-100 text-gray-700';
        }
    }"
    class="fixed inset-0 z-50 flex items-start justify-center bg-black/60 pt-8"
    @keydown.escape.window="$wire.closeRegistraduriaBrowser()"
>
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-4 flex flex-col overflow-hidden"
         style="max-height: 90vh;">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 shrink-0">
            <div class="flex items-center gap-3">
                <span class="font-semibold text-gray-800 text-sm">Registraduría — Consulta de puesto de votación</span>
                <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="statusColor()"
                    x-text="statusLabel()"
                ></span>
            </div>
            <button
                type="button"
                class="text-gray-400 hover:text-gray-600 transition-colors"
                @click="$wire.closeRegistraduriaBrowser()"
                title="Cerrar"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Instruction bar --}}
        <div class="bg-blue-50 border-b border-blue-100 px-5 py-2 shrink-0">
            <p class="text-xs text-blue-700">
                Haz clic directamente sobre la imagen para interactuar con la página.
                El formulario se llenará automáticamente al obtener el resultado.
            </p>
        </div>

        {{-- Screenshot area --}}
        <div class="flex-1 overflow-auto bg-gray-100 p-3">
            <template x-if="status !== 'error'">
                <img
                    :src="screenshotSrc"
                    alt="Registraduría"
                    class="w-full rounded cursor-pointer select-none object-contain object-top"
                    style="max-height: calc(85vh - 130px);"
                    @click="forwardClick($event)"
                    @error="/* ignore transient screenshot load errors */"
                />
            </template>
            <template x-if="status === 'error'">
                <div class="flex flex-col items-center justify-center h-48 gap-2 text-red-600">
                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                    <p class="text-sm font-medium" x-text="error ?? 'Ocurrió un error al consultar la Registraduría.'"></p>
                    <button
                        type="button"
                        class="text-xs underline text-red-500 hover:text-red-700"
                        @click="$wire.closeRegistraduriaBrowser()"
                    >Cerrar</button>
                </div>
            </template>
        </div>
    </div>
</div>
```
  </action>
  <verify>
    <automated>cd /Volumes/NAS(MAC)/Data/Herd/sigma-project && vendor/bin/pint --dirty 2>&1 | tail -5 && php artisan view:clear && php -r "require 'vendor/autoload.php'; echo 'PHP OK';"</automated>
  </verify>
  <done>
    - HasRegistraduriaPolling has `$registraduriaSessionId`, `$registraduriaOpen` properties
    - HasRegistraduriaPolling has `openRegistraduriaBrowser`, `handleRegistraduriaResult`, `closeRegistraduriaBrowser` methods
    - `pollRegistraduria` method is completely removed
    - VoterForm Placeholder is first component in schema
    - VoterForm suffixAction calls `$livewire->openRegistraduriaBrowser($state)`
    - VoterForm "Información Personal" Section has NO extraAttributes (Alpine polling removed)
    - registraduria-browser.blade.php exists with Alpine x-data, screenshot img, click forwarding
    - Pint passes on dirty files
    - No syntax errors
  </done>
</task>

<task type="auto">
  <name>Task 4: Write Pest feature tests and run Pint</name>
  <files>tests/Feature/RegistraduriaControllerTest.php</files>
  <action>
Create tests/Feature/RegistraduriaControllerTest.php using Pest. Test the 5 proxy routes with auth
protection and correct proxying behavior. Use Http::fake() to mock the Python service responses.

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('redirects unauthenticated users away from registraduria routes', function () {
    $this->get(route('registraduria.result', ['id' => 'test-id']))
        ->assertRedirect();

    $this->post(route('registraduria.lookup'), ['cedula' => '123'])
        ->assertRedirect();

    $this->get(route('registraduria.viewport', ['id' => 'test-id']))
        ->assertRedirect();
});

it('returns session id from lookup when service responds successfully', function () {
    Http::fake([
        '*/lookup' => Http::response(['session_id' => 'abc-123'], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('registraduria.lookup'), ['cedula' => '1234567890'])
        ->assertOk()
        ->assertJson(['session_id' => 'abc-123']);
});

it('returns 422 when cedula is missing from lookup', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('registraduria.lookup'), [])
        ->assertUnprocessable();
});

it('returns 503 when python service is unreachable for lookup', function () {
    Http::fake([
        '*/lookup' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('registraduria.lookup'), ['cedula' => '1234567890'])
        ->assertStatus(503);
});

it('proxies result status from python service', function () {
    Http::fake([
        '*/result/*' => Http::response(['status' => 'waiting_captcha', 'data' => null, 'error' => null], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('registraduria.result', ['id' => 'abc-123']))
        ->assertOk()
        ->assertJson(['status' => 'waiting_captcha']);
});

it('returns 404 when result session not found in python service', function () {
    Http::fake([
        '*/result/*' => Http::response(['error' => 'not found'], 404),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('registraduria.result', ['id' => 'nonexistent']))
        ->assertNotFound();
});

it('proxies screenshot as image/png', function () {
    $fakePng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    Http::fake([
        '*/screenshot/*' => Http::response($fakePng, 200, ['Content-Type' => 'image/png']),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('registraduria.screenshot', ['id' => 'abc-123']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('forwards click coordinates to python service', function () {
    Http::fake([
        '*/click/*' => Http::response(['ok' => true], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('registraduria.click', ['id' => 'abc-123']), ['x' => 640, 'y' => 400])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('returns 422 when click coordinates are missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('registraduria.click', ['id' => 'abc-123']), [])
        ->assertUnprocessable();
});

it('proxies viewport dimensions from python service', function () {
    Http::fake([
        '*/viewport/*' => Http::response(['width' => 1280, 'height' => 800], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('registraduria.viewport', ['id' => 'abc-123']))
        ->assertOk()
        ->assertJson(['width' => 1280, 'height' => 800]);
});
```

After creating the test file, run Pint on all modified PHP files:
```bash
vendor/bin/pint --dirty
```
  </action>
  <verify>
    <automated>cd /Volumes/NAS(MAC)/Data/Herd/sigma-project && php artisan test --filter=RegistraduriaController 2>&1</automated>
  </verify>
  <done>
    - All tests in RegistraduriaControllerTest pass
    - Auth protection tests confirm unauthenticated requests are redirected
    - Http::fake() prevents any real calls to the Python service during tests
    - Pint passes with no style violations
  </done>
</task>

</tasks>

<verification>
Full integration checklist (manual smoke test after tasks complete):

1. Start Python service: `cd registraduria-service && python app.py`
2. In a second terminal verify headless flag: `grep "headless=True" registraduria-service/app.py`
3. Open voter Create/Edit form in SIGMA
4. Type a cedula in "Número de Documento" and click the magnifying-glass button
5. Modal should appear overlaying the form with a live screenshot of the Registraduria site
6. Click "No soy un robot" on the screenshot — verify the click lands on the captcha in the browser
7. After completing captcha, click "Consultar" on the screenshot
8. Modal closes automatically and form fields (municipio, puesto, mesa) fill in
9. Run: `php artisan test --filter=RegistraduriaController`
</verification>

<success_criteria>
- Python service launches with headless=True and no display required
- /screenshot/, /click/, /viewport/ endpoints return expected responses from Python service
- All 5 PHP routes require authentication (unauthenticated returns redirect)
- Modal renders inside SIGMA Filament form without page navigation
- Screenshot updates every 400ms (verified by watching img src in DevTools)
- Click on screenshot POSTs to /registraduria/click with scaled coordinates and X-CSRF-TOKEN header
- On status=done the modal closes and form fields auto-fill
- All 9 Pest tests pass
- Pint passes on dirty files
</success_criteria>

<output>
After completion, create `.planning/quick/260508-wze-registraduria-headless-proxy-screenshots/260508-wze-SUMMARY.md`
</output>
