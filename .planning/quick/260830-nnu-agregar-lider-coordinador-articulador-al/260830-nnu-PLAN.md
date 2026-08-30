---
phase: quick
plan: 260830-nnu
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Filament/Resources/Voters/Pages/ViewVoter.php
  - tests/Feature/Filament/VoterResourceTest.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "Al abrir el detalle (Ver) de un apoyo, el usuario ve quién es su Líder, Coordinador y Articulador, igual que ya se ve en el listado de apoyos."
    - "Cuando el registrador del apoyo es un coordinador registrando directo (sin líder intermedio), el detalle muestra 'N/A' para Líder y el propio coordinador para Coordinador."
  artifacts:
    - path: "app/Filament/Resources/Voters/Pages/ViewVoter.php"
      provides: "3 nuevas TextEntry (Líder, Coordinador, Articulador) en el infolist + eager load de registeredBy.coordinator.areaCoordinator"
  key_links:
    - from: "app/Filament/Resources/Voters/Pages/ViewVoter.php"
      to: "App\\Models\\Voter::registeredBy / User::coordinator / User::areaCoordinator"
      via: "->state() closures resolviendo la cadena registrador -> coordinador -> articulador"
      pattern: "registeredBy.*coordinator.*areaCoordinator"
---

<objective>
Agregar al detalle individual del apoyo (pantalla "Ver" de `ViewVoter`) los mismos 3 datos de cadena de mando que ya se agregaron hoy al listado de apoyos (`VotersTable`): Líder, Coordinador y Articulador — reutilizando exactamente la misma lógica de resolución (el registrador puede ser un líder normal, o un coordinador registrando directo).

Purpose: El cliente notó que esta información está en el listado pero falta en el detalle individual — inconsistencia entre las dos pantallas.
Output: `ViewVoter.php` con 3 nuevas entradas en el infolist y eager load explícito; test Pest cubriendo ambos casos (registrador líder vs. registrador coordinador directo).
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@app/Filament/Resources/Voters/Pages/ViewVoter.php
@app/Filament/Resources/Voters/Tables/VotersTable.php
</context>

<interfaces>
<!-- Patrón exacto ya usado hoy en VotersTable.php (líneas 134-158), a reutilizar textualmente en el infolist -->

```php
// Eager load (VotersTable.php línea 32):
->modifyQueryUsing(fn (Builder $query) => $query->with('registeredBy.coordinator.areaCoordinator'))

// Columna "Coordinador" (VotersTable.php líneas 134-145):
TextColumn::make('coordinador')
    ->label('Coordinador')
    ->state(function (Voter $record): string {
        $registrador = $record->registeredBy;

        if ($registrador?->hasRole(UserRole::COORDINATOR->value)) {
            return $registrador->name;
        }

        return $registrador?->coordinator?->name ?? 'N/A';
    }),

// Columna "Articulador" (VotersTable.php líneas 147-158):
TextColumn::make('articulador')
    ->label('Articulador')
    ->state(function (Voter $record): string {
        $registrador = $record->registeredBy;

        if ($registrador?->hasRole(UserRole::COORDINATOR->value)) {
            return $registrador->areaCoordinator?->name ?? 'N/A';
        }

        return $registrador?->coordinator?->areaCoordinator?->name ?? 'N/A';
    }),
```

`ViewVoter` es una `Filament\Resources\Pages\ViewRecord` — el `infolist()` actual vive inline en `ViewVoter.php` (líneas 24-89), no hay Schema separado; seguir la misma estructura y no crear un archivo nuevo.

Para el eager load en una página `ViewRecord`, sobreescribir `resolveRecord()` (método heredado de `Filament\Resources\Pages\Concerns\InteractsWithRecord`, firma `resolveRecord(int|string $key): Model`) para encadenar `->loadMissing(...)` sobre el registro ya resuelto por el padre — evita duplicar la query base del resource y evita N+1 en los 3 nuevos `->state()`.

