---
phase: quick
plan: 260514-mng
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Console/Commands/DispatchBirthdayWebhooks.php
  - app/Services/BirthdayWebhookService.php
  - routes/console.php
  - app/Filament/Resources/Campaigns/Schemas/CampaignForm.php
  - tests/Feature/DispatchBirthdayWebhooksTest.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "birthday:dispatch-webhooks command runs and posts JSON to configured webhook URLs"
    - "Command skips campaigns with webhook disabled or wrong time"
    - "Command skips HTTP call entirely when nobody has birthday today"
    - "Scheduler fires the command every minute without overlapping"
    - "CampaignForm shows birthday webhook section before Configuración"
  artifacts:
    - path: "app/Console/Commands/DispatchBirthdayWebhooks.php"
      provides: "Artisan command with --campaign and --force options"
    - path: "app/Services/BirthdayWebhookService.php"
      provides: "HTTP dispatch logic with payload builder"
    - path: "routes/console.php"
      provides: "everyMinute schedule entry"
    - path: "app/Filament/Resources/Campaigns/Schemas/CampaignForm.php"
      provides: "Toggle/URL/TimePicker section before Configuración"
    - path: "tests/Feature/DispatchBirthdayWebhooksTest.php"
      provides: "5 Pest tests covering happy and skip paths"
  key_links:
    - from: "DispatchBirthdayWebhooks"
      to: "BirthdayWebhookService::dispatch"
      via: "time match or --force flag"
    - from: "BirthdayWebhookService"
      to: "Http::post($url, $payload)"
      via: "Illuminate\\Support\\Facades\\Http"
---

<objective>
Implement birthday webhook automation: an artisan command that fires per-campaign HTTP webhooks with today's birthday people (voters + coordinators/leaders), a service that builds and posts the payload, a per-minute scheduler entry, and a CampaignForm section to configure the feature.

Purpose: Campaign operators can receive real-time birthday lists at a configured time without manual intervention.
Output: Command, service, schedule entry, form section, and Pest tests.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@app/Console/Commands/SendBirthdayMessages.php
@app/Filament/Resources/Campaigns/Schemas/CampaignForm.php
@app/Models/Campaign.php
@app/Enums/UserRole.php
@routes/console.php
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: BirthdayWebhookService and DispatchBirthdayWebhooks command</name>
  <files>
    app/Services/BirthdayWebhookService.php,
    app/Console/Commands/DispatchBirthdayWebhooks.php,
    tests/Feature/DispatchBirthdayWebhooksTest.php
  </files>
  <behavior>
    - Test: dispatches webhook when voter has birthday today and time matches — Http::assertSent() receives correct payload keys
    - Test: dispatches webhook when user coordinator has birthday today — people array includes type=coordinator entry
    - Test: skips HTTP when time does NOT match configured birthday_webhook_time
    - Test: skips HTTP when birthday_webhook_enabled is false
    - Test: no HTTP call when nobody has birthday today (voters or users)
  </behavior>
  <action>
**BirthdayWebhookService** (`app/Services/BirthdayWebhookService.php`):

```
declare(strict_types=1);
namespace App\Services;

use App\Models\Campaign;
use App\Models\Voter;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BirthdayWebhookService
{
    public function dispatch(Campaign $campaign, Carbon $colombia): void
```

- Query voters: `Voter::where('campaign_id', $campaign->id)->whereNotNull('birth_date')->whereMonth('birth_date', $colombia->month)->whereDay('birth_date', $colombia->day)->get()`
- Query campaign users with birthday today AND role coordinator OR leader:
  `$campaign->users()->whereNotNull('birth_date')->whereMonth('birth_date', $colombia->month)->whereDay('birth_date', $colombia->day)->whereHas('roles', fn($q) => $q->whereIn('name', ['coordinator', 'leader']))->get()`
