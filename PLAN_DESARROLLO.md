# 📋 Plan de Desarrollo SIGMA
## Sistema Integral de Gestión y Análisis Electoral

**Versión:** 3.0 (Reorganizado)
**Fecha de Actualización:** 2025-11-08
**Estado del Proyecto:** 85% Completo

---

## 🎯 Resumen Ejecutivo

### Estado Actual: 85% Completo

**✅ COMPLETADO (85%):**
- ✅ Sistema de autenticación completo (Fortify: Login, Registro, 2FA, Reset Password)
- ✅ Panel de administración Filament v4 funcional
- ✅ UI moderna con Volt + Flux UI + Tailwind CSS v4
- ✅ Sistema de roles (5 roles: Super Admin, Admin Campaña, Coordinador, Líder, Revisor)
- ✅ Estructura territorial completa (Department, Municipality, Neighborhood)
- ✅ Importación masiva de barrios desde Excel
- ✅ Sistema multi-campaña con scopes (departamental/municipal/regional)
- ✅ UserResource completo (gestión de usuarios y roles)
- ✅ VoterResource completo (gestión de votantes + importación)
- ✅ Modelos de votantes y censo
- ✅ Sistema de validación contra censo
- ✅ Asignaciones territoriales (TerritorialAssignment)
- ✅ Sistema de encuestas completo (Survey, Questions, Responses, Metrics)
- ✅ Call Center funcional (CallAssignment, VerificationCall, cola)
- ✅ Sistema de mensajería SMS (Hablame API integrada)
- ✅ Plantillas de mensajes con variables dinámicas
- ✅ Control anti-spam y horarios permitidos
- ✅ Traducción completa al español
- ✅ 472 tests pasando
- ✅ Base de datos: SQLite (test), MySQL (producción)

**⚠️ PENDIENTE CRÍTICO (15%):**
- ❌ **Flags de clasificación** (anotadores, testigos, coordinadores especiales)
- ❌ **Relación votante:** Coordinadores y líderes también son votantes
- ❌ **Votantes directos:** Coordinadores y líderes pueden tener votantes propios
- ❌ **App Web móvil optimizada** para líderes (registro rápido)
- ❌ **Sistema de votación día D** (marcar "votó" / "no votó")
- ❌ **Dashboards diferenciados por rol**
- ❌ **Estadísticas para coordinadores especiales**
- ❌ Reportes avanzados y analítica

---

## 🏗️ Arquitectura del Sistema

### Concepto de Roles y Jerarquía

```
┌─────────────────────────────────────────────────────────┐
│              Jerarquía Real de SIGMA                    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  COORDINADOR (User con role=COORDINATOR)                │
│  ├─ Gestiona territorio asignado                        │
│  ├─ Tiene LÍDERES asignados bajo su coordinación       │
│  ├─ Tiene VOTANTES DIRECTOS propios                     │
│  ├─ ÉL MISMO ES VOTANTE (su voto cuenta)               │
│  └─ Si is_special_coordinator=true:                     │
│     └─ Estadísticas separadas                          │
│     └─ Puede ser: concejal, senador, etc.              │
│                                                         │
│  LÍDER (User con role=LEADER)                          │
│  ├─ Asignado a un coordinador                          │
│  ├─ Gestiona zona específica                           │
│  ├─ Tiene VOTANTES DIRECTOS asignados                  │
│  ├─ ÉL MISMO ES VOTANTE (su voto cuenta)               │
│  └─ Registra votantes en su territorio                 │
│                                                         │
│  VOTANTE (Modelo Voter)                                │
│  ├─ Puede ser persona común                            │
│  ├─ Puede ser un coordinador (referencia a User)       │
│  ├─ Puede ser un líder (referencia a User)             │
│  └─ Registrado por un líder/coordinador                │
│                                                         │
│  FLAGS DE CLASIFICACIÓN (campos boolean en User):       │
│  ├─ is_vote_recorder: Anotador el día D                │
│  ├─ is_witness: Testigo electoral (se le paga)         │
│  └─ is_special_coordinator: Coordinador especial       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Relaciones Clave

**Todos los coordinadores y líderes DEBEN tener su registro como votante:**
```php
// Un User con role=COORDINATOR o LEADER también tiene:
User::find(1)->voter // Su propio registro como votante