Confirmado en `app/Models/User.php` (líneas 42, 148-150): la relación `areaCoordinator()` usa la FK `area_coordinator_user_id` (NO `area_coordinator_id`).
</interfaces>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Agregar Líder/Coordinador/Articulador al infolist de ViewVoter</name>
  <files>app/Filament/Resources/Voters/Pages/ViewVoter.php, tests/Feature/Filament/VoterResourceTest.php</files>
  <behavior>
    - Test 1 (registrador es líder normal): un `Voter` registrado por un `User` con rol `LEADER` cuyo `coordinator_user_id` apunta a un coordinador con `areaCoordinator` asignado → el detalle muestra el nombre del líder en "Líder", el nombre del coordinador en "Coordinador", y el nombre del articulador en "Articulador".
    - Test 2 (registrador es coordinador registrando directo, sin líder intermedio): un `Voter` registrado directamente por un `User` con rol `COORDINATOR` → el detalle muestra "N/A" en "Líder", el nombre de ese mismo coordinador en "Coordinador", y el nombre de su `areaCoordinator` en "Articulador".
  </behavior>
  <action>
    En `app/Filament/Resources/Voters/Pages/ViewVoter.php`:

    1. Agregar `use App\Enums\UserRole;` y `use Illuminate\Database\Eloquent\Model;` a los imports (orden alfabético, siguiendo convención Pint).
    2. Sobreescribir `resolveRecord()` para eager-loadear la cadena de relaciones, igual que `VotersTable.php` ya hace hoy en el listado:
       ```php
       protected function resolveRecord(int|string $key): Model
       {
           return parent::resolveRecord($key)->loadMissing('registeredBy.coordinator.areaCoordinator');
       }
       ```
    3. Dentro del `infolist()`, agregar 3 nuevas `Components\TextEntry` inmediatamente después de `campaign.name` (línea 43) y antes de `census_validated_at` (línea 45), reutilizando textualmente la lógica de resolución de `VotersTable.php`:
       - `lider`: label "Líder" — si `registeredBy` tiene rol `LEADER`, mostrar `$registrador->name`; si no, `'N/A'`.
       - `coordinador`: label "Coordinador" — mismo patrón exacto que `VotersTable.php` líneas 134-145 (si `registeredBy` tiene rol `COORDINATOR`, mostrar su nombre; si no, `$registrador?->coordinator?->name ?? 'N/A'`).
       - `articulador`: label "Articulador" — mismo patrón exacto que `VotersTable.php` líneas 147-158.

    Cada `TextEntry` usa `->state(fn (Voter $record): string => ...)` con la misma closure lógica (extraer a métodos privados de la clase, como ya hace `latestValidationSource()`/`nextStepGuidance()`/`missingDataSummary()`, para mantener el `infolist()` legible — ej. `resolveLiderLabel()`, `resolveCoordinadorLabel()`, `resolveArticuladorLabel()`).

    En `tests/Feature/Filament/VoterResourceTest.php`, agregar los 2 tests descritos en `<behavior>`, siguiendo el patrón exacto ya usado en el archivo (líneas 585-603: crear `Municipality`, `User` coordinador con `assignRole(UserRole::COORDINATOR->value)` + `coordinator_user_id` auto-referenciado, `User` líder con `assignRole(UserRole::LEADER->value)` + `coordinator_user_id` apuntando al coordinador, `Voter::factory()->create(['registered_by' => ...])`, y `Livewire::test(ViewVoter::class, ['record' => $voter->id])->assertSee(...)`). Para el articulador, crear un tercer `User` (área coordinador) y asignarlo vía `$coordinator->update(['area_coordinator_user_id' => $areaCoordinator->id])`.
  </action>
  <verify>
    <automated>cd "/Volumes/NAS(MAC)/Data/Herd/sigma-project" && php artisan test --filter="VoterResourceTest"</automated>
  </verify>
  <done>Ambos tests nuevos pasan; el infolist de ViewVoter muestra Líder/Coordinador/Articulador con el mismo comportamiento ambiguo (líder vs. coordinador directo) ya resuelto en VotersTable; ningún test preexistente de VoterResourceTest se rompe.</done>
</task>

</tasks>

<verification>
`php artisan test --filter="VoterResourceTest"` pasa completo (incluye los 2 tests nuevos + todos los preexistentes, sin regresiones). `vendor/bin/pint --dirty` limpio.
</verification>

<success_criteria>
- El detalle individual de un apoyo (`/admin/voters/{id}`) muestra Líder, Coordinador y Articulador con la misma lógica de resolución que el listado.
- Caso ambiguo (coordinador registrando directo) resuelto igual que en `VotersTable.php`: Líder → "N/A", Coordinador → el propio coordinador, Articulador → su `areaCoordinator`.
- Sin N+1: eager load explícito vía `resolveRecord()->loadMissing(...)`.
- Cero regresiones en `VoterResourceTest`.
</success_criteria>

<output>
After completion, create `.planning/quick/260830-nnu-agregar-lider-coordinador-articulador-al/260830-nnu-SUMMARY.md`
</output>
</output>
