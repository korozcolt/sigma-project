---
phase: quick-260730-ofo
plan: 01
type: execute
wave: 1
depends_on: []
requirements: [QUICK-260730-ofo]
files_modified:
  - config/services.php
  - .env.example
  - database/migrations/2026_07_30_200000_create_two_captcha_balance_snapshots_table.php
  - app/Models/TwoCaptchaBalanceSnapshot.php
  - database/factories/TwoCaptchaBalanceSnapshotFactory.php
  - app/Services/TwoCaptchaService.php
  - app/Console/Commands/SnapshotTwoCaptchaBalance.php
  - routes/console.php
  - app/Enums/DailyCaptchaCostStatus.php
  - app/Services/DailyCaptchaCost.php
  - app/Services/TwoCaptchaDailyCostService.php
  - app/Services/SaldoColorResolver.php
  - resources/views/filament/components/saldos-badge.blade.php
  - app/Providers/Filament/AdminPanelProvider.php
  - tests/Feature/Services/TwoCaptchaServiceTest.php
  - tests/Feature/Console/SnapshotTwoCaptchaBalanceTest.php
  - tests/Feature/Services/TwoCaptchaDailyCostServiceTest.php
  - tests/Feature/Filament/SaldosBadgeTest.php
autonomous: true

must_haves:
  truths:
    - "Un SUPER_ADMIN ve un ícono/botón de saldos en la topbar del panel admin que abre un dropdown al hacer clic"
    - "El dropdown muestra el saldo de Hablame (COP) con color y el saldo de 2captcha (USD) con color"
    - "El dropdown lista el costo promedio por consulta de 2captcha de los últimos 7 días, con '—' (sin datos) o 'Recarga detectada' según el día"
    - "Un usuario que no es SUPER_ADMIN no ve el cuadrito en ninguna parte de la topbar"
    - "La topbar nunca llama a la API de 2captcha de forma síncrona: lee la última fila de snapshot; el saldo de Hablame se cachea 1h"
    - "Un comando programado guarda cada hora un snapshot del saldo 2captcha en su tabla"
    - "El cliente de 2captcha devuelve null (no lanza) ante clave inválida, error o timeout"
  artifacts:
    - path: "database/migrations/2026_07_30_200000_create_two_captcha_balance_snapshots_table.php"
      provides: "Tabla two_captcha_balance_snapshots (balance decimal, checked_at, timestamps), sin campaign scoping"
      contains: "two_captcha_balance_snapshots"
    - path: "app/Models/TwoCaptchaBalanceSnapshot.php"
      provides: "Modelo Eloquent del snapshot"
    - path: "app/Services/TwoCaptchaService.php"
      provides: "getBalance(): ?float con degradación grácil"
      exports: ["getBalance"]
    - path: "app/Console/Commands/SnapshotTwoCaptchaBalance.php"
      provides: "Comando balances:snapshot-2captcha"
    - path: "app/Services/TwoCaptchaDailyCostService.php"
      provides: "Cálculo del costo promedio diario por consulta (Bogotá), maneja cold-start/recarga/conteo-cero"
      exports: ["forDay", "lastDays"]
    - path: "app/Enums/DailyCaptchaCostStatus.php"
      provides: "Enum Computed/NoData/RechargeDetected"
    - path: "app/Services/DailyCaptchaCost.php"
      provides: "DTO tipado (día, status, averageUsd)"
    - path: "resources/views/filament/components/saldos-badge.blade.php"
      provides: "Vista Blade del cuadrito, gateada a super_admin, sin llamadas API síncronas"
      contains: "isSuperAdmin"
    - path: "app/Providers/Filament/AdminPanelProvider.php"
      provides: "Segundo renderHook TOPBAR_END que monta la vista solo en el panel admin"
  key_links:
    - from: "app/Providers/Filament/AdminPanelProvider.php"
      to: "resources/views/filament/components/saldos-badge.blade.php"
      via: "renderHook(PanelsRenderHook::TOPBAR_END, ...)"
      pattern: "saldos-badge"
    - from: "resources/views/filament/components/saldos-badge.blade.php"
      to: "app/Models/TwoCaptchaBalanceSnapshot.php"
      via: "latest('checked_at')->first() (lee snapshot, NO la API)"
      pattern: "TwoCaptchaBalanceSnapshot"
    - from: "resources/views/filament/components/saldos-badge.blade.php"
      to: "app/Services/HablameSmsService.php"
      via: "Cache::remember('saldo_hablame', now()->addHour(), fn () => ...->getAccountInfo())"
      pattern: "Cache::remember"
    - from: "resources/views/filament/components/saldos-badge.blade.php"
      to: "app/Services/TwoCaptchaDailyCostService.php"
      via: "lastDays(7)"
      pattern: "lastDays"
    - from: "app/Console/Commands/SnapshotTwoCaptchaBalance.php"
      to: "app/Services/TwoCaptchaService.php"
      via: "getBalance() -> TwoCaptchaBalanceSnapshot::create"
      pattern: "getBalance"
    - from: "routes/console.php"
      to: "app/Console/Commands/SnapshotTwoCaptchaBalance.php"
      via: "Schedule::command('balances:snapshot-2captcha')->hourly()->withoutOverlapping(10)"
      pattern: "balances:snapshot-2captcha"
    - from: "app/Services/TwoCaptchaDailyCostService.php"
      to: "database (snapshots + registraduria_lookups)"
      via: "delta de saldo ÷ conteo de lookups source='live' del día en Bogotá"
      pattern: "registraduria_lookups|source"