- If both collections are empty: `Log::info('No birthdays today for campaign', ['campaign_id' => $campaign->id])` then return — NO Http call.
- Build people array:
  - Voter entry: `type=voter`, `id`, `full_name` (from accessor), `first_name`, `last_name`, `document_number`, `phone`, `birth_date` formatted `Y-m-d`, `age = $colombia->diffInYears(Carbon::parse($voter->birth_date))`
  - User entry: `type = $user->hasRole('coordinator') ? 'coordinator' : 'leader'`, `id`, `full_name = $user->name`, split first/last via `explode(' ', $user->name, 2)`, `document_number`, `phone`, `birth_date` formatted `Y-m-d`, `age = $colombia->diffInYears(Carbon::parse($user->birth_date))`
- Payload:
  ```json
  {
    "campaign_id": $campaign->id,
    "campaign_name": $campaign->name,
    "candidate_name": $campaign->settings['candidate_name'] ?? $campaign->name,
    "date": $colombia->format('Y-m-d'),
    "dispatched_at": $colombia->toIso8601String(),
    "total": count($people),
    "people": $people
  }
  ```
- `Http::timeout(30)->post($url, $payload)->throw()` — throws on non-2xx; caller catches.

---

**DispatchBirthdayWebhooks** (`app/Console/Commands/DispatchBirthdayWebhooks.php`):

Signature: `birthday:dispatch-webhooks {--campaign= : ID de la campaña específica} {--force : Omitir verificación de hora}`

Description: `Despacha webhooks de cumpleaños a las campañas configuradas`

Follow the same pattern as `SendBirthdayMessages`: `declare(strict_types=1)`, constructor injects `BirthdayWebhookService $webhookService` via property promotion.

`handle()` logic:
1. `$colombia = now()->timezone('America/Bogota')`
2. Build campaign query: if `--campaign` option set, `Campaign::where('id', $this->option('campaign'))`, else `Campaign::query()->active()`. Filter to only those where `settings->birthday_webhook_enabled = true` AND `settings->birthday_webhook_url` is not null (use PHP-level filter on collection or raw JSON query if supported).
3. For each campaign: get `$configuredTime = $campaign->settings['birthday_webhook_time'] ?? '08:00'`. If NOT `--force` and `$colombia->format('H:i') !== $configuredTime` → `Log::info('Skipping campaign, time not matched', ...)` → continue.
4. Try: call `$this->webhookService->dispatch($campaign, $colombia)`. Log info on success. Catch `\Throwable $e`: `Log::warning('Webhook failed for campaign', ['campaign_id' => $campaign->id, 'error' => $e->getMessage()])` and continue loop.
5. Return `self::SUCCESS`.

---

**Tests** (`tests/Feature/DispatchBirthdayWebhooksTest.php`):

```php
uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 5, 14, 13, 0, 0, 'UTC')); // 08:00 Colombia
});

afterEach(function () {
    Carbon::setTestNow(null);
});
```

Test 1 "dispatches webhook when voter has birthday today and time matches":
- Create campaign with `settings = ['birthday_webhook_enabled' => true, 'birthday_webhook_url' => 'https://test.example.com/hook', 'birthday_webhook_time' => '08:00']`
- Create voter with `campaign_id`, `birth_date = '1990-05-14'`
- `Http::fake(['*' => Http::response([], 200)])`
- `artisan('birthday:dispatch-webhooks')->assertSuccessful()`
- `Http::assertSent(fn ($request) => $request->url() === 'https://test.example.com/hook' && $request['campaign_id'] === $campaign->id && count($request['people']) === 1 && $request['people'][0]['type'] === 'voter')`

Test 2 "dispatches webhook when coordinator has birthday today":
- Create campaign with webhook settings
- Create user with `birth_date = '1985-05-14'`, assign role `coordinator`, attach to campaign
- `Http::fake(...)`, run command, `Http::assertSent(fn ($r) => $r['people'][0]['type'] === 'coordinator')`

Test 3 "skips when time does not match":
- Campaign with `birthday_webhook_time = '10:00'` (current Colombia time is 08:00)
- Create voter with birthday today
- `Http::fake(...)`, run command
- `Http::assertNothingSent()`

