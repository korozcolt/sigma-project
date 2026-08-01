---
phase: quick
plan: 260801-hvd
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Models/Invitation.php
  - app/Services/InvitationService.php
  - routes/web.php
  - resources/views/livewire/public/register-leader.blade.php
  - resources/views/livewire/coordinator/leaders.blade.php
  - resources/views/livewire/coordinator/leader-add-voter.blade.php
  - resources/views/livewire/coordinator/leader-voters.blade.php
  - tests/Feature/InvitationServiceLeaderLinkTest.php
  - tests/Feature/PublicLeaderRegistrationTest.php
  - tests/Feature/Coordinator/GenerateLeaderInvitationLinkTest.php
  - tests/Feature/Coordinator/AddVoterFromLeaderDetailTest.php
  - tests/Feature/Coordinator/LeadersVoterCountDisplayTest.php
autonomous: false
requirements: []

must_haves:
  truths:
    - "Un coordinador puede generar, desde su propio panel (coordinator/leaders), un enlace público de auto-registro de líder, sin pasar por el admin/Filament."
    - "Al abrir ese enlace (sin sesión iniciada), una persona puede crear su propia cuenta de líder completando el mismo nivel de verificación que el formulario manual: OTP por SMS + cruce de cédula contra Registraduría/censo."
    - "El líder auto-registrado queda activo de inmediato, asignado al mismo coordinador y a las mismas campañas que si lo hubiera creado el coordinador manualmente."
    - "Un enlace de auto-registro de líder no puede reutilizarse una segunda vez, ni sirve para registrar un apoyo en /registro/{token} (y un enlace de apoyo tampoco sirve en la ruta de auto-registro de líder)."
    - "Un coordinador puede agregar un apoyo directamente desde el detalle de un líder (coordinator/leaders/{leader}/voters), y ese apoyo queda registrado a nombre del líder (registered_by = leader.id), no del coordinador."
    - "El listado de líderes (coordinator/leaders) sigue mostrando, para cada líder, el conteo correcto de apoyos registrados — sin regresión tras los cambios anteriores."
  artifacts:
    - path: "app/Services/InvitationService.php"
      provides: "createLeaderRegistrationLink(), validateLeaderInvitation(), markLeaderInvitationAccepted() — ciclo de vida de las invitaciones de auto-registro de líder"
    - path: "app/Models/Invitation.php"
      provides: "getLeaderRegistrationUrl() — URL pública del enlace de auto-registro de líder"
    - path: "resources/views/livewire/public/register-leader.blade.php"
      provides: "Formulario público (Volt) de auto-registro de líder con OTP + verificación de cédula"
    - path: "resources/views/livewire/coordinator/leader-add-voter.blade.php"
      provides: "Página (Volt) para que el coordinador registre un apoyo a nombre de un líder específico"
    - path: "resources/views/livewire/coordinator/leaders.blade.php"
      provides: "Botón 'Generar enlace de registro' + acción Livewire que crea la invitación"
    - path: "resources/views/livewire/coordinator/leader-voters.blade.php"
      provides: "Botón 'Agregar Apoyo' enlazando a la nueva página de registro de apoyo por líder"
    - path: "routes/web.php"
      provides: "Rutas public.leader-registration (GET/POST vía Volt) y coordinator.leaders.voters.create"
  key_links:
    - from: "resources/views/livewire/coordinator/leaders.blade.php"
      to: "App\\Services\\InvitationService::createLeaderRegistrationLink"
      via: "acción Livewire wire:click"
      pattern: "createLeaderRegistrationLink"
    - from: "resources/views/livewire/public/register-leader.blade.php"
      to: "App\\Services\\InvitationService::validateLeaderInvitation"
      via: "mount(string $token)"
      pattern: "validateLeaderInvitation"
    - from: "resources/views/livewire/public/register-leader.blade.php"
      to: "App\\Models\\User::create + assignRole(LEADER) + InvitationService::markLeaderInvitationAccepted"
      via: "save()"
      pattern: "markLeaderInvitationAccepted"
    - from: "resources/views/livewire/coordinator/leader-add-voter.blade.php"
      to: "App\\Models\\Voter::create"
      via: "save() con registered_by = $this->leader->id"
      pattern: "registered_by.*leader->id"
    - from: "resources/views/livewire/coordinator/leader-voters.blade.php"
      to: "route('coordinator.leaders.voters.create', $leader)"
      via: "botón 'Agregar Apoyo'"
      pattern: "leaders.voters.create"