// Y puede tener votantes directos:
User::find(1)->directVoters // Votantes que él registró
```

---

## 📊 Estado Detallado por Fase

---

## 🔥 FASE 0: Configuración Base y Roles
**Estado:** ✅ 100% COMPLETADO

### 0.1 Sistema de Roles ✅
- [x] Instalado `spatie/laravel-permission`
- [x] Middleware de permisos configurado
- [x] Migración para roles y permisos
- [x] Seeders para roles base
- [x] Enum `UserRole` con 5 roles

**Roles Implementados:**
```php
- SUPER_ADMIN      // Administrador General
- ADMIN_CAMPAIGN   // Administrador de Campaña
- COORDINATOR      // Coordinador
- LEADER           // Líder
- REVIEWER         // Revisor
```

### 0.2 Tests ✅
- [x] Test de asignación de roles
- [x] Test de permisos por rol
- [x] Test de políticas de acceso

---

## 🗺️ FASE 1: Estructura Territorial
**Estado:** ✅ 100% COMPLETADO

### 1.1 Modelo de Departamento ✅
- [x] Modelo `Department`
- [x] Migración completa
- [x] Seeder con departamentos de Colombia
- [x] DepartmentResource de Filament
- [x] Tests CRUD

### 1.2 Modelo de Municipio ✅
- [x] Modelo `Municipality`
- [x] Migración con relación a Department
- [x] Seeder con municipios
- [x] MunicipalityResource de Filament
- [x] Filtros por departamento
- [x] Tests CRUD y relaciones

### 1.3 Modelo de Barrio ✅
- [x] Modelo `Neighborhood`
- [x] Migración con relación a Municipality
- [x] Soporte para barrios globales y por campaña
- [x] NeighborhoodResource de Filament
- [x] Importación masiva desde Excel
- [x] Comando artisan `neighborhoods:import`
- [x] Tests CRUD

**Logro:** 224 barrios importados para Sincelejo, Sucre

---

## 🏛️ FASE 2: Sistema Multi-Campaña
**Estado:** ✅ 100% COMPLETADO

### 2.1 Modelo de Campaña ✅
- [x] Modelo `Campaign`
- [x] Migración con todos los campos
- [x] Enum `CampaignStatus` (draft, active, paused, completed)
- [x] Enum `CampaignScope` (departamental, municipal, regional)
- [x] CampaignResource de Filament completo
- [x] Query scopes (municipal, departamental, regional)
- [x] Tests CRUD

### 2.2 Relación Campaña-Usuario ✅
- [x] Pivot table `campaign_user`
- [x] Relación many-to-many
- [x] Tests de permisos por campaña

---

## 👥 FASE 3: Gestión de Usuarios y Jerarquía
**Estado:** ✅ 95% COMPLETADO | ⚠️ 5% PENDIENTE

### 3.1 Modelo User Extendido ✅
- [x] Campos adicionales en users
- [x] Migración completa
- [x] Factory actualizado
- [x] UserResource de Filament completo

**Campos Existentes:**
```php
- phone, secondary_phone
- address
- municipality_id, neighborhood_id
- document_number
- birth_date
- role (UserRole enum)
```

### 3.2 Relación User-Voter ⚠️ PENDIENTE
**Objetivo:** Todo coordinador y líder debe tener su propio registro como votante.

#### Tareas Pendientes:
- [ ] Agregar campo `user_id` a tabla `voters` (nullable)
  ```php
  // Migración:
  $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
  $table->index('user_id');
  ```
- [ ] Relación en modelo `User`
  ```php
  public function voter(): BelongsTo
  {
      return $this->belongsTo(Voter::class);
  }

  public function directVoters(): HasMany
  {
      return $this->hasMany(Voter::class, 'registered_by');
  }
  ```
- [ ] Relación en modelo `Voter`
  ```php
  public function user(): BelongsTo
  {
      return $this->belongsTo(User::class);
  }
  ```
- [ ] Observer `UserObserver` para auto-crear votante
  ```php
  public function created(User $user): void
  {
      // Si es coordinador o líder, crear su registro como votante
      if (in_array($user->role, [UserRole::COORDINATOR, UserRole::LEADER])) {
          Voter::create([
              'user_id' => $user->id,
              'campaign_id' => $user->campaigns()->first()?->id,
              'document_number' => $user->document_number,
              'first_name' => explode(' ', $user->name)[0],
              'last_name' => explode(' ', $user->name, 2)[1] ?? '',
              'birth_date' => $user->birth_date,
              'phone' => $user->phone,
              'municipality_id' => $user->municipality_id,
              'neighborhood_id' => $user->neighborhood_id,
              'address' => $user->address,
              'registered_by' => $user->id, // Se auto-registra
              'status' => VoterStatus::CONFIRMED,
          ]);
      }
  }
  ```
- [ ] Comando para migrar users existentes
  ```bash
  php artisan users:create-voter-records
  ```
- [ ] Tests (15+ tests)
  - [ ] Test crear coordinador auto-crea votante
  - [ ] Test crear líder auto-crea votante
  - [ ] Test relación user->voter
  - [ ] Test relación user->directVoters
  - [ ] Test comando migración

**Archivos:**
- `database/migrations/xxxx_add_user_id_to_voters_table.php`
- `app/Observers/UserObserver.php`
- `app/Console/Commands/CreateVoterRecordsForUsers.php`
- `tests/Feature/UserVoterRelationTest.php`

**Estimación:** 1 día

---

### 3.3 Flags de Clasificación ⚠️ PENDIENTE
**Objetivo:** Agregar campos boolean para clasificar usuarios sin cambiar su rol.

#### Tareas Pendientes:
- [ ] Migración para agregar flags
  ```php
  Schema::table('users', function (Blueprint $table) {
      $table->boolean('is_vote_recorder')->default(false)
          ->comment('Anotador el día de votación');

      $table->boolean('is_witness')->default(false)
          ->comment('Testigo electoral (se le paga)');

      $table->string('witness_assigned_station')->nullable()
          ->comment('Mesa electoral asignada como testigo');

      $table->decimal('witness_payment_amount', 10, 2)->nullable()
          ->comment('Monto de pago como testigo');

      $table->boolean('is_special_coordinator')->default(false)
          ->comment('Coordinador especial (concejal, senador, etc.)');

      $table->index(['is_vote_recorder', 'is_witness', 'is_special_coordinator']);
  });
  ```
- [ ] Actualizar UserResource con nuevos campos
  - [ ] Sección "Clasificaciones Especiales"
  - [ ] Toggle para `is_vote_recorder`
  - [ ] Toggle para `is_witness` + campos de testigo
  - [ ] Toggle para `is_special_coordinator`
- [ ] Query scopes en modelo `User`
  ```php
  public function scopeVoteRecorders(Builder $query): void
  {
      $query->where('is_vote_recorder', true);
  }

  public function scopeWitnesses(Builder $query): void
  {
      $query->where('is_witness', true);
  }

  public function scopeSpecialCoordinators(Builder $query): void
  {
      $query->where('role', UserRole::COORDINATOR)
            ->where('is_special_coordinator', true);
  }
  ```
- [ ] Filtros en UserResource para estos flags
- [ ] Actualizar Factory para generar datos de testigos
- [ ] Tests (10+ tests)

**Archivos:**
- `database/migrations/xxxx_add_classification_flags_to_users_table.php`
- `app/Filament/Resources/Users/UserResource.php` (actualizar)
- `database/factories/UserFactory.php` (actualizar)
- `tests/Feature/UserClassificationFlagsTest.php`

**Estimación:** 0.5 días

---

### 3.4 Asignaciones Territoriales ✅
- [x] Modelo `TerritorialAssignment`
- [x] Asignación de coordinadores a territorios
- [x] Asignación de líderes a zonas
- [x] Validación jerárquica
- [x] Tests

---

## 🗳️ FASE 4: Módulo de Votantes
**Estado:** ✅ 100% COMPLETADO

### 4.1 Enum de Estados del Votante ✅
- [x] Enum `VoterStatus` completo
- [x] Documentación de estados
- [x] Colores y badges para UI

### 4.2 Modelo de Votante ✅
- [x] Modelo `Voter` completo
- [x] Migración con todos los campos
- [x] Factory para testing
- [x] Relaciones (Campaign, Leader, Territory)
- [x] Scopes útiles
- [x] Tests

### 4.3 VoterResource de Filament ✅
- [x] Resource completo
- [x] Form con validaciones
- [x] Table con filtros avanzados
- [x] Acciones masivas
- [x] Importación CSV/Excel
- [x] Exportación
- [x] Tests de UI

### 4.4 Estadísticas de Votantes ✅
- [x] Conteo por estado
- [x] Filtros por territorio
- [x] Filtros por líder/coordinador

---

## ✅ FASE 5: Validación y Censo Electoral
**Estado:** ✅ 100% COMPLETADO

### 5.1 Modelo de Censo Electoral ✅
- [x] Modelo `CensusRecord`
- [x] Migración optimizada con índices
- [x] Importador CSV/Excel
- [x] Tests

### 5.2 Servicio de Validación ✅
- [x] `VoterValidationService`
- [x] Lógica de matching con censo
- [x] Job asíncrono para validación masiva
- [x] Tests unitarios

### 5.3 Modelo de Historial de Validación ✅
- [x] Modelo `ValidationHistory`
- [x] Tracking de cambios de estado
- [x] Auditoría completa
- [x] Tests

### 5.4 Interface de Revisión ✅
- [x] Panel para revisores en Filament
- [x] Queue de votantes pendientes
- [x] Acciones rápidas (aprobar/rechazar)
- [x] Tests

---

## 📞 FASE 6: Módulos Estratégicos
**Estado:** ✅ 100% COMPLETADO

### 6.1 Sistema de Encuestas ✅
- [x] Modelo `Survey` con versionamiento
- [x] Modelo `SurveyQuestion` (5 tipos)
- [x] Modelo `SurveyResponse`
- [x] Modelo `SurveyMetrics`
- [x] Enum `QuestionType`
- [x] Cálculo automático de métricas
- [x] Tests completos

### 6.2 Sistema de Mensajería ✅
- [x] Modelo `Message`
- [x] Modelo `MessageTemplate`
- [x] Modelo `MessageBatch`
- [x] `HablameSmsService` (integración Hablame SMS)
- [x] Control anti-spam
- [x] Horarios permitidos
- [x] Variables dinámicas en plantillas
- [x] Tracking de estado completo
- [x] Tests

**Integración SMS:**
- [x] API Hablame v5 funcionando
- [x] Formato de request correcto
- [x] Parsing de respuestas (statusId 102, 106)
- [x] Formateo de números (10 dígitos)
- [x] Documentación completa

### 6.3 Call Center Workflow ✅
- [x] Modelo `CallAssignment`
- [x] Modelo `VerificationCall`
- [x] Enum `CallResult`
- [x] Queue de llamadas
- [x] Interface de llamadas
- [x] Estadísticas y métricas
- [x] Tests completos

---

## 🌐 FASE 7: Sistema de Traducción
**Estado:** ✅ 100% COMPLETADO

### 7.1 Configuración de Idioma ✅
- [x] Configurado `config/app.php` locale='es'
- [x] Filament configurado para español
- [x] Tests de configuración

### 7.2 Archivos de Traducción ✅
- [x] `lang/es/filament.php`
- [x] `lang/es/models.php`
- [x] `lang/es/enums.php`
- [x] `lang/es/validation.php`

### 7.3 Traducción de Resources ✅
- [x] Todos los Resources traducidos
- [x] Etiquetas y mensajes en español
- [x] Componentes Volt traducidos

---

## 🖥️ FASE 8: Interfaces Web Optimizadas
**Estado:** ⚠️ 30% COMPLETADO | 🔥 PRIORIDAD ALTA

### 8.1 App Web para Líderes ⚠️ PENDIENTE
**Objetivo:** Vista móvil optimizada para que líderes registren votantes rápidamente.

#### Tareas Pendientes:
- [ ] Crear layout `resources/views/layouts/app.blade.php`
  - [ ] Diseño mobile-first
  - [ ] Menú simplificado
  - [ ] Logo de campaña
- [ ] Middleware `EnsureUserHasRole`
  ```php
  Route::middleware(['auth', 'role:leader'])->prefix('app')->group(function () {
      Route::get('/dashboard', LeaderDashboard::class);
      Route::get('/register-voter', QuickVoterRegister::class);
      Route::get('/my-voters', MyVoters::class);
  });
  ```
- [ ] Componente Volt: Dashboard del Líder
  ```php
  // resources/views/livewire/leader/dashboard.blade.php
  - Estadísticas personales (votantes registrados, confirmados, pendientes)
  - Metas vs logros
  - Botón grande "REGISTRAR VOTANTE"
  ```
- [ ] Componente Volt: Registro Rápido de Votantes
  ```php
  // resources/views/livewire/leader/quick-voter-register.blade.php
  - Formulario optimizado (solo campos esenciales)
  - Auto-guardado cada 3 segundos
  - Validación en tiempo real
  - Búsqueda por documento (verificar si ya existe)
  - Botón "Registrar y Nuevo" (continuar registrando)
  ```
- [ ] Componente Volt: Mis Votantes
  ```php
  // resources/views/livewire/leader/my-voters.blade.php
  - Lista de votantes del líder
  - Filtros por estado
  - Búsqueda rápida
  - Edición rápida
  ```
- [ ] Tests (25+ tests)

**Archivos:**
- `resources/views/layouts/app.blade.php`
- `app/Http/Middleware/EnsureUserHasRole.php`
- `resources/views/livewire/leader/dashboard.blade.php`
- `resources/views/livewire/leader/quick-voter-register.blade.php`
- `resources/views/livewire/leader/my-voters.blade.php`
- `routes/web.php` (actualizar)
- `tests/Feature/Leader/` (todos)

**Estimación:** 3 días

---

### 8.2 App Web para Coordinadores ⚠️ PENDIENTE
**Objetivo:** Vista para coordinadores gestionen líderes y asignen anotadores/testigos.

#### Tareas Pendientes:
- [ ] Rutas para coordinadores
  ```php
  Route::middleware(['auth', 'role:coordinator'])->prefix('app/coordinator')->group(function () {
      Route::get('/dashboard', CoordinatorDashboard::class);
      Route::get('/leaders', ManageLeaders::class);
      Route::get('/assign-recorders', AssignVoteRecorders::class);
      Route::get('/assign-witnesses', AssignWitnesses::class);
      Route::get('/my-voters', CoordinatorVoters::class);
  });
  ```
- [ ] Dashboard del Coordinador
  - [ ] Estadísticas de territorio
  - [ ] Lista de líderes bajo coordinación
  - [ ] Performance de cada líder
  - [ ] Votantes directos del coordinador
- [ ] Gestión de Líderes
  - [ ] Ver líderes asignados
  - [ ] Ver votantes de cada líder
  - [ ] Re-asignar territorios
- [ ] Asignación de Anotadores
  - [ ] Seleccionar users (coordinadores/líderes/votantes)
  - [ ] Marcar flag `is_vote_recorder = true`
  - [ ] Asignar mesa/territorio
- [ ] Asignación de Testigos
  - [ ] Seleccionar users
  - [ ] Marcar flag `is_witness = true`
  - [ ] Asignar mesa electoral
  - [ ] Registrar monto de pago
- [ ] Votantes Directos del Coordinador
  - [ ] Registro de votantes propios (igual que líderes)
  - [ ] Lista de votantes directos
- [ ] Tests (20+ tests)

**Archivos:**
- `resources/views/livewire/coordinator/dashboard.blade.php`
- `resources/views/livewire/coordinator/manage-leaders.blade.php`
- `resources/views/livewire/coordinator/assign-vote-recorders.blade.php`
- `resources/views/livewire/coordinator/assign-witnesses.blade.php`
- `resources/views/livewire/coordinator/my-voters.blade.php`
- `tests/Feature/Coordinator/` (todos)

**Estimación:** 2-3 días

---

### 8.3 Sistema de Votación Día D ⚠️ PENDIENTE
**Objetivo:** Anotadores marcan votantes como "votó" o "no votó" el día de elecciones.

#### Tareas Pendientes:
- [ ] Crear modelo `VoteRecord`
  ```php
  - id
  - voter_id (FK)
  - campaign_id (FK)
  - recorded_by (FK a users) // El anotador
  - vote_status (enum: voted, did_not_vote)
  - voted_at (timestamp)
  - polling_station (string) // Mesa de votación
  - notes (text, nullable)
  - timestamps
  ```
- [ ] Enum `VoteStatus`
  ```php
  case VOTED = 'voted';
  case DID_NOT_VOTE = 'did_not_vote';
  ```
- [ ] Middleware `IsElectionDay`
  ```php
  public function handle(Request $request, Closure $next)
  {
      $electionDate = config('voting.election_date');

      if (!$electionDate || now()->format('Y-m-d') !== $electionDate) {
          abort(403, 'El sistema de votación solo está disponible el día de las elecciones.');
      }

      return $next($request);
  }
  ```
- [ ] Configuración `config/voting.php`
  ```php
  return [
      'election_date' => env('ELECTION_DATE', '2025-10-27'),
      'vote_recording_enabled' => env('VOTE_RECORDING_ENABLED', false),
  ];
  ```
- [ ] Rutas para anotadores (día D)
  ```php
  Route::middleware(['auth', 'user_flag:is_vote_recorder', 'is_election_day'])
      ->prefix('app/voting')
      ->group(function () {
          Route::get('/dashboard', VotingDashboard::class);
          Route::get('/record', RecordVotes::class);
      });
  ```
- [ ] Componente Volt: Dashboard Votación (tiempo real)
  - [ ] % de participación actual
  - [ ] Votos registrados vs meta
  - [ ] Gráfica votos por hora
  - [ ] Comparativa con otras mesas
- [ ] Componente Volt: Registrar Votos
  - [ ] Vista móvil optimizada
  - [ ] Búsqueda rápida (documento/nombre)
  - [ ] Botones grandes "VOTÓ" / "NO VOTÓ"
  - [ ] Confirmación visual (verde/rojo)
  - [ ] Lista de votantes asignados a la mesa
- [ ] Widget para Panel Admin: Dashboard Día D
  - [ ] Mapa de calor en tiempo real
  - [ ] Participación por territorio
  - [ ] Ranking de mesas
- [ ] Tests (20+ tests)

**Archivos:**
- `app/Models/VoteRecord.php`
- `app/Enums/VoteStatus.php`
- `app/Http/Middleware/IsElectionDay.php`
- `config/voting.php`
- `resources/views/livewire/voting/dashboard.blade.php`
- `resources/views/livewire/voting/record-votes.blade.php`
- `app/Filament/Widgets/ElectionDayDashboard.php`
- `database/migrations/xxxx_create_vote_records_table.php`
- `tests/Feature/Voting/` (todos)

**Estimación:** 2-3 días

---

### 8.4 Dashboards Diferenciados por Rol ⚠️ PENDIENTE
**Objetivo:** Cada rol ve un dashboard específico al entrar al sistema.

#### Tareas Pendientes:
- [ ] Dashboard SUPER_ADMIN (Panel Admin)
  - [ ] Overview de todas las campañas
  - [ ] Métricas globales del sistema
  - [ ] Alertas y notificaciones
  - [ ] Acceso a todos los módulos
- [ ] Dashboard ADMIN_CAMPAIGN (Panel Admin)
  - [ ] Vista de su campaña
  - [ ] Todos los territorios de la campaña
  - [ ] Performance de coordinadores
  - [ ] Estadísticas generales
- [ ] Dashboard COORDINATOR (App Web)
  - [ ] Su territorio asignado
  - [ ] Performance de líderes bajo su coordinación
  - [ ] Sus votantes directos
  - [ ] Estadísticas territoriales
  - [ ] Si `is_special_coordinator = true`:
    - [ ] Vista especial con foco en votantes directos
    - [ ] Sin sección de líderes (o minimizada)
- [ ] Dashboard LEADER (App Web)
  - [ ] Estadísticas personales
  - [ ] Sus votantes registrados
  - [ ] Metas vs logros
  - [ ] Ranking entre líderes
- [ ] Dashboard REVIEWER (Panel Admin)
  - [ ] Call center stats personales
  - [ ] Votantes pendientes de validar
  - [ ] Performance personal
  - [ ] Cola de llamadas
- [ ] Redirección automática al dashboard correcto
  ```php
  // En LoginResponse o middleware
  return match (auth()->user()->role) {
      UserRole::SUPER_ADMIN, UserRole::ADMIN_CAMPAIGN, UserRole::REVIEWER
          => redirect()->route('filament.admin.pages.dashboard'),
      UserRole::COORDINATOR
          => redirect()->route('coordinator.dashboard'),
      UserRole::LEADER
          => redirect()->route('leader.dashboard'),
      default
          => redirect()->route('home'),
  };
  ```
- [ ] Tests (15+ tests)

**Archivos:**
- `app/Filament/Pages/Dashboard.php` (customizar por rol)
- `resources/views/livewire/dashboards/coordinator.blade.php`
- `resources/views/livewire/dashboards/leader.blade.php`
- `app/Http/Responses/LoginResponse.php` (customizar)
- `tests/Feature/Dashboards/` (todos)

**Estimación:** 2 días

---

### 8.5 Estadísticas para Coordinadores Especiales ⚠️ PENDIENTE
**Objetivo:** Reportes y listados separados para coordinadores especiales.

#### Tareas Pendientes:
- [ ] Widget en Panel Admin: Coordinadores Especiales
  ```php
  // app/Filament/Widgets/SpecialCoordinatorsWidget.php
  - Listado de coordinadores especiales
  - Votantes directos de cada uno
  - Performance comparativa
  - Clasificación (concejal, senador, etc.)
  ```
- [ ] Filtros en VoterResource
  - [ ] Filtro "Registrado por coordinador especial"
  - [ ] Mostrar tipo de coordinador especial
- [ ] Exportación específica
  - [ ] Excel: "Votantes de Coordinadores Especiales"
  - [ ] Separado por tipo de coordinador
- [ ] Query scopes útiles
  ```php
  // En Voter.php
  public function scopeFromSpecialCoordinators(Builder $query): void
  {
      $query->whereHas('registeredBy', function ($q) {
          $q->where('role', UserRole::COORDINATOR)
            ->where('is_special_coordinator', true);
      });
  }
  ```
- [ ] Tests (8+ tests)

**Archivos:**
- `app/Filament/Widgets/SpecialCoordinatorsWidget.php`
- `app/Exports/SpecialCoordinatorVotersExport.php`
- `tests/Feature/SpecialCoordinators/` (todos)

**Estimación:** 1 día

---

## 📊 FASE 9: Reportes y Analítica
**Estado:** ⚠️ 20% COMPLETADO | 🔥 PRIORIDAD MEDIA

### 9.1 Widgets de Filament ⚠️ PARCIAL

#### 9.1.1 Widget de Overview General ✅
- [x] Total votantes por estado
- [x] Tasa de validación básica

#### 9.1.2 Widgets Pendientes ⚠️
- [ ] Widget por Territorio
  - [ ] Mapa de calor
  - [ ] Gráfica por municipio
  - [ ] Gráfica por barrio
- [ ] Widget por Líder
  - [ ] Ranking de líderes
  - [ ] Eficiencia de captación
  - [ ] Tasa de confirmación
- [ ] Widget de Encuestas
  - [ ] Resultados visuales
  - [ ] Comparativas temporales
- [ ] Tests (15+ tests)

**Estimación:** 2 días

---

### 9.2 Reportes Exportables ⚠️ PENDIENTE

#### Tareas Pendientes:
- [ ] Reporte de Votantes Avanzado
  - [ ] Excel con múltiples hojas
    - [ ] Por territorio
    - [ ] Por líder
    - [ ] Por estado
    - [ ] Por coordinador especial
  - [ ] PDF con resumen ejecutivo
  - [ ] Filtros dinámicos
- [ ] Reporte de Líderes
  - [ ] Performance individual
  - [ ] Ranking general
  - [ ] Comparativas territoriales
  - [ ] Excel/PDF
- [ ] Reporte de Coordinadores
  - [ ] Eficiencia territorial
  - [ ] Performance de líderes
  - [ ] Votantes directos vs indirectos
- [ ] Reporte de Testigos Electorales
  - [ ] Lista completa
  - [ ] Mesas asignadas
  - [ ] Pagos totales
  - [ ] Exportación para contabilidad
- [ ] Reporte de Anotadores (post-elecciones)
  - [ ] Participación registrada
  - [ ] Votos por mesa
  - [ ] Estadísticas día D
- [ ] Tests (15+ tests)

**Archivos:**
- `app/Services/ReportGenerator.php`
- `app/Exports/VotersAdvancedExport.php`
- `app/Exports/LeadersPerformanceExport.php`
- `app/Exports/CoordinatorsReportExport.php`
- `app/Exports/WitnessesExport.php`
- `app/Exports/VoteRecordersExport.php`
- `tests/Feature/Reports/` (todos)

**Estimación:** 3 días

---

### 9.3 API REST ⚠️ PENDIENTE (Prioridad Baja)

#### Tareas Pendientes:
- [ ] Instalar y configurar Laravel Sanctum
- [ ] Crear estructura de API
  ```
  /api/v1/
    ├── voters
    ├── campaigns
    ├── stats
    ├── leaders
    ├── coordinators
    └── vote-records (día D)
  ```
- [ ] API Resources para transformación
- [ ] Autenticación con tokens
- [ ] Rate limiting
- [ ] Documentación (Scribe/Scramble)
- [ ] Tests API (30+ tests)

**Archivos:**
- `routes/api.php`
- `app/Http/Controllers/Api/V1/` (controllers)
- `app/Http/Resources/` (resources)
- `tests/Feature/Api/` (tests)

**Estimación:** 4 días

---

## 🧪 Testing y Calidad

### Estado Actual:
- ✅ **472 tests pasando**
- ✅ Alta cobertura en modelos y servicios
- ✅ Tests para Resources de Filament

### Tests Pendientes:
- [ ] ~15 tests para User-Voter relation
- [ ] ~10 tests para flags de clasificación
- [ ] ~25 tests para App Web Líderes
- [ ] ~20 tests para Sistema Votación Día D
- [ ] ~20 tests para App Web Coordinadores
- [ ] ~15 tests para Dashboards
- [ ] ~8 tests para Coordinadores Especiales
- [ ] ~15 tests para Widgets
- [ ] ~15 tests para Reportes
- [ ] ~30 tests para API (opcional)

**Meta Final:** 600+ tests

---

## 📅 Roadmap Recomendado

### Sprint 1 (Semana 1): Relaciones y Clasificaciones
**Objetivo:** Completar el modelo de datos

- **Día 1:**
  - [ ] FASE 3.2: Relación User-Voter (1 día)
  - [ ] Migración user_id en voters
  - [ ] Observer para auto-crear votantes
  - [ ] Comando migración users existentes
  - [ ] Tests

- **Día 2:**
  - [ ] FASE 3.3: Flags de clasificación (0.5 día)
  - [ ] Migración flags en users
  - [ ] Actualizar UserResource
  - [ ] Query scopes
  - [ ] Tests

### Sprint 2 (Semana 2): App Web Líderes
**Objetivo:** Líderes pueden registrar votantes fácilmente

- **Día 1-3:**
  - [ ] FASE 8.1: App Web para Líderes (3 días)
  - [ ] Layout app.blade.php
  - [ ] Dashboard líder
  - [ ] Registro rápido votantes
  - [ ] Mis votantes
  - [ ] Tests

### Sprint 3 (Semana 3): App Web Coordinadores
**Objetivo:** Coordinadores gestionan equipo

- **Día 1-3:**
  - [ ] FASE 8.2: App Web para Coordinadores (2-3 días)
  - [ ] Dashboard coordinador
  - [ ] Gestión de líderes
  - [ ] Asignación anotadores/testigos
  - [ ] Votantes directos
  - [ ] Tests

- **Día 4:**
  - [ ] FASE 8.5: Estadísticas Coordinadores Especiales (1 día)
  - [ ] Widget especial
  - [ ] Filtros y exportaciones
  - [ ] Tests

### Sprint 4 (Semana 4): Sistema Votación Día D
**Objetivo:** Sistema listo para día de elecciones

- **Día 1-3:**
  - [ ] FASE 8.3: Sistema Votación Día D (2-3 días)
  - [ ] Modelo VoteRecord
  - [ ] Middleware IsElectionDay
  - [ ] Vista anotadores
  - [ ] Dashboard tiempo real
  - [ ] Tests

### Sprint 5 (Semana 5): Dashboards y Widgets
**Objetivo:** Cada rol tiene su vista optimizada

- **Día 1-2:**
  - [ ] FASE 8.4: Dashboards por Rol (2 días)
  - [ ] Dashboard para cada rol
  - [ ] Redirección automática
  - [ ] Tests

- **Día 3-4:**
  - [ ] FASE 9.1: Widgets Avanzados (2 días)
  - [ ] Widget territorio
  - [ ] Widget líderes
  - [ ] Widget encuestas
  - [ ] Tests

### Sprint 6+ (Opcional): Reportes y API
**Prioridad:** BAJA - Solo si hay tiempo/necesidad

- [ ] FASE 9.2: Reportes Exportables (3 días)
- [ ] FASE 9.3: API REST (4 días)

**Estimación Total:** 18-22 días de desarrollo

---

## 🎯 Prioridades Críticas

### DEBE Completarse Antes de Elecciones:
1. ✅ Relación User-Voter (coordinadores/líderes son votantes)
2. ✅ Flags de clasificación (anotadores, testigos, especiales)
3. 🔥 App Web para Líderes (registro rápido)
4. 🔥 Sistema Votación Día D (marcar votos)
5. 🔥 Asignación de anotadores por coordinadores
6. ⚠️ Dashboards por rol

### PUEDE Completarse Después:
- Widgets avanzados
- Reportes complejos
- API REST
- App móvil nativa

---

## 📦 Dependencias

### Ya Instaladas:
- ✅ `maatwebsite/excel` - Importación/exportación
- ✅ `spatie/laravel-permission` - Roles y permisos
- ✅ Filament v4 completo
- ✅ Livewire v3 + Volt
- ✅ Flux UI Free

### Por Instalar (según necesidad):
```bash
# Para reportes PDF avanzados
composer require barryvdh/laravel-dompdf