---

<objective>
Agregar un cuadrito de saldos SOLO para SUPER_ADMIN en la topbar del panel `admin`: un ícono que abre un dropdown con (1) saldo Hablame (COP, con color), (2) saldo 2captcha (USD, con color) y (3) el costo promedio por consulta de 2captcha de los últimos 7 días. El costo diario es una aproximación por delta de saldo (2captcha no expone historial de consumo) dividido entre el conteo de consultas live del día.

Propósito: dar visibilidad operativa de saldos externos sin exponer acciones de escritura, sin frenar la carga de páginas admin (nunca se llama a las APIs externas de forma síncrona en la topbar) y sin romper la topbar si una clave está mal/ausente (degrada a "N/D").

Salida: migración + modelo + factory de snapshots, `TwoCaptchaService`, comando programado horario, servicio de cálculo diario (enum + DTO + servicio), resolutor de colores, vista Blade gateada y su cableado en `AdminPanelProvider`, más pruebas Pest.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/quick/260730-ofo-agregar-cuadrito-de-saldos-hablame-2capt/260730-ofo-RESEARCH.md
@app/Services/HablameSmsService.php
@config/services.php
@resources/views/filament/components/campaign-context-switcher.blade.php
@app/Providers/Filament/AdminPanelProvider.php
@app/Console/Commands/ReconcileLivePollingPlaces.php
@routes/console.php

<interfaces>
<!-- Contratos ya existentes en el código. Úsalos directamente, sin explorar. -->

Gate super-admin (App\Services\CampaignContext):
```php
public static function isSuperAdmin(?App\Models\User $user = null): bool; // usa hasRole(UserRole::SUPER_ADMIN->value)
```

Hablame (App\Services\HablameSmsService) — REUSAR VERBATIM, no reimplementar:
```php
public function getAccountInfo(): array; // devuelve ['success' => bool, 'balance' => ?float (COP), ...] — 'balance' es COP confirmado
```

Clave 2captcha: ya existe en `.env` como `TWO_CAPTCHA_KEY` (valor 4b39eeff...). NO crear una variable nueva; el bloque de config debe leer `TWO_CAPTCHA_KEY`.

Tabla proxy de conteo de consultas (migración 2026_07_26_170000_create_registraduria_lookups_table):
`registraduria_lookups` con `source` (default 'live'), `created_at`. Modelo: App\Models\RegistraduriaLookup.

Patrón de comando programado (routes/console.php, estilo a imitar):
```php
Schedule::command('census:reconcile-live')->hourly()->withoutOverlapping(10);
```

Patrón de renderHook existente (AdminPanelProvider, línea ~90) — el nuevo hook COEXISTE, no reemplaza:
```php
->renderHook(PanelsRenderHook::TOPBAR_END, fn () => view('filament.components.campaign-context-switcher'))
```