---

<objective>
Implementar 2 flujos nuevos sobre la jerarquía Coordinador → Líder → Apoyo, y verificar que un tercero ya existente sigue funcionando:

1. **Enlace de auto-registro de líder**: un coordinador genera, desde su propio panel, un enlace público (token) que le permite a un futuro líder crear su propia cuenta (con la misma verificación OTP + cédula que el formulario manual `coordinator/leaders/create`), sin que el coordinador tenga que llenar el formulario por él.
2. **Agregar apoyo desde el detalle de un líder**: dentro de `coordinator/leaders/{leader}/voters` (vista, hoy solo de lectura), el coordinador puede registrar un apoyo a nombre de ese líder específico (`registered_by = leader.id`), reutilizando la misma validación/cruce de censo que `leader/register-voter.blade.php`.
3. **Conteo de apoyos por líder** (ya implementado en `coordinator/leaders.blade.php` vía `voters_count` y en el Filament `LeadersTable` vía `registered_voters_count`): no requiere código nuevo, solo un test de regresión que confirme que sigue funcionando después de los cambios de este plan.

Purpose: hoy la única forma de crear un líder es que el coordinador llene el formulario a mano con los datos del líder (incluyendo su contraseña), y la única forma de agregar un apoyo a un líder es que el líder mismo inicie sesión. Este plan le da al coordinador un flujo de autoservicio para ambos casos, reutilizando el sistema de `Invitation` ya existente (hoy solo usado para invitar apoyos) y extendiéndolo de forma explícita para el caso "invitación crea un líder".

