---
phase: 260830-iok
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - database/migrations/2026_08_30_120000_make_email_nullable_on_users_table.php
  - tests/Feature/Auth/LoginWithoutEmailTest.php
  - app/Filament/Resources/Leaders/Schemas/LeaderForm.php
  - app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php
  - app/Filament/Resources/AreaCoordinators/Schemas/AreaCoordinatorForm.php
  - app/Filament/Resources/Users/Schemas/UserForm.php
  - tests/Feature/Filament/RequireEmailOrDocumentNumberTest.php
  - resources/views/livewire/public/register-leader.blade.php
  - resources/views/livewire/coordinator/create-leader.blade.php
  - resources/views/livewire/coordinator/edit-leader.blade.php
  - tests/Feature/RequireEmailOrDocumentNumberLivewireTest.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "Un coordinador puede crear un líder (Volt) ingresando solo cédula, sin correo, y el registro se guarda exitosamente"
    - "Un líder puede autoregistrarse por el link público (Volt) ingresando solo cédula, sin correo, tras verificar OTP, y el registro se guarda exitosamente"
    - "Un coordinador puede editar un líder existente y dejar el correo en blanco si ese líder ya tiene cédula registrada"
    - "Un admin/super_admin puede crear un Coordinador, Articulador o Usuario (Filament) con solo cédula, sin correo"
    - "Intentar crear (o editar, en el caso de líder) un registro sin correo NI cédula fallla la validación en los 7 puntos de entrada (3 Volt + 4 Filament)"
    - "Un usuario sin correo (con cédula) puede iniciar sesión normalmente con su número de cédula, usando el login dual ya existente en FortifyServiceProvider"
    - "Los formularios de apoyo (Voter) no se tocan — el correo de apoyo ya era opcional antes de este cambio"
  artifacts:
    - path: "database/migrations/2026_08_30_120000_make_email_nullable_on_users_table.php"
      provides: "users.email nullable vía ->change(), manteniendo el unique index existente (MySQL permite múltiples NULL en un índice unique)"
    - path: "app/Filament/Resources/Leaders/Schemas/LeaderForm.php"
      provides: "email/document_number condicionalmente requeridos (uno u otro) vía ->required(fn (Get $get) ...)"
    - path: "app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php"
      provides: "misma regla cruzada aplicada al form de Coordinador"
    - path: "app/Filament/Resources/AreaCoordinators/Schemas/AreaCoordinatorForm.php"
      provides: "misma regla cruzada aplicada al form de Articulador"
    - path: "app/Filament/Resources/Users/Schemas/UserForm.php"
      provides: "misma regla cruzada aplicada al form genérico de Usuario (admin)"
    - path: "resources/views/livewire/public/register-leader.blade.php"
      provides: "reglas Livewire #[Validate] cruzadas (required_without) + persistencia null-safe de email/document_number"
    - path: "resources/views/livewire/coordinator/create-leader.blade.php"
      provides: "mismo patrón aplicado a la creación de líder por parte del coordinador"
    - path: "resources/views/livewire/coordinator/edit-leader.blade.php"
      provides: "correo opcional en edición, validado contra el document_number ya persistido en el modelo (este form no tiene campo document_number)"
  key_links:
    - from: "TextInput::make('email')->required(fn (Get $get): bool => blank($get('document_number')))"
      to: "TextInput::make('document_number')->required(fn (Get $get): bool => blank($get('email')))"
      via: "closures cruzadas en los 4 Filament Schemas — cada campo se vuelve requerido solo si el otro está vacío"
      pattern: "required\\(fn \\(Get \\$get\\): bool => blank\\(\\$get\\("
    - from: "#[Validate('nullable|email|unique:users,email|required_without:document_number')]"
      to: "#[Validate('nullable|string|max:50|unique:users,document_number|required_without:email')]"
      via: "reglas Livewire cruzadas en register-leader.blade.php y create-leader.blade.php"
      pattern: "required_without:"
    - from: "User::create([...]) / \\$this->leader->update([...])"
      to: "blank($this->email) ? null : $this->email (y lo mismo para document_number)"
      via: "conversión explícita de string vacío a NULL antes de persistir — evita que el unique index rechace un segundo registro con '' en vez de NULL"
      pattern: "blank\\(\\$this->email\\) \\? null"
    - from: "FortifyServiceProvider::configureAuthentication()"
      to: "User::where('document_number', $login)->first()"
      via: "fallback ya existente (sin cambios) — cubierto por el nuevo test LoginWithoutEmailTest"
      pattern: "document_number.*first\\(\\)"