DTO de referencia para forma/estilo: App\Services\PollingPlaceResolutionResult (los DTOs viven en app/Services).
</interfaces>

**Datos bloqueados (del RESEARCH y el task_detail — NO re-verificar):**
- 2captcha: `POST https://api.2captcha.com/getBalance`, body JSON `{"clientKey":"<key>"}`, éxito `{"errorId":0,"balance":0.93958}` (USD). `errorId != 0` = error (`errorCode` string). Envolver en try/catch + `Http::timeout(10)`; cualquier fallo → devolver `null`, nunca lanzar.
- Hablame `balance` = COP (no USD).
- Umbrales 2captcha (USD, aprobados, ajustables): verde > $5, amarillo $1–$5, rojo < $1.
- Umbrales Hablame (COP): PROVISIONALES/placeholder — verde > 500.000, amarillo 100.000–500.000, rojo < 100.000. Marcar con `// TODO ajustar con el usuario — valores provisionales` y constantes nombradas.
- Zona horaria operativa: America/Bogota (UTC-5, sin DST). Bucketear los días en Bogotá, no UTC.
- Cold start (sin snapshot del día previo) → renderizar "—", nunca 0 ni división por cero.
- Día con delta <= 0 (recarga/top-up) → "Recarga detectada", no un número.
- Conteo del día: `registraduria_lookups` con `source='live'` cuyo `created_at` cae en el día (Bogotá). Es una aproximación (un force-refresh de un documento ya resuelto actualiza la fila existente en vez de crear una → subconteo); documentarlo como limitación conocida en un comentario.
- Sin nuevas dependencias Composer — usar `Illuminate\Support\Facades\Http`.
</context>

<tasks>

<task type="auto">
  <name>Tarea 1: Cimientos — config, .env.example, migración, modelo, factory</name>
  <files>config/services.php, .env.example, database/migrations/2026_07_30_200000_create_two_captcha_balance_snapshots_table.php, app/Models/TwoCaptchaBalanceSnapshot.php, database/factories/TwoCaptchaBalanceSnapshotFactory.php</files>
  <action>
Crear la base de datos y config para los snapshots. Sin llamadas externas aquí.

1. `config/services.php`: agregar un bloque nuevo, con la misma forma que `hablame`, LEYENDO la variable existente `TWO_CAPTCHA_KEY` (no crear una nueva):
```php
'twocaptcha' => [
    'api_key' => env('TWO_CAPTCHA_KEY'),
    'api_url' => env('TWO_CAPTCHA_API_URL', 'https://api.2captcha.com'),
],
```
2. `.env.example`: agregar una línea `TWO_CAPTCHA_KEY=` (existe el archivo; hoy no contiene ninguna clave de captcha). No poner el valor real.
3. Migración: `php artisan make:migration create_two_captcha_balance_snapshots_table --no-interaction`. Renombrar/ubicar como el path del <files>. Esquema, SIN campaign_id (dato a nivel cuenta/sistema):
```php
Schema::create('two_captcha_balance_snapshots', function (Blueprint $table): void {
    $table->id();
    $table->decimal('balance', 12, 5); // USD, ej. 0.93958
    $table->timestamp('checked_at');
    $table->timestamps();
    $table->index('checked_at');
});
```
4. Modelo: `php artisan make:model TwoCaptchaBalanceSnapshot --no-interaction`. `declare(strict_types=1)`, `$fillable = ['balance', 'checked_at']`, método `casts()` (preferido en este repo): `['balance' => 'decimal:5', 'checked_at' => 'datetime']`. `use` explícitos.
5. Factory: `php artisan make:factory TwoCaptchaBalanceSnapshotFactory --no-interaction`, definiendo `balance` (ej. `$this->faker->randomFloat(5, 0, 50)`) y `checked_at` (`now()`).

Convenciones CLAUDE.md: `use` explícitos (nunca alias/inline), llaves siempre, tipos de retorno y de parámetros explícitos, PHPDoc sobre comentarios inline.
  </action>
  <verify>
    <automated>php artisan migrate --no-interaction && php artisan tinker --execute="echo App\Models\TwoCaptchaBalanceSnapshot::factory()->make()->balance;"</automated>
  </verify>
  <done>La migración corre; el modelo y su factory instancian; `config('services.twocaptcha.api_key')` resuelve a `TWO_CAPTCHA_KEY`; `.env.example` incluye `TWO_CAPTCHA_KEY=`.</done>