Output:
- Un botón "Generar enlace de registro" en `coordinator/leaders.blade.php` que crea una `Invitation` con `target_role = 'LEADER'` y muestra el enlace copiable.
- Una página pública Volt (`registro-lider/{token}`) donde esa persona completa OTP + cédula y queda creada como `User` con rol `leader`, mismo coordinador y mismas campañas que el flujo manual. La invitación queda marcada `accepted` (no reutilizable).
- Un botón "Agregar Apoyo" en `coordinator/leaders/{leader}/voters` que lleva a una página Volt nueva (`coordinator/leaders/{leader}/voters/create`) con el mismo formulario/validación que `leader/register-voter.blade.php`, pero que registra el apoyo a nombre del líder de la pantalla.
- Un test de regresión confirmando que el conteo de apoyos por líder (feature #3, ya implementado) sigue correcto.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/quick/260801-hvd-enlaces-de-auto-registro-para-que-coordi/260801-hvd-CONTEXT.md
@app/Models/Invitation.php
@app/Services/InvitationService.php
@app/Http/Controllers/PublicVoterRegistrationController.php
@app/Http/Middleware/RequireInvitationForRegistration.php
@app/Services/OtpVerificationService.php
@app/Services/IdentityLookupService.php
@app/Services/VoterValidationService.php
@resources/views/livewire/coordinator/create-leader.blade.php
@resources/views/livewire/coordinator/leaders.blade.php
@resources/views/livewire/coordinator/leader-voters.blade.php
@resources/views/livewire/leader/register-voter.blade.php
@resources/views/public/voter-registration.blade.php
@routes/web.php
@tests/Feature/CreateLeaderOtpTest.php
@tests/Feature/PublicVoterRegistrationLinkTest.php
</context>

<interfaces>
<!-- Firmas/columnas exactas que el executor necesita. No hace falta explorar el codebase, usar directamente. -->

From `app/Models/Invitation.php` (columnas fillable + comportamiento de `boot()`):
```php
protected $fillable = [
    'token', 'invited_by_user_id', 'invited_email', 'invited_name', 'target_role',
    'campaign_id', 'municipality_id', 'parent_leader_id', 'leader_user_id',
    'coordinator_user_id', 'status', 'expires_at', 'notes',
];
// boot(): si target_role viene vacío, se autocalcula (leader_user_id ? 'LEADER' : 'COORDINATOR').
// Pasar target_role EXPLÍCITAMENTE evita ese autocálculo.
// token: Str::random(60) si viene vacío. status: 'pending' si viene vacío.
// invited_email: "registro+{token}@sigma.local" si viene vacío (no se usa para nada real).
public function isValid(): bool { return $this->status === 'pending' && !$this->isExpired(); }
public function coordinator(): BelongsTo; // users.id
public function leader(): BelongsTo;      // users.id
// Columnas existentes NO usadas hoy por ningún flujo real (perfectas para el ciclo de vida de esta invitación):
// accepted_at (timestamp nullable), registered_user_id (FK nullable a users, nullOnDelete)
```

From `app/Services/InvitationService.php` (métodos existentes, NO tocar su comportamiento):
```php
public function validateInvitation(string $token): ?Invitation; // status=pending && !expired
public function hasRegistrationAssignee(Invitation $invitation): bool; // (bool) leader_user_id
public function getRegistrationAssigneeUserId(Invitation $invitation): int;
```

From `app/Services/OtpVerificationService.php`:
```php
public function generate(string $phone, Campaign $campaign): OtpVerification; // envía SMS real via HablameSmsService
public function verify(string $phone, Campaign $campaign, string $code): bool;
```

From `app/Services/IdentityLookupService.php`:
```php
public function findByDocumentNumber(string $documentNumber): ?NationalIdentityRecord; // nombre1/nombre2/apellido1/apellido2
```

From `app/Services/VoterValidationService.php`:
```php
public function documentExistsInCensus(int $campaignId, string $documentNumber): bool;
```

From `database/factories/InvitationFactory.php` (ya soporta `target_role`, reusar en tests):
```php
Invitation::factory()->create(['coordinator_user_id' => $coordinator->id, 'target_role' => 'LEADER', 'leader_user_id' => null, ...]);
```

Config de test para no enviar SMS real (usado en `tests/Feature/CreateLeaderOtpTest.php`):
```php
Config::set('services.hablame.sandbox_mode', true);
```
</interfaces>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Extender InvitationService + Invitation con el ciclo de vida de "invitación de líder"</name>
  <files>app/Services/InvitationService.php, app/Models/Invitation.php, tests/Feature/InvitationServiceLeaderLinkTest.php</files>
  <behavior>
    - `createLeaderRegistrationLink(User $coordinator): Invitation` crea una Invitation con `target_role = 'LEADER'`, `coordinator_user_id = $coordinator->id`, `leader_user_id = null`, `status = 'pending'`, `expires_at = now()->addDays(7)`, `invited_by_user_id = $coordinator->id`. Token generado automáticamente por el model (60 chars, único).
    - `validateLeaderInvitation(string $token): ?Invitation` reutiliza `validateInvitation()` (pending + no expirada) y ADEMÁS exige `target_role === 'LEADER'` y `leader_user_id` nulo (para que un token de invitación de apoyo —siempre con `leader_user_id` seteado— nunca pase esta validación). Devuelve `null` si cualquiera de las condiciones falla.
    - `markLeaderInvitationAccepted(Invitation $invitation, User $leader): void` actualiza `status = 'accepted'`, `accepted_at = now()`, `registered_user_id = $leader->id`. Tras esto, `validateLeaderInvitation()` y `validateInvitation()` deben devolver `null` para ese mismo token (porque `status` ya no es `'pending'`) — así el enlace queda no-reutilizable.
    - `Invitation::getLeaderRegistrationUrl(): string` devuelve `route('public.leader-registration', ['token' => $this->token])` (nuevo método, análogo al ya existente `getRegistrationUrl()` que apunta a `public.voters.register` — NO modificar `getRegistrationUrl()`).
  </behavior>
  <action>
    Añadir los 3 métodos nuevos a `InvitationService` (mismo estilo que los métodos existentes — sin `handle()`/`execute()`, esta clase ya usa métodos de dominio directos) y el método nuevo a `Invitation`. No crear ninguna migración: `target_role`, `coordinator_user_id`, `leader_user_id`, `status`, `accepted_at`, `registered_user_id`, `expires_at` ya existen en la tabla `invitations`.
  </action>
  <verify>
    <automated>php artisan test --filter=InvitationServiceLeaderLinkTest</automated>
  </verify>
  <done>Los 3 métodos existen y pasan tests: creación con target_role=LEADER, validación rechaza tokens expirados/de apoyo/ya aceptados, marcar-aceptada deja el token no reutilizable, y getLeaderRegistrationUrl() genera una URL con el token.</done>
</task>

<task type="auto">
  <name>Task 2: Botón "Generar enlace de registro" en el listado de líderes del coordinador</name>
  <files>resources/views/livewire/coordinator/leaders.blade.php, tests/Feature/Coordinator/GenerateLeaderInvitationLinkTest.php</files>
  <action>
    En la clase Volt de `coordinator/leaders.blade.php`, añadir:
    - Propiedades públicas `public bool $showLeaderInvitationModal = false;` y `public ?string $leaderInvitationUrl = null;`.
    - Método `generateLeaderInvitationLink(): void` que hace `abort_unless(auth()->user()->hasRole(UserRole::COORDINATOR->value), 403)`, llama a `app(InvitationService::class)->createLeaderRegistrationLink(auth()->user())`, guarda `$this->leaderInvitationUrl = $invitation->getLeaderRegistrationUrl()` y `$this->showLeaderInvitationModal = true`.

    En la vista, junto al botón "Agregar Líder" (línea ~101), añadir un botón secundario `<flux:button variant="outline" wire:click="generateLeaderInvitationLink" icon="link">Generar enlace de registro</flux:button>`. Añadir un `<flux:modal wire:model="showLeaderInvitationModal">` con el input readonly mostrando `$leaderInvitationUrl` y un botón "Copiar" implementado con Alpine (`x-data="{ copied: false }"` + `navigator.clipboard.writeText(...)`, sin dependencias nuevas — Flux Free no trae un componente de copiado fuera de tabla). Incluir un texto de ayuda: "Comparte este enlace con la persona que quieres invitar como líder. Expira en 7 días y solo puede usarse una vez."

    No mostrar este botón/modal a roles distintos de coordinator si la vista ya distingue roles en otras partes (verificar contra el `@if(!auth()->user()->hasRole(...COORDINATOR...))` existente en el archivo — este botón debe comportarse igual que "Agregar Líder", visible para todos los roles que acceden a esta pantalla, ya que la ruta ya está protegida por `role:coordinator,admin_campaign,super_admin`).
  </action>
  <verify>
    <automated>php artisan test --filter=GenerateLeaderInvitationLinkTest</automated>
  </verify>
  <done>Un coordinador autenticado que hace click en "Generar enlace de registro" ve el modal con una URL copiable; en base de datos existe una nueva Invitation con target_role=LEADER, coordinator_user_id=coordinador, leader_user_id=null, status=pending.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Página pública de auto-registro de líder (OTP + cédula)</name>
  <files>routes/web.php, resources/views/livewire/public/register-leader.blade.php</files>
  <behavior>
    - Visitar `registro-lider/{token}` con un token de invitación LEADER válido muestra el formulario con el nombre del coordinador que invita.
    - Visitar con un token inexistente, expirado, ya aceptado, o de tipo apoyo (con `leader_user_id` seteado) redirige a `home` con `session('error')` y NO renderiza el formulario.
    - Completar el formulario SIN verificar el OTP y llamar a `save()` agrega un error en `otp_code` y NO crea ningún `User`.
    - Completar el formulario CON OTP verificado (mismo flujo que `sendOtp()`/`verifyOtp()` de `coordinator/create-leader.blade.php`) y `save()` crea un `User` con: `name`, `email`, `password` (hasheada), `phone`, `document_number`, `municipality_id = coordinador->municipality_id`, `coordinator_user_id = coordinador->id`, `neighborhood_id`, `email_verified_at = now()`; le asigna el rol `UserRole::LEADER`; lo adjunta a las mismas campañas del coordinador (`$coordinator->campaigns()->pluck('campaigns.id')`); y marca la invitación como aceptada vía `InvitationService::markLeaderInvitationAccepted()`.
  </behavior>
  <action>
    1. En `routes/web.php`, añadir junto a las rutas públicas de registro de apoyos (cerca de `registro/{token}`):
       ```php
       Volt::route('registro-lider/{token}', 'public.register-leader')
           ->name('public.leader-registration')
           ->middleware(['invitation.required']);
       ```
       (El middleware `invitation.required` ya valida genéricamente pending+no-expirada; la validación específica de "es una invitación de tipo LEADER" se hace dentro del componente Volt, en `mount()`, usando `InvitationService::validateLeaderInvitation()` — el middleware por sí solo NO basta para rechazar un token de apoyo reutilizado en esta ruta.)

    2. Crear `resources/views/livewire/public/register-leader.blade.php` como componente Volt clase-based, con `layout('components.layouts::public', ['title' => 'Registro de líder']);`. Mount recibe `string $token`; si `InvitationService::validateLeaderInvitation($token)` devuelve null, hacer `session()->flash('error', 'El enlace de registro no es válido, ya expiró o ya fue utilizado.'); $this->redirect(route('home')); return;`. Si es válido, guardar la invitación en una propiedad pública `public Invitation $invitation;` (Livewire serializa/rehidrata modelos Eloquent en propiedades públicas de forma nativa, mismo patrón que `public User $leader` en `leader-voters.blade.php`).

    3. Reproducir el mismo set de campos/validación/lógica que `coordinator/create-leader.blade.php` (name, email, password, phone, document_number, neighborhood_id, otpSent/otpVerified/otp_code, registraduriaVerified/censusNotFoundWarning/nameLocked, `updatedDocumentNumber()`, `sendOtp()`, `verifyOtp()`, `getNeighborhoodsProperty()`), con estas diferencias:
       - NO hay selector de coordinador (fijo: `$this->invitation->coordinator`).
       - `resolveActiveCampaign()` usa `$this->invitation->coordinator->campaigns()->first()`.
       - `save()` valida OTP verificado primero (igual que create-leader), crea el `User` como se describe en `<behavior>`, y al final llama a `app(InvitationService::class)->markLeaderInvitationAccepted($this->invitation, $leader)`. Tras crear, mostrar una pantalla de éxito ("¡Tu cuenta fue creada! Ya puedes iniciar sesión.") con un botón a `route('login')`, en vez de redirigir (no hay sesión que iniciar automáticamente — el nuevo líder inicia sesión por su cuenta con la contraseña que eligió, igual que si el coordinador lo hubiera creado manualmente).

    4. La vista debe usar el mismo layout visual que `resources/views/public/voter-registration.blade.php` (tarjeta con datos del enlace: candidato/campaña/coordinador — aquí solo coordinador, ya que no hay campaña ni líder todavía) para consistencia, pero como componente Volt (formulario reactivo, no un `<form method="POST">` plano) dado que necesita interactividad de OTP.
  </action>
  <verify>
    <automated>php artisan test --filter=PublicLeaderRegistrationTest</automated>
  </verify>
  <done>Un token LEADER válido muestra el formulario; guardar sin OTP verificado falla; guardar con OTP verificado crea el User líder con rol/coordinador/campañas correctos y marca la invitación como aceptada.</done>
</task>

<task type="auto">
  <name>Task 4: Casos de seguridad — token inválido/expirado/reutilizado y cédula duplicada</name>
  <files>tests/Feature/PublicLeaderRegistrationTest.php</files>
  <action>
    Extender el mismo archivo de test de la Task 3 (o crearlo aquí si la Task 3 solo dejó el happy path + bloqueo de OTP) con los casos restantes exigidos por seguridad, dado que esta ruta crea cuentas de usuario sin autenticación previa:
    - Token que no existe en la tabla `invitations` → `GET registro-lider/{token}` redirige a `home` con `session('error')`.
    - Token expirado (`expires_at` en el pasado) → mismo comportamiento.
    - Token de invitación de APOYO (creado con `leader_user_id` seteado, como en `PublicVoterRegistrationLinkTest`) usado en `registro-lider/{token}` → rechazado (no debe poder crear un líder con un enlace pensado para apoyos).
    - Token LEADER ya aceptado (reutilizar el mismo token después de un registro exitoso) → rechazado en el segundo intento.
    - Cédula (`document_number`) ya usada por otro `User` existente → `save()` agrega error de validación (`unique:users,document_number`, mismo mensaje/regla que `create-leader.blade.php`) y NO crea un segundo usuario.
  </action>
  <verify>
    <automated>php artisan test --filter=PublicLeaderRegistrationTest</automated>
  </verify>
  <done>Los 5 casos de seguridad tienen test explícito y pasan; ningún caso inválido crea un User.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 5: "Agregar Apoyo" desde el detalle de un líder</name>
  <files>routes/web.php, resources/views/livewire/coordinator/leader-add-voter.blade.php, resources/views/livewire/coordinator/leader-voters.blade.php, tests/Feature/Coordinator/AddVoterFromLeaderDetailTest.php</files>
  <behavior>
    - `GET coordinator/leaders/{leader}/voters/create` para un líder que NO pertenece al coordinador autenticado (distinto `coordinator_user_id`, o no tiene rol leader, o no comparte campaña) responde 403 — mismo `abort_unless` que ya usa `mount()` en `leader-voters.blade.php`.
    - Completar el formulario y guardar crea un `Voter` con `registered_by = $this->leader->id` (el líder de la URL), NUNCA `auth()->id()` (el coordinador que está logueado).
    - El botón "Agregar Apoyo" es visible en `coordinator/leaders/{leader}/voters` y apunta a esta nueva ruta.
  </behavior>
  <action>
    1. En `routes/web.php`, añadir dentro del grupo `coordinator.` (junto a `leaders/{leader}/voters`):
       ```php
       Volt::route('leaders/{leader}/voters/create', 'coordinator.leader-add-voter')->name('leaders.voters.create');
       ```
    2. Crear `resources/views/livewire/coordinator/leader-add-voter.blade.php` duplicando la lógica de `resources/views/livewire/leader/register-voter.blade.php` (campos, `updatedDocumentNumber()`, cascada departamento→municipio→puesto→mesa, validación con `MaxTablesForPollingPlace`, cálculo de `$status` vía `VoterValidationService`/`RegistraduriaLookup`), con estos cambios:
       - `mount(User $leader): void` con la MISMA verificación de autorización que `leader-voters.blade.php::mount()` (coordinador dueño del líder, mismo municipio, campaña compartida) — copiar ese bloque `abort_unless(...)` tal cual.
       - Resolver la campaña activa desde `$this->leader->campaigns()->first()` (no `auth()->user()`), ya que quien está logueado es el coordinador, no el líder.
       - `Voter::create([...])` usa `'registered_by' => $this->leader->id` en vez de `auth()->id()`.
       - Tras guardar, redirigir a `route('coordinator.leaders.voters', $this->leader)` (la lista de apoyos de ese líder), no a `leader.my-voters`.
       - Encabezado de la página: "Agregar Apoyo para {{ $leader->name }}" con botón "Volver" hacia `coordinator.leaders.voters`.
       - NO modificar `resources/views/livewire/leader/register-voter.blade.php` — es una copia adaptada, no una extracción compartida (evita cualquier riesgo de regresión en el flujo del líder autenticado).
    3. En `resources/views/livewire/coordinator/leader-voters.blade.php`, añadir un botón "Agregar Apoyo" junto al botón "Volver" del encabezado (línea ~99-107): `<flux:button variant="primary" :href="route('coordinator.leaders.voters.create', $leader)" wire:navigate icon="user-plus">Agregar Apoyo</flux:button>`.
  </action>
  <verify>
    <automated>php artisan test --filter=AddVoterFromLeaderDetailTest</automated>
  </verify>
  <done>Un coordinador puede abrir "Agregar Apoyo" desde el detalle de uno de sus líderes, completar el formulario y el Voter creado queda con registered_by = leader.id; un coordinador que intenta acceder al detalle de un líder ajeno recibe 403.</done>
</task>

<task type="auto">
  <name>Task 6: Regresión — conteo de apoyos por líder (feature ya implementada)</name>
  <files>tests/Feature/Coordinator/LeadersVoterCountDisplayTest.php</files>
  <action>
    Esta funcionalidad YA EXISTE (`coordinator/leaders.blade.php` usa `withCount(['registeredVoters as voters_count'])` y renderiza "{{ $leader->voters_count }} apoyos registrados"; el Filament `LeadersTable` ya cuenta `registeredVoters` vía `->counts('registeredVoters')`). No se requiere ningún cambio de código para esto — solo un test de regresión liviano que confirme que sigue funcionando después de los cambios de las Tasks 1-5 (en particular, que crear apoyos vía la nueva página de la Task 5 también los cuenta correctamente).

    Crear `tests/Feature/Coordinator/LeadersVoterCountDisplayTest.php`: un coordinador con un líder que tiene 2 apoyos registrados (uno vía factory con `registered_by`, otro creado a través del flujo nuevo de la Task 5 si es sencillo de simular, o ambos vía factory) visita `Volt::test('coordinator.leaders')` y el test hace `assertSee('2 apoyos registrados')`.
  </action>
  <verify>
    <automated>php artisan test --filter=LeadersVoterCountDisplayTest</automated>
  </verify>
  <done>El test pasa, confirmando que el conteo de apoyos por líder sigue correcto y no fue afectado por los cambios de este plan.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <what-built>
    Los 2 flujos nuevos (enlace de auto-registro de líder + agregar apoyo desde el detalle de un líder) con cobertura Pest completa, más la confirmación de que el conteo de apoyos sigue funcionando. Todo lo automatizable ya fue automatizado y verificado con tests.
  </what-built>
  <how-to-verify>
    Dado que esta tarea crea cuentas de usuario sin autenticación previa (superficie sensible) y modifica una pantalla del panel del coordinador, verificar manualmente en el navegador antes de dar por cerrado el task (los tests Pest no son suficientes para esto — confirmar en real):
    1. Inicia sesión como coordinador. Ve a "Líderes" (`coordinator/leaders`) y haz click en "Generar enlace de registro". Copia el enlace mostrado.
    2. Abre ese enlace en una ventana de incógnito (sin sesión iniciada). Completa el formulario: cédula, verifica que se autocomplete el nombre si la cédula existe en `national_identity_records`, envía el código OTP a un teléfono real, ingrésalo, y guarda. Confirma que ves el mensaje de éxito.
    3. Como super_admin (o consultando la DB), confirma que el nuevo usuario tiene rol `leader`, el `coordinator_user_id` correcto, y las mismas campañas del coordinador. Intenta reabrir el mismo enlace — debe rechazarlo (ya usado).
    4. Vuelve al panel del coordinador, entra al detalle de un líder (`coordinator/leaders/{leader}/voters`) y haz click en "Agregar Apoyo". Registra un apoyo de prueba y confirma que aparece en la lista de ese líder.
    5. Vuelve a "Líderes" y confirma que el conteo de "apoyos registrados" de ese líder subió correctamente.
  </how-to-verify>
  <resume-signal>Escribe "aprobado" o describe cualquier problema encontrado</resume-signal>
</task>

</tasks>

<verification>
Antes de cerrar el task:
- `vendor/bin/pint --dirty` sin cambios pendientes.
- `php artisan test --filter=InvitationServiceLeaderLinkTest`
- `php artisan test --filter=GenerateLeaderInvitationLinkTest`
- `php artisan test --filter=PublicLeaderRegistrationTest`
- `php artisan test --filter=AddVoterFromLeaderDetailTest`
- `php artisan test --filter=LeadersVoterCountDisplayTest`
- Ejecutar también los tests preexistentes que tocan los mismos archivos, para descartar regresión: `php artisan test --filter=PublicVoterRegistrationLinkTest`, `php artisan test --filter=CreateLeaderOtpTest`, `php artisan test tests/Feature/Leader/LeaderAppTest.php`.
</verification>

<success_criteria>
- Un coordinador puede generar un enlace de auto-registro de líder desde su propio panel y ese enlace crea, sin intervención manual del coordinador, un `User` con rol `leader` correctamente vinculado (coordinador + campañas), tras pasar OTP + verificación de cédula.
- El enlace es de un solo uso y no es intercambiable con los enlaces de registro de apoyos.
- Un coordinador puede agregar un apoyo a nombre de un líder específico desde el detalle de ese líder, quedando `registered_by` correctamente asignado al líder.
- El conteo de apoyos por líder sigue mostrándose correctamente en el listado de líderes.
- Todos los tests nuevos y los preexistentes relacionados pasan; `pint --dirty` limpio.
</success_criteria>

<output>
After completion, create `.planning/quick/260801-hvd-enlaces-de-auto-registro-para-que-coordi/260801-hvd-SUMMARY.md`
</output>