---

<objective>
El cliente pidió que el correo deje de ser obligatorio para líderes y coordinadores, exigiendo en su lugar al menos correo O cédula. El login dual (correo o cédula) ya funciona vía `FortifyServiceProvider::authenticateUsing()` — no requiere cambios. El problema es puramente de validación/esquema: `users.email` es `NOT NULL` a nivel de columna, y el campo `email` es `->required()`/`required` en 7 puntos de código (3 Livewire Volt + 4 Filament Schemas) para líder/coordinador/articulador/usuario admin. `document_number` también está marcado `->required()` en esos mismos 7 puntos (salvo `edit-leader.blade.php`, que no tiene ese campo), pese a ya ser `nullable()->unique()` en la DB desde 2025-11-03 — así que hoy de facto AMBOS son obligatorios, cuando el pedido es que sea "uno u otro".

Purpose: Permitir que líderes, coordinadores, articuladores y usuarios admin se registren/editen con solo cédula (sin correo) o solo correo (sin cédula), sin dejar nunca a nadie sin forma de loguearse.
Output: 1 migración (`users.email` nullable), 4 Filament Schemas con regla cruzada, 3 vistas Livewire Volt con regla cruzada, 3 archivos Pest nuevos.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@app/Providers/FortifyServiceProvider.php
@database/migrations/0001_01_01_000000_create_users_table.php
@database/migrations/2025_11_03_131419_add_profile_fields_to_users_table.php
@app/Filament/Resources/Leaders/Schemas/LeaderForm.php
@app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php
@app/Filament/Resources/AreaCoordinators/Schemas/AreaCoordinatorForm.php
@app/Filament/Resources/Users/Schemas/UserForm.php
@resources/views/livewire/public/register-leader.blade.php
@resources/views/livewire/coordinator/create-leader.blade.php
@resources/views/livewire/coordinator/edit-leader.blade.php
</context>

<interfaces>
<!-- Verificado 2026-08-30 directamente contra el código actual — no requiere exploración adicional. -->

**`FortifyServiceProvider::configureAuthentication()`** (líneas 51-67, YA EXISTENTE, NO TOCAR): intenta `User::where('email', $login)->first()`, y si no encuentra nada, cae a `User::where('document_number', $login)->first()`. El login dual ya funciona — este plan solo necesita un test que lo confirme para un usuario sin correo.

**`document_number` ya es `nullable()->unique()`** desde `database/migrations/2025_11_03_131419_add_profile_fields_to_users_table.php:17` — NO requiere migración. Solo `email` necesita la migración (columna `NOT NULL` + `unique()` desde la migración base).

**Patrón `->change()` sin doctrine/dbal ya establecido en este proyecto** (confirmado: `doctrine/dbal` NO está instalado, pero 7 migraciones existentes ya usan `->change()` exitosamente, p.ej. `database/migrations/2026_07_30_000001_make_validated_by_nullable_on_validation_histories_table.php`). Usar el mismo patrón — NO añadir `doctrine/dbal` como dependencia.

**Patrón Filament de "required condicional" ya usado en estos mismos 4 Schemas** (p.ej. `LeaderForm.php:175`, password): `->required(fn (string $operation): bool => $operation === 'create')`. Este plan usa el mismo patrón de closure pero cruzando dos campos: `->required(fn (Get $get): bool => blank($get('otro_campo')))`.

**Gotcha crítico de persistencia (blank string vs NULL):** los 3 componentes Volt declaran `public string $email = '';` / `public string $document_number = '';` (tipado NO nullable). Si el usuario deja el campo vacío, Livewire mantiene `''` (string vacío), NO `null`. Un índice `unique` en MySQL permite múltiples `NULL`, pero **NO permite múltiples `''`** — el segundo líder sin correo fallaría con un error de unique constraint en `''`. Por eso, al persistir (`User::create()`/`update()`), hay que convertir explícitamente `'' -> null` con `blank($this->email) ? null : $this->email`. Filament's `TextInput` tiene el mismo problema — se soluciona con `->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)`.