Test 4 "skips when birthday_webhook_enabled is false":
- Campaign with `birthday_webhook_enabled = false`
- Create voter with birthday today
- `Http::fake(...)`, run command
- `Http::assertNothingSent()`

Test 5 "no HTTP call when nobody has birthday today":
- Campaign with webhook enabled and time matching
- No voters or users with today's birthday
- `Http::fake(...)`, run command
- `Http::assertNothingSent()`

All tests use factories. Use `Http::fake` from `Illuminate\Support\Facades\Http`.
  </action>
  <verify>
    <automated>php artisan test tests/Feature/DispatchBirthdayWebhooksTest.php --stop-on-failure</automated>
  </verify>
  <done>All 5 tests pass. Service dispatches correct JSON payload. Command skips on time mismatch or disabled. No HTTP call when nobody has birthday.</done>
</task>

<task type="auto">
  <name>Task 2: CampaignForm birthday section + console schedule entry</name>
  <files>
    app/Filament/Resources/Campaigns/Schemas/CampaignForm.php,
    routes/console.php
  </files>
  <action>
**CampaignForm** — insert new Section before the existing `Section::make('Configuración')` (currently the last section at line ~194).

Add imports at top with existing `use` statements (keep alphabetical per PSR-12):
```php
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
```

Insert Section before `Section::make('Configuración')`:
```php
Section::make('Automatización de Cumpleaños')
    ->schema([
        Toggle::make('settings.birthday_webhook_enabled')
            ->label('Activar webhook de cumpleaños')
            ->helperText('Al activar, se enviará automáticamente un JSON al webhook en la hora configurada.')
            ->columnSpanFull()
            ->live(),
        TextInput::make('settings.birthday_webhook_url')
            ->label('URL del Webhook')
            ->url()
            ->placeholder('https://hooks.ejemplo.com/cumpleanos')
            ->columnSpanFull()
            ->visible(fn (Get $get) => (bool) $get('settings.birthday_webhook_enabled')),
        TimePicker::make('settings.birthday_webhook_time')
            ->label('Hora de envío (horario colombiano)')
            ->seconds(false)
            ->default('08:00')
            ->helperText('Hora en la que se enviará el webhook (hora de Colombia, UTC-5).')
            ->visible(fn (Get $get) => (bool) $get('settings.birthday_webhook_enabled')),
    ])
    ->collapsible()
    ->columns(2),
```

Note: `Get` is already imported as `Filament\Schemas\Components\Utilities\Get` — do NOT re-import. `TextInput` is already imported. Only add `TimePicker` and `Toggle` imports.

---

**routes/console.php** — append after the existing birthday line:
```php
Schedule::command('birthday:dispatch-webhooks')->everyMinute()->withoutOverlapping();
```

---

After both edits run: `vendor/bin/pint --dirty`
  </action>
  <verify>
    <automated>php artisan route:list --filter=inspire 2>&1; php artisan list | grep birthday</automated>
  </verify>
  <done>
    - `php artisan list | grep birthday` shows both `birthday:dispatch-webhooks` and `messages:send-birthdays`
    - CampaignForm compiles without errors (`php artisan config:cache` passes)
    - Pint reports no dirty files
  </done>
</task>

</tasks>

<verification>
- `php artisan test tests/Feature/DispatchBirthdayWebhooksTest.php` — all 5 tests pass
- `php artisan list | grep birthday` — command listed
- `php artisan birthday:dispatch-webhooks --help` — shows `--campaign` and `--force` options
- `vendor/bin/pint --dirty` — no output (clean)
</verification>

<success_criteria>
- BirthdayWebhookService builds correct JSON payload and uses Http::timeout(30)->post()
- DispatchBirthdayWebhooks filters by time OR --force, loops campaigns, logs warnings on failures
- Scheduler entry added with everyMinute()->withoutOverlapping()
- CampaignForm shows birthday section (Toggle + URL + TimePicker) before Configuración
- All 5 Pest tests pass
</success_criteria>

<output>
After completion, create `.planning/quick/260514-mng-implementar-automatizaci-n-de-webhook-de/260514-mng-SUMMARY.md`
</output>