</task>

<task type="auto" tdd="true">
  <name>Tarea 2: TwoCaptchaService::getBalance() con degradación grácil</name>
  <files>app/Services/TwoCaptchaService.php, tests/Feature/Services/TwoCaptchaServiceTest.php</files>
  <behavior>
    - Respuesta `{"errorId":0,"balance":0.93958}` → devuelve `0.93958` (float).
    - Respuesta con `errorId != 0` (ej. `{"errorId":1,"errorCode":"ERROR_KEY_DOES_NOT_EXIST"}`) → devuelve `null`, no lanza.
    - Excepción de conexión / timeout → devuelve `null`, no lanza.
    - `api_key` vacía/null (config) → devuelve `null` sin hacer HTTP.
  </behavior>
  <action>
Crear `app/Services/TwoCaptchaService.php` (`declare(strict_types=1)`, namespace `App\Services`). Constructor con property promotion que lea config en propiedades (`config('services.twocaptcha.api_key')`, `config('services.twocaptcha.api_url')`) — mismo estilo que `HablameSmsService::__construct`.

```php
public function getBalance(): ?float
```
- Si no hay api_key → `return null;`.
- `Http::timeout(10)->acceptJson()->post("{$this->apiUrl}/getBalance", ['clientKey' => $this->apiKey])` dentro de try/catch (`\Throwable`).
- Si `$response->successful()` y `($json['errorId'] ?? null) === 0` y `isset($json['balance'])` → `return (float) $json['balance'];`.
- Cualquier otro caso: `Log::warning(...)` (contexto: errorCode si existe) y `return null;`. En el catch: `Log::error(...)` y `return null;`.

`use` explícitos: `Illuminate\Support\Facades\Http`, `Illuminate\Support\Facades\Log`.

Test (`php artisan make:test --pest tests/Feature/Services/TwoCaptchaServiceTest.php`): usar `Http::fake([...])` para los 3 casos de respuesta + `config(['services.twocaptcha.api_key' => null])` para el caso sin clave. Para el timeout, `Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'))`. Assert que devuelve float / null según corresponda y que nunca lanza.
  </action>
  <verify>
    <automated>php artisan test --filter=TwoCaptchaServiceTest</automated>
  </verify>
  <done>getBalance() devuelve el float en éxito y `null` (sin lanzar) ante error, timeout o clave ausente; todos los casos del test verdes.</done>
</task>

<task type="auto">
  <name>Tarea 3: Comando de snapshot horario + entrada en el scheduler</name>
  <files>app/Console/Commands/SnapshotTwoCaptchaBalance.php, routes/console.php, tests/Feature/Console/SnapshotTwoCaptchaBalanceTest.php</files>
  <action>
1. Comando: `php artisan make:command SnapshotTwoCaptchaBalance --no-interaction`. `declare(strict_types=1)`. `protected $signature = 'balances:snapshot-2captcha';` y una `$description` clara. Inyectar `TwoCaptchaService` en `handle(TwoCaptchaService $service): int`:
```php
$balance = $service->getBalance();
if ($balance === null) {
    $this->warn('Saldo 2captcha no disponible; no se guarda snapshot.');
    return self::SUCCESS;
}
TwoCaptchaBalanceSnapshot::create(['balance' => $balance, 'checked_at' => now()]);
return self::SUCCESS;
```
No escribir fila cuando el saldo es null (evita snapshots basura que corromperían el delta diario). `use` explícitos.

2. `routes/console.php`: agregar, imitando el estilo exacto de `census:reconcile-live`, con un comentario en español:
```php
// Snapshot horario del saldo 2captcha para el cuadrito de saldos y el promedio diario
Schedule::command('balances:snapshot-2captcha')->hourly()->withoutOverlapping(10);
```