**Namespaces de páginas Filament para tests** (confirmados vía `find`):
```
App\Filament\Resources\Leaders\Pages\CreateLeader
App\Filament\Resources\Coordinators\Pages\CreateCoordinator
App\Filament\Resources\AreaCoordinators\Pages\CreateAreaCoordinator
App\Filament\Resources\Users\Pages\CreateUser
```

**`UserFactory` default password**: `'password' => static::$password ??= 'password'` (texto plano `'password'`, cast a hash por el modelo) — usar `'password' => 'password'` al hacer login en tests, igual que `tests/Feature/Auth/LoginRedirectByRoleTest.php`.
</interfaces>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Migración — users.email nullable + test de login por cédula sin correo</name>
  <files>database/migrations/2026_08_30_120000_make_email_nullable_on_users_table.php, tests/Feature/Auth/LoginWithoutEmailTest.php</files>
  <behavior>
    - `users.email` acepta `NULL` sin romper el índice unique existente.
    - Un `User` puede crearse con `email = null` y `document_number` con valor.
    - Ese mismo usuario puede iniciar sesión usando su `document_number` como "correo o cédula" en `login.store` (login dual ya existente, sin cambios).
  </behavior>
  <action>
    Crear `database/migrations/2026_08_30_120000_make_email_nullable_on_users_table.php` siguiendo EXACTAMENTE el patrón ya usado en `database/migrations/2026_07_30_000001_make_validated_by_nullable_on_validation_histories_table.php` (`->change()` sin doctrine/dbal, ya confirmado funcional en este proyecto):

    ```php
    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->nullable()->change();
            });
        }

        public function down(): void
        {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->nullable(false)->change();
            });
        }
    };
    ```

    NO restablecer `->unique()` en el `change()` — el índice unique ya existe y `MODIFY COLUMN` en MySQL no lo toca; volver a declarar `->unique()` intentaría crear un índice duplicado.

    Ejecutar `php artisan migrate`.

    Crear `tests/Feature/Auth/LoginWithoutEmailTest.php` (mismo estilo que `tests/Feature/Auth/LoginRedirectByRoleTest.php`, sin `declare(strict_types=1)` para mantener consistencia con ese archivo hermano):

    ```php
    <?php

    use App\Models\User;
    use Spatie\Permission\Models\Role;

    beforeEach(function () {
        Role::findOrCreate('leader');
    });

    test('un usuario puede crearse sin correo electrónico gracias a que users.email ahora es nullable', function () {
        $leader = User::factory()->create([
            'email' => null,
            'document_number' => '1234567890',
        ]);

        expect($leader->email)->toBeNull();
        $this->assertDatabaseHas('users', ['id' => $leader->id, 'email' => null]);
    });

    test('un usuario sin correo puede iniciar sesión con su número de cédula', function () {
        $leader = User::factory()->withoutTwoFactor()->create([
            'email' => null,
            'document_number' => '1234567890',
        ]);
        $leader->assignRole('leader');

        $response = $this->post(route('login.store'), [
            'email' => '1234567890',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('leader.dashboard', absolute: false));
        $this->assertAuthenticatedAs($leader);
    });
    ```
  </action>
  <verify>
    <automated>php artisan test --filter=LoginWithoutEmailTest</automated>
  </verify>
  <done>Migración aplicada sin error; ambos tests pasan; `php artisan migrate:rollback --step=1` seguido de `php artisan migrate` no rompe nada (columna vuelve a NOT NULL y luego a nullable limpiamente).</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Regla cruzada email/document_number en los 4 Filament Schemas (Leader/Coordinator/AreaCoordinator/User)</name>
  <files>app/Filament/Resources/Leaders/Schemas/LeaderForm.php, app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php, app/Filament/Resources/AreaCoordinators/Schemas/AreaCoordinatorForm.php, app/Filament/Resources/Users/Schemas/UserForm.php, tests/Feature/Filament/RequireEmailOrDocumentNumberTest.php</files>
  <behavior>
    - En cada uno de los 4 forms: crear un registro con SOLO `document_number` (sin `email`) tiene éxito.
    - Crear un registro con SOLO `email` (sin `document_number`) tiene éxito.
    - Crear un registro SIN `email` NI `document_number` falla la validación en ambos campos.
    - Un `email`/`document_number` dejado en blanco se persiste como `NULL`, no como `''` (verificado indirectamente: dos registros consecutivos sin correo no chocan por unique constraint).
  </behavior>
  <action>
    Aplicar el MISMO cambio mecánico a los 4 archivos. En cada uno, localizar el `TextInput::make('email')` y el `TextInput::make('document_number')` dentro de la sección "Información personal"/"Información Personal":

    1. Quitar `->required()` de ambos campos.
    2. Añadir a `email`: `->live(onBlur: true)`, `->required(fn (Get $get): bool => blank($get('document_number')))`, y `->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)`.
    3. Añadir a `document_number`: `->required(fn (Get $get): bool => blank($get('email')))` y `->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)` (si `document_number` ya tiene `->live(onBlur: true)` y `->afterStateUpdated(...)` — como en Leader/Coordinator/AreaCoordinator — dejarlos intactos, solo agregar las dos líneas nuevas antes del `->afterStateUpdated`).
    4. Añadir un `->helperText('Debes ingresar al menos el correo o el número de documento.')` al campo `email` en los 4 forms (reemplaza cualquier helperText previo si lo hubiera — ninguno de los 4 tiene uno actualmente en `email`).

    `Get` ya está importado en los 4 archivos (usado en otras closures) — no se requieren nuevos `use`.

    En `LeaderForm.php`, `CoordinatorForm.php` y `AreaCoordinatorForm.php` el resultado para `email` queda:
    ```php
    TextInput::make('email')
        ->label('Correo electrónico')
        ->email()
        ->unique(ignoreRecord: true)
        ->maxLength(255)
        ->live(onBlur: true)
        ->required(fn (Get $get): bool => blank($get('document_number')))
        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
        ->helperText('Debes ingresar al menos el correo o el número de documento.'),
    ```
    y para `document_number`:
    ```php
    TextInput::make('document_number')
        ->label('Número de documento')
        ->unique(ignoreRecord: true)
        ->maxLength(50)
        ->live(onBlur: true)
        ->required(fn (Get $get): bool => blank($get('email')))
        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
        ->afterStateUpdated(function ($state, Set $set): void {
            // ... cuerpo existente sin cambios
        }),
    ```

    En `UserForm.php` (único de los 4 sin `live()`/`afterStateUpdated()` previos en estos campos, y `maxLength(255)` en `document_number` en vez de 50 — respetar el `maxLength` existente de cada archivo, no unificarlo):
    ```php
    TextInput::make('email')
        ->label('Correo Electrónico')
        ->email()
        ->unique(ignoreRecord: true)
        ->maxLength(255)
        ->live(onBlur: true)
        ->required(fn (Get $get): bool => blank($get('document_number')))
        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
        ->helperText('Debes ingresar al menos el correo o el número de documento.'),

    TextInput::make('document_number')
        ->label('Número de Documento')
        ->unique(ignoreRecord: true)
        ->maxLength(255)
        ->live(onBlur: true)
        ->required(fn (Get $get): bool => blank($get('email')))
        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null),
    ```

    Crear `tests/Feature/Filament/RequireEmailOrDocumentNumberTest.php` (con `declare(strict_types=1);`, mismo estilo que `LeaderResourceCampaignTest.php`/`CoordinatorResourceCampaignTest.php`/`AreaCoordinatorResourceCampaignTest.php` — leerlos si hace falta el setup exacto de `RoleSeeder`/`CampaignContext`/super_admin). Cubrir, para CADA uno de los 4 resources (Leader/Coordinator/AreaCoordinator/User):
    - Crear con `document_number` presente y `email` ausente/vacío → `assertHasNoFormErrors()`, y el registro creado tiene `email` NULL en base de datos.
    - Crear con `email` presente y `document_number` ausente/vacío → `assertHasNoFormErrors()`.
    - Crear sin `email` NI `document_number` → `assertHasFormErrors(['email', 'document_number'])`.

    Para `Leader`, crear primero un `coordinator_user_id` válido (`User::factory()->create()` + `assignRole(UserRole::COORDINATOR->value)`) y usarlo en el `fillForm`. Para `Coordinator`/`AreaCoordinator`, incluir un `municipality_id` válido. Para `User` (CreateUser), incluir `password`/`passwordConfirmation` iguales y `phone`. No reutilizar las funciones globales `leaderFormData()`/`coordinatorFormData()`/`areaCoordinatorFormData()` ya definidas en otros archivos de test (declaradas como funciones globales — para que este archivo siga siendo ejecutable de forma aislada con `--filter`, definir los arrays de datos inline en cada test, no como función global reutilizable).
  </action>
  <verify>
    <automated>php artisan test --filter=RequireEmailOrDocumentNumberTest</automated>
  </verify>
  <done>Los 12 casos (4 resources × 3 escenarios) pasan; `php artisan test --filter=LeaderResourceCampaignTest --filter=CoordinatorResourceCampaignTest --filter=AreaCoordinatorResourceCampaignTest` (regresión, ambos con email+cédula presentes) sigue pasando sin cambios.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Regla cruzada email/document_number en los 3 componentes Livewire Volt (register-leader, create-leader, edit-leader)</name>
  <files>resources/views/livewire/public/register-leader.blade.php, resources/views/livewire/coordinator/create-leader.blade.php, resources/views/livewire/coordinator/edit-leader.blade.php, tests/Feature/RequireEmailOrDocumentNumberLivewireTest.php</files>
  <behavior>
    - `public.register-leader`: completar el flujo OTP y guardar con solo `document_number` (sin `email`) crea el líder exitosamente; guardar sin `email` NI `document_number` falla la validación en ambos campos.
    - `coordinator.create-leader`: mismo comportamiento.
    - `coordinator.edit-leader`: editar un líder que YA tiene `document_number` y dejar `email` en blanco guarda exitosamente (este form no tiene campo `document_number`, así que la regla cruzada se valida contra `$this->leader->document_number` ya persistido, no contra un campo del formulario); editar un líder SIN `document_number` y dejar `email` en blanco falla.
  </behavior>
  <action>
    En `resources/views/livewire/public/register-leader.blade.php` y `resources/views/livewire/coordinator/create-leader.blade.php` (mismo cambio en ambos archivos):

    1. Cambiar:
       ```php
       #[Validate('required|email|unique:users,email')]
       public string $email = '';
       ```
       a:
       ```php
       #[Validate('nullable|email|unique:users,email|required_without:document_number')]
       public string $email = '';
       ```
    2. Cambiar:
       ```php
       #[Validate('required|string|max:50|unique:users,document_number')]
       public string $document_number = '';
       ```
       a:
       ```php
       #[Validate('nullable|string|max:50|unique:users,document_number|required_without:email')]
       public string $document_number = '';
       ```
    3. En el método `save()`, dentro del array pasado a `User::create([...])`, cambiar `'email' => $this->email,` por `'email' => blank($this->email) ? null : $this->email,` y `'document_number' => $this->document_number,` por `'document_number' => blank($this->document_number) ? null : $this->document_number,`.

    En `resources/views/livewire/coordinator/edit-leader.blade.php` (este form NO tiene campo `document_number`, así que la regla cruzada se valida contra el modelo ya persistido, no contra otro campo del form):

    1. Cambiar:
       ```php
       #[Validate('required|email|unique:users,email')]
       public string $email = '';
       ```
       a:
       ```php
       #[Validate('nullable|email|unique:users,email')]
       public string $email = '';
       ```
    2. En `save()`, cambiar:
       ```php
       $this->validate([
           'email' => 'required|email|unique:users,email,' . $this->leader->id,
       ]);
       ```
       a:
       ```php
       $this->validate([
           'email' => 'nullable|email|unique:users,email,' . $this->leader->id,
       ]);

       if (blank($this->email) && blank($this->leader->document_number)) {
           $this->addError('email', 'Debes ingresar un correo electrónico; este líder no tiene cédula registrada.');

           return;
       }
       ```
    3. En el array pasado a `$this->leader->update([...])`, cambiar `'email' => $this->email,` por `'email' => blank($this->email) ? null : $this->email,`.

    Seguir CLAUDE.md: `use` statements explícitos (ya cubiertos, sin nuevos imports), llaves siempre, textos visibles al usuario en español.

    Crear `tests/Feature/RequireEmailOrDocumentNumberLivewireTest.php` (sin `declare(strict_types=1)`, mismo estilo que `tests/Feature/PublicLeaderRegistrationTest.php` y `tests/Feature/Coordinator/CreateLeaderRegistraduriaLookupTest.php` — leerlos para el setup exacto de invitación/coordinador/OTP si hace falta). Cubrir:
    - `public.register-leader`: flujo completo (`sendOtp` → `verifyOtp` con el código real de `OtpVerification::query()->latest()->first()->code`, igual que `PublicLeaderRegistrationTest.php`) guardando con `document_number` presente y `email` vacío → `assertHasNoErrors()`, y el `User` creado tiene `email` NULL.
    - `public.register-leader`: mismo flujo OTP pero con `email` Y `document_number` vacíos → `assertHasErrors(['email', 'document_number'])`, sin crear ningún `User`.
    - `coordinator.create-leader`: mismos dos casos (coordinador autenticado vía `actingAs`).
    - `coordinator.edit-leader`: un líder existente CON `document_number` — `Volt::test('coordinator.edit-leader', ['leader' => $leader])->set('email', '')->call('save')->assertHasNoErrors()`, y el líder queda con `email` NULL en base de datos.
    - `coordinator.edit-leader`: un líder existente SIN `document_number` (`document_number` NULL) — dejar `email` en blanco y guardar → `assertHasErrors(['email'])`.
  </action>
  <verify>
    <automated>php artisan test --filter=RequireEmailOrDocumentNumberLivewireTest</automated>
  </verify>
  <done>Los 6 casos pasan; regresión: `php artisan test --filter=PublicLeaderRegistrationTest --filter=CreateLeaderIdentityLookupTest --filter=CreateLeaderRegistraduriaLookupTest` sigue pasando (todos con email+cédula presentes, sin cambios de comportamiento para el caso "ambos presentes").</done>