# Para API (Sprint 6+)
composer require laravel/sanctum

# Para auditoría completa (opcional)
composer require owen-it/laravel-auditing
```

---

## 📝 Documentación

### Documentación Existente:
- ✅ `docs/DECISIONES.md` - Decisiones de arquitectura
- ✅ `docs/PATRON_ENUMS.md` - Patrón para enums
- ✅ `docs/CHEATSHEET.md` - Comandos útiles
- ✅ `docs/INTEGRACION_HABLAME_SMS.md` - Integración SMS
- ✅ `docs/SURVEY_EXPORT_INTEGRATION.md` - Exportación encuestas
- ✅ `docs/GUIA_USO_PLAN.md` - Guía de uso del plan

### Documentación Pendiente:
- [ ] `docs/JERARQUIA_USUARIOS.md` - Explicar jerarquía user-voter
- [ ] `docs/SISTEMA_VOTACION_DIA_D.md` - Guía sistema votación
- [ ] `docs/DEPLOYMENT.md` - Guía de despliegue
- [ ] `docs/API.md` - Documentación API (si se implementa)

---

## 🎓 Estándares de Código

### Decisiones Tomadas:
- ✅ **Import Statements:** SIEMPRE usar `use` explícitos, NUNCA alias
  ```php
  // ✅ Correcto
  use Filament\Forms\Components\Select;
  use Filament\Forms\Components\TextInput;

  // ❌ Incorrecto
  use Filament\Forms;
  Forms\Components\Select::make()
  ```
- ✅ Formateo con Laravel Pint antes de commit
- ✅ Tests con Pest v4
- ✅ Convenciones Laravel 12
- ✅ Filament v4 best practices

---

## 🎯 Métricas de Éxito

### Al Completar Sprint 1-5:
- [ ] Coordinadores y líderes tienen registro como votantes
- [ ] Flags de clasificación funcionando (anotadores, testigos, especiales)
- [ ] Líderes registran 100+ votantes/día desde app móvil
- [ ] Sistema votación listo para día D (1000+ votos/hora)
- [ ] Cada rol tiene dashboard específico
- [ ] 600+ tests pasando
- [ ] < 200ms response time promedio

---

**Última Actualización:** 2025-11-08
**Próxima Revisión:** Después de completar Sprint 1
**Progreso:** 85% → Meta 100% en 18-22 días