3. Test (`php artisan make:test --pest tests/Feature/Console/SnapshotTwoCaptchaBalanceTest.php`): `Http::fake` con respuesta de saldo exitosa → `artisan('balances:snapshot-2captcha')->assertSuccessful()` y `assertDatabaseHas('two_captcha_balance_snapshots', [...])`. Segundo caso: `Http::fake` de error → assertSuccessful pero `assertDatabaseCount('two_captcha_balance_snapshots', 0)`. Usar `RefreshDatabase`.
  </action>
  <verify>
    <automated>php artisan test --filter=SnapshotTwoCaptchaBalanceTest</automated>
  </verify>
  <done>El comando guarda una fila de snapshot con saldo válido y NO guarda nada cuando el saldo es null; la entrada `->hourly()->withoutOverlapping(10)` está en routes/console.php; tests verdes.</done>
</task>

<task type="auto" tdd="true">
  <name>Tarea 4: Cálculo del costo promedio diario (enum + DTO + servicio)</name>
  <files>app/Enums/DailyCaptchaCostStatus.php, app/Services/DailyCaptchaCost.php, app/Services/TwoCaptchaDailyCostService.php, tests/Feature/Services/TwoCaptchaDailyCostServiceTest.php</files>
  <behavior>
    - Día normal (snapshot de apertura antes del día + snapshot dentro del día con saldo menor, y N lookups live) → status Computed, averageUsd = (apertura − cierre) / N.
    - Día de recarga (delta <= 0, el saldo subió o quedó igual) → status RechargeDetected, averageUsd null.
    - Cold start (no hay snapshot ANTES del inicio del día) → status NoData, averageUsd null.
    - Conteo cero (hay delta positivo pero 0 lookups live ese día) → status NoData (guarda de división), averageUsd null.
    - Los días se bucketean en America/Bogota, no en UTC.
  </behavior>
  <action>
Orden interface-first: enum → DTO → servicio.

1. `app/Enums/DailyCaptchaCostStatus.php`: enum string-backed, casos TitleCase `Computed`, `NoData`, `RechargeDetected`.

2. `app/Services/DailyCaptchaCost.php`: DTO `readonly` (mismo lugar/estilo que `PollingPlaceResolutionResult`):
```php
public function __construct(
    public Carbon\CarbonImmutable $day,
    public DailyCaptchaCostStatus $status,
    public ?float $averageUsd = null,
) {}
```
`use` explícitos.

3. `app/Services/TwoCaptchaDailyCostService.php`:
- Constante `private const TIMEZONE = 'America/Bogota';`
- `public function forDay(Carbon\CarbonInterface $day): DailyCaptchaCost`:
  - `$start = CarbonImmutable::parse($day)->timezone(self::TIMEZONE)->startOfDay();` `$endExclusive = $start->addDay();`
  - Convertir a UTC para consultar (los timestamps se guardan en UTC): `$startUtc = $start->utc(); $endUtc = $endExclusive->utc();`
  - `$opening` = último `TwoCaptchaBalanceSnapshot` con `checked_at < $startUtc` (`orderByDesc('checked_at')->first()`).
  - `$closing` = último snapshot con `checked_at >= $startUtc AND checked_at < $endUtc` (un snapshot DENTRO del día).
  - Si `$opening === null || $closing === null` → `DailyCaptchaCost($start, NoData)`.
  - `$spend = (float) $opening->balance - (float) $closing->balance;`
  - Si `$spend <= 0` → `DailyCaptchaCost($start, RechargeDetected)`.
  - `$count = RegistraduriaLookup::query()->where('source', 'live')->where('created_at', '>=', $startUtc)->where('created_at', '<', $endUtc)->count();`
    - PHPDoc/comentario: aproximación conocida — un force-refresh de un documento ya resuelto hace `updateOrCreate` sobre la fila existente sin cambiar `created_at`, por lo que este conteo puede subestimar los solves reales de 2captcha.
    - Se usa un rango sobre timestamps UTC (no `CONVERT_TZ`/`DATE(... AT TIME ZONE ...)`) para no depender de las tablas de zona horaria de MySQL.
  - Si `$count === 0` → `DailyCaptchaCost($start, NoData)` (guarda de división).
  - Si no → `DailyCaptchaCost($start, Computed, averageUsd: $spend / $count)`.
- `public function lastDays(int $days = 7): array` → devuelve `array<DailyCaptchaCost>` para hoy y los `days-1` días anteriores (bucketeados en Bogotá), del más reciente al más antiguo; itera llamando a `forDay()`.