</task>

</tasks>

<verification>
```bash
php artisan test --filter=LoginWithoutEmailTest
php artisan test --filter=RequireEmailOrDocumentNumberTest
php artisan test --filter=RequireEmailOrDocumentNumberLivewireTest
php artisan test --filter=LeaderResourceCampaignTest
php artisan test --filter=CoordinatorResourceCampaignTest
php artisan test --filter=AreaCoordinatorResourceCampaignTest
php artisan test --filter=PublicLeaderRegistrationTest
php artisan test --filter=CreateLeaderIdentityLookupTest
vendor/bin/pint --dirty
```

No se agrega ninguna dependencia nueva (doctrine/dbal NO se instala — se reutiliza el patrón `->change()` ya probado en 7 migraciones existentes del proyecto). No se toca `FortifyServiceProvider` (el login dual ya funciona). No se toca ningún formulario de `Voter`/apoyo (su correo ya era opcional). Por la preferencia estándar del usuario, se recomienda una verificación real en navegador (crear un líder solo con cédula desde ambos formularios Volt y desde el panel Filament, y loguearse con esa cédula) antes de considerar esto desplegado, aunque la cobertura Pest es exhaustiva.
</verification>

<success_criteria>
- `users.email` es `nullable` en base de datos, manteniendo su índice `unique`.
- Los 4 Filament Schemas (Leader/Coordinator/AreaCoordinator/User) y los 3 componentes Volt (register-leader/create-leader/edit-leader) exigen al menos uno de `email`/`document_number`, nunca ambos obligatorios ni ambos opcionales sin control.
- Un `email`/`document_number` dejado en blanco se persiste como `NULL`, nunca como `''` (evita colisiones falsas en el índice unique).
- El login por cédula para un usuario sin correo funciona sin ningún cambio en `FortifyServiceProvider` (comportamiento preexistente, ahora cubierto por test).
- Todos los tests nuevos y de regresión pasan; `vendor/bin/pint --dirty` limpio.
</success_criteria>

<output>
After completion, create `.planning/quick/260830-iok-quitar-obligatoriedad-del-correo-para-li/260830-iok-SUMMARY.md`
</output>