Tipos de retorno y parámetros explícitos; `use` explícitos (`Carbon\CarbonImmutable`, `Carbon\CarbonInterface`, `App\Enums\DailyCaptchaCostStatus`, `App\Models\RegistraduriaLookup`, `App\Models\TwoCaptchaBalanceSnapshot`).

4. Test (`php artisan make:test --pest tests/Feature/Services/TwoCaptchaDailyCostServiceTest.php`, `RefreshDatabase`): sembrar snapshots vía factory con `checked_at` controlados (usar Bogotá para elegir los instantes) y `RegistraduriaLookup::factory()` con `source='live'` y `created_at` en el día objetivo. Cubrir los 4 casos del <behavior>. Verificar además que un lookup con `created_at` justo fuera del rango Bogotá NO se cuenta (prueba de frontera de día).
  </action>
  <verify>
    <automated>php artisan test --filter=TwoCaptchaDailyCostServiceTest</automated>
  </verify>
  <done>forDay() devuelve el DTO tipado correcto para día normal, recarga, cold-start y conteo-cero; lastDays(7) devuelve 7 DTOs bucketeados en Bogotá; tests verdes.</done>
</task>

<task type="auto">
  <name>Tarea 5: Vista Blade del cuadrito + resolutor de colores + cableado en AdminPanelProvider</name>
  <files>app/Services/SaldoColorResolver.php, resources/views/filament/components/saldos-badge.blade.php, app/Providers/Filament/AdminPanelProvider.php, tests/Feature/Filament/SaldosBadgeTest.php</files>
  <action>
1. `app/Services/SaldoColorResolver.php` (`declare(strict_types=1)`): constantes nombradas + dos métodos estáticos que devuelven clases Tailwind de badge (string).
```php
// 2captcha (USD) — umbrales aprobados por el usuario, ajustables
private const TWOCAPTCHA_GREEN_MIN_USD = 5.0;
private const TWOCAPTCHA_YELLOW_MIN_USD = 1.0;
// TODO ajustar con el usuario — valores provisionales (COP), aún sin confirmar
private const HABLAME_GREEN_MIN_COP = 500000.0;
private const HABLAME_YELLOW_MIN_COP = 100000.0;

public static function twoCaptcha(?float $usd): string; // null -> gris "N/D"
public static function hablame(?float $cop): string;     // null -> gris "N/D"
```
Cada método: `null` → clases grises (ej. `'bg-gray-100 text-gray-600'`); >= verde-min → verde; >= amarillo-min → amarillo; si no → rojo. Usar `match(true)` o if/else con llaves.

2. `resources/views/filament/components/saldos-badge.blade.php`: replicar EXACTO el gate de `campaign-context-switcher.blade.php`. En el bloque `@php` (con `use` explícitos):
```php
use App\Services\CampaignContext;
if (! CampaignContext::isSuperAdmin()) {
    return;
}
$captchaSnapshot = App\Models\TwoCaptchaBalanceSnapshot::query()->orderByDesc('checked_at')->first();
```
- `use` explícitos también para `TwoCaptchaBalanceSnapshot`, `HablameSmsService`, `TwoCaptchaDailyCostService`, `SaldoColorResolver`, `App\Enums\DailyCaptchaCostStatus`, `Illuminate\Support\Facades\Cache`.
- 2captcha para mostrar: leer `$captchaSnapshot?->balance` (LA ÚLTIMA FILA DEL SNAPSHOT — NO llamar a la API desde la vista). Si null → "N/D".
- Hablame: `Cache::remember('saldo_hablame', now()->addHour(), fn () => app(HablameSmsService::class)->getAccountInfo())`; saldo = `($info['success'] ?? false) ? $info['balance'] : null`.
- Días: `app(TwoCaptchaDailyCostService::class)->lastDays(7)`.
- Colores vía `SaldoColorResolver::twoCaptcha(...)` / `::hablame(...)`.
- UI: un botón/ícono en la topbar que abre un dropdown al hacer clic (Alpine ya está incluido — usar `x-data="{ open: false }"`, `@click`, `x-show`, `@click.outside`, mirroring el ejemplo del menú-avatar). Root único con `id="saldos-badge"`. Contenido del dropdown: badge Hablame (COP, formatear con `number_format($cop, 0, ',', '.')` y sufijo " COP"), badge 2captcha (USD, `'$'.number_format($usd, 5)`), y una lista de los 7 días: por día mostrar la fecha (`$dia->day->format('d/m')`) y, según `$dia->status`: `Computed` → `'$'.number_format($dia->averageUsd, 5)`; `NoData` → `'—'`; `RechargeDetected` → `'Recarga detectada'`. Todo el texto en español. Solo lectura, sin formularios ni acciones de escritura.

3. `app/Providers/Filament/AdminPanelProvider.php`: agregar un SEGUNDO `->renderHook(...)` inmediatamente después del hook existente de `campaign-context-switcher` (coexisten, no reemplazar), SOLO en este panel `admin`:
```php
->renderHook(PanelsRenderHook::TOPBAR_END, fn () => view('filament.components.saldos-badge'))
```

4. Test (`php artisan make:test --pest tests/Feature/Filament/SaldosBadgeTest.php`): actuar como SUPER_ADMIN, `get('/admin')` (Dashboard) y `assertSee('saldos-badge')` (o un texto ancla en español del cuadrito). Segundo caso: un usuario que SÍ puede entrar al panel admin pero NO es super_admin (revisar cómo se prueba/gatea campaign-context-switcher y qué rol usar, ej. admin_campaign) → `assertDontSee('saldos-badge')`. Sembrar al menos un `TwoCaptchaBalanceSnapshot` para que la vista tenga datos. Reusar los helpers/roles de los tests Filament existentes.

Convenciones: `use` explícitos en todo PHP; Pint al final.
  </action>
  <verify>
    <automated>php artisan test --filter=SaldosBadgeTest</automated>
  </verify>
  <done>Un SUPER_ADMIN ve el cuadrito (badges de saldo + lista de 7 días) en la topbar admin; un no-super_admin no lo ve; la vista lee snapshot/caché (no la API en vivo); test verde.</done>
</task>

</tasks>

<verification>
- `vendor/bin/pint --dirty` sin cambios pendientes de estilo.
- `php artisan test --filter=TwoCaptchaServiceTest` y `--filter=TwoCaptchaDailyCostServiceTest` y `--filter=SnapshotTwoCaptchaBalanceTest` y `--filter=SaldosBadgeTest` verdes.
- `grep -n "TWO_CAPTCHA_KEY" config/services.php .env.example` confirma la config y el placeholder.
- `grep -n "balances:snapshot-2captcha" routes/console.php` confirma la entrada horaria.
- Confirmar que la vista NO contiene ninguna llamada directa a `TwoCaptchaService::getBalance()` ni a `Http::` (solo lee snapshot + Hablame cacheado).
</verification>

<success_criteria>
- SUPER_ADMIN ve el cuadrito de saldos en la topbar del panel admin; ningún otro rol lo ve.
- El dropdown muestra saldo Hablame (COP, color), saldo 2captcha (USD, color) y el promedio diario de 2captcha de 7 días con estados '—' / 'Recarga detectada' / monto.
- La topbar nunca llama a la API de 2captcha de forma síncrona; Hablame se cachea 1h.
- El comando horario puebla `two_captcha_balance_snapshots`; el cálculo diario maneja cold-start, recarga y conteo-cero sin dividir por cero.
- Cobertura Pest de: cliente 2captcha (éxito/error/timeout/sin-clave), comando (guarda / no guarda), cálculo diario (normal/recarga/cold-start/conteo-cero/frontera de día), gating del cuadrito (super_admin vs no).
- Umbrales Hablame marcados como provisionales en código (constantes + `// TODO`).

Nota (no bloqueante, preferencia del usuario): antes de desplegar a producción, verificar el cuadrito en un navegador real (render, apertura del dropdown, colores) — las pruebas Pest/Livewire no bastan para este badge de topbar.
</success_criteria>

<output>
Al completar, crear `.planning/quick/260730-ofo-agregar-cuadrito-de-saldos-hablame-2capt/260730-ofo-SUMMARY.md`.
</output>
