# 🗳️ SIGMA — Sistema Integral de Gestión y Análisis Electoral

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square)](https://laravel.com/)
[![Filament](https://img.shields.io/badge/Filament-4-FDAE4B?style=flat-square)](https://filamentphp.com/)
[![Pest](https://img.shields.io/badge/Tests-Pest_4-22C55E?style=flat-square)](https://pestphp.com/)

Plataforma de operaciones electorales para campañas políticas: organización territorial, jerarquía de equipo, ciclo de vida del votante ("Apoyo"), validación contra el censo y la Registraduría, comunicaciones masivas, reportería operativa y ejecución de Día D — con aislamiento estricto de datos por campaña y control de acceso por rol en cada capa.

---

## Estado del Proyecto

| Milestone | Estado | Contenido |
|-----------|--------|-----------|
| **v1.0** — MVP Hardening | ✅ Enviado | Operaciones core endurecidas: aislamiento por campaña, ciclo de vida del Apoyo, seguimiento, reportería, Día D |
| **v1.1** — Consulta de Puesto de Votación Resiliente | ✅ Enviado | Cascada de resolución (BD → snapshot censal → fuente en vivo) con reconciliación automática horaria |
| **v1.2** — Articuladores + Metadata de Usuario | 🚧 En progreso (fases 12-15 de 17) | Jerarquía articulador→coordinador, catálogo de metadata asignable por superiores |

Fuente de verdad viva del estado y las decisiones vigentes: **[`.planning/PROJECT.md`](.planning/PROJECT.md)**. Historial de cambios: **[`CHANGELOG.md`](CHANGELOG.md)**. Detalle de lo ya enviado: **[`.planning/MILESTONES.md`](.planning/MILESTONES.md)**.

---

## Arquitectura

Laravel 12 (MVC) + Livewire 3/Volt (frontend reactivo, componentes de página completos) + Filament 4 (CRUD administrativo por panel) + Eloquent ORM, con autenticación por sesión vía Fortify y RBAC vía Spatie Permission.

**El contexto de campaña es transversal a todo el sistema.** Un `super_admin` selecciona la campaña activa desde el topbar; un `CampaignMembershipScope` global restringe casi cualquier consulta de `User`/`Voter`/datos operativos a esa campaña. El aislamiento cruzado entre campañas es un requisito de producto, no un detalle de implementación — se aplica en queries, imports/exports, jobs en cola y widgets de reportería por igual.

La jerarquía de equipo (líder → coordinador → articulador) se modela con FKs auto-referenciadas en `User` (`coordinator_user_id`, `area_coordinator_user_id`), no con una tabla de jerarquía genérica — cada nivel es plano y sin anidamiento adicional por diseño.

### Paneles y roles

Cada rol opera desde su propio panel Filament, auto-contenido y con datos ya filtrados a lo que le corresponde ver:

| Rol | Panel | Qué gestiona |
|-----|-------|--------------|
| **Super Admin** | `/admin` | Todo: campañas, usuarios de cualquier rol, catálogo de metadata, configuración global |
| **Admin Campaña** | `/admin` | Todo lo anterior, acotado a la campaña activa |
| **Analista de Reportes** | `/reports` | Solo lectura: dashboards y reportería operativa, sin capacidad de edición |
| **Articulador** | `/articulador` | Sus propios coordinadores (crear, editar, listar) — nunca ve los de otro articulador |
| **Coordinador** | `/coordinator` | Sus propios líderes y los Apoyos de su equipo, incluida la ejecución de Día D |
| **Líder** | `/leader` | Sus propios Apoyos registrados |
| **Revisor** | — | Rol de validación/auditoría, sin panel propio dedicado |

Cada panel de rol operativo (coordinador/articulador/líder) es un espejo estructural del inmediatamente superior, con el alcance de datos recortado y algunas capacidades (ej. OTP, auto-promoción) deliberadamente omitidas cuando no aplican a ese nivel.

---

## Módulos y Flujos de Negocio

**Campaña y territorio.** Una `Campaign` define el contexto activo; la estructura territorial es `Department → Municipality → Neighborhood` (sembrada con `colombia:import`: 33 departamentos, 1,123 municipios). `TerritorialAssignment` liga usuarios a su territorio de trabajo.

**Ciclo de vida del Apoyo (`Voter`).** Un Apoyo transita hasta 12 estados (`VoterStatus`): desde `pending_review` hasta `confirmed`/`voted`/`did_not_vote`, pasando por verificación contra censo/Registraduría/llamada, o rechazo por censo/fuera-de-alcance/cédula-no-encontrada. Las cédulas duplicadas reciben una secuencia y estado `DUPLICATE`; un `admin_campaign`/`super_admin` resuelve la disputa con la acción **"Reasignar dueño de duplicado"**, que hace una transferencia real de propiedad (no solo limpia una bandera) y queda auditada en `ValidationHistory`.

**Validación de censo y resolución de puesto de votación.** `PollingPlaceResolver` corre una cascada con guardia anti-downgrade: BD de la campaña → snapshot nacional (`NationalCensusRecord`, 216K filas indexadas por cédula) → intento en vivo contra la Registraduría. Los adaptadores en vivo (`RegistraduriaService`, `ConsultaCensoService`, `InfovotantesService`) implementan una interfaz común (`LiveSourceAdapter`) y delegan a un microservicio Python separado (ver abajo). La procedencia de cada resultado (`PollingPlaceSource`: live/db_reconstruction/snapshot/manual) queda visible como badge en toda la UI. Un job horario (`ReconcileLivePollingPlaces`) reintenta automáticamente los Apoyos en estado fallback, acotado y con un tope de intentos.

**Comunicación y seguimiento.** Encuestas configurables (`Survey`/`SurveyQuestion`, 5 tipos de pregunta), call center con cola y tracking de resultados (`CallAssignment`/`VerificationCall`), y mensajería SMS masiva vía la API de Hablame (incluye felicitaciones de cumpleaños automatizadas).

**Ejecución de Día D.** Registro de voto con evidencia obligatoria (foto + GPS) capturado en `VoteRecord`, prevención de doble-voto a nivel de base de datos (no solo validación de aplicación), y cierre de jornada vía `ElectionEvent` con logging estructurado.

**Jerarquía y metadata (v1.2, en progreso).** Un articulador (`area_coordinator`) gestiona un conjunto de coordinadores, un nivel jerárquico adicional sin anidamiento posterior, con autorización explícita por propiedad vía `CoordinatorPolicy`. Un catálogo de claves de metadata (`MetadataKey`, curado por superadmin) permite a cualquier superior asignar valores auditables a sus subordinados directos (`UserMetadataValue`, tabla append-only — cada asignación es una fila nueva, dando historial de auditoría gratis). Las fases 16-17 (aún pendientes) añaden la UI del catálogo y los filtros/exports por metadata en las tablas de Filament.

---

## Estructura de Carpetas

```
app/
  Filament/           Resources, Pages, Widgets — la capa de UI administrativa (5 paneles, 16+ recursos)
  Providers/Filament/ Un provider por panel: Admin, Reports, Coordinator, AreaCoordinator, Leader
  Models/             ~33 modelos Eloquent — identidad/jerarquía, campaña/territorio, Apoyo, censo, outreach, metadata
  Services/           Capa de lógica de negocio (~24 servicios): resolución de puesto de votación, SMS, call center, etc.
  Policies/           Autorización explícita por propiedad (CoordinatorPolicy, VoterPolicy, InvitationPolicy)
  Enums/              Estados y catálogos tipados (VoterStatus, PollingPlaceSource, UserRole, CampaignScope, ...)
  Http/Middleware/    Control de acceso y redirección por rol
  Console/Commands/   Imports de datos, jobs de reconciliación, comandos de backfill histórico
resources/views/livewire/   Componentes Volt organizados por rol (coordinator/, leader/, articulador/, public/)
routes/               web.php, api.php, console.php
database/             migrations, factories, seeders
tests/                Feature/, Unit/, Browser/ (Pest 4), Visual/ (regresión visual Playwright), E2E/ChromeDevTools/ (Playwright)
docs/                 Reglas de negocio vigentes y notas de deploy
.planning/            Estado vivo del proyecto (workflow GSD) — PROJECT.md, ROADMAP.md, STATE.md, REQUIREMENTS.md, fases
registraduria-service/  Microservicio Python independiente (ver abajo)
```

### Microservicio `registraduria-service/`

Un proceso Flask separado (Python + Playwright + 2captcha) que resuelve el reCAPTCHA de `wsp.registraduria.gov.co` y scrapea el resultado de puesto de votación. Expone tres rutas de consulta (Registraduría, Infovotantes, ConsultaCenso) más un endpoint de polling por `session_id`. Es el backend real detrás de los `LiveSourceAdapter` que Laravel invoca — se despliega y corre de forma independiente a la app principal.

---

## 🚀 Quick Start

### Requisitos
- PHP 8.4+
- Composer
- Node.js 18+

### Instalación

```bash
# 1. Clonar e instalar
git clone [repo-url] sigma-project
cd sigma-project
composer install
npm install

# 2. Configurar entorno
cp .env.example .env
php artisan key:generate

# 3. Base de datos
touch database/database.sqlite
php artisan migrate
php artisan colombia:import       # Importa 33 deptos + 1,123 municipios
php artisan db:seed

# 4. Compilar assets y lanzar
npm run build
php artisan serve
```

### Acceso

- **Panel Admin:** http://localhost:8000/admin
- **Panel Reportes:** http://localhost:8000/reports
- **Panel Coordinadores:** http://localhost:8000/coordinator
- **Panel Articuladores:** http://localhost:8000/articulador
- **Panel Líderes:** http://localhost:8000/leader

**Usuario por defecto:** ver `RoleSeeder`/`DatabaseSeeder` para credenciales.

---

## Stack Tecnológico

| Categoría | Tecnología |
|-----------|------------|
| **Backend** | Laravel 12, PHP 8.4, MySQL/SQLite |
| **Frontend** | Filament 4, Livewire 3, Volt, Flux UI, Tailwind CSS 4 |
| **Testing** | Pest 4, PHPUnit 12, Playwright (E2E + regresión visual) |
| **Autenticación** | Laravel Fortify (2FA incluido) |
| **Permisos** | Spatie Laravel Permission |
| **Servicios externos** | Hablame (SMS), Registraduría/Infovotantes/ConsultaCenso (vía microservicio Python) |
| **DevOps** | Laravel Herd, Vite, Pint |

---

## Comandos Artisan Destacados

```bash
# Importación de datos de referencia
php artisan colombia:import              # Territorio: departamentos + municipios
php artisan census:import-national       # Snapshot nacional del censo

# Reconciliación automática (también corren como jobs programados)
php artisan census:reconcile-live        # Reintenta lookups en vivo para Apoyos en fallback
php artisan census:reconcile-territory   # Revalida alcance territorial

# Backfills históricos (cada uno atado a un fix documentado)
php artisan census:backfill-live-status-desync
```

Ver `app/Console/Commands/` para el listado completo, incluyendo comandos de mantenimiento del servicio de captcha y pruebas de integración SMS.

---

## Testing

```bash
# Todos los tests
php -d memory_limit=512M artisan test

# Tests específicos
php artisan test --filter=VoterTest

# Con cobertura
php artisan test --coverage
```

- **`tests/Feature/`** — la mayoría de la cobertura, organizada por dominio (Articulador, Coordinator, Leader, Filament, Policies, Jobs, Middleware, Validation...)
- **`tests/Unit/`** — pruebas aisladas de lógica pura
- **`tests/Browser/`** — pruebas de navegador nativas de Pest 4
- **`tests/Visual/`** — regresión visual con Playwright (baselines por rol × flujo)
- **`tests/E2E/ChromeDevTools/`** — suite E2E con Playwright (call center, Día D, SMS, cierre de jornada, encuestas)

---

## 💻 Comandos Útiles de Desarrollo

```bash
npm run dev                    # Hot reload frontend
vendor/bin/pint --dirty        # Formatear solo archivos modificados
php artisan test --filter=X    # Tests específicos
npm run build                  # Compilar assets para producción
php artisan optimize           # Optimizar Laravel
```

---

## 📚 Documentación

| Documento | Descripción |
|-----------|-------------|
| **[.planning/PROJECT.md](.planning/PROJECT.md)** | Estado actual, requisitos validados/activos, decisiones clave — fuente de verdad viva |
| **[CHANGELOG.md](CHANGELOG.md)** | Historial de cambios por versión |
| **[.planning/MILESTONES.md](.planning/MILESTONES.md)** | Resumen de milestones ya enviados |
| **[CLAUDE.md](CLAUDE.md)** | Guidelines de desarrollo y convenciones del proyecto |
| **[docs/REGLAS_NEGOCIO.md](docs/REGLAS_NEGOCIO.md)** | Reglas de negocio vigentes |
| **[docs/deploy/docker-volumes.md](docs/deploy/docker-volumes.md)** | Volúmenes persistentes para deploy |

---

## Contribución

Este proyecto usa el workflow **GSD** (Get Shit Done) para planificación y ejecución estructurada por fases — ver `.planning/` para el roadmap, estado y planes activos.

1. Leer [CLAUDE.md](CLAUDE.md) para convenciones de código del proyecto.
2. Trabajar dentro de un flujo GSD (`/gsd:quick`, `/gsd:debug`, `/gsd:execute-phase`) — no editar directamente fuera de un plan salvo excepción explícita.
3. Escribir tests — todo cambio requiere cobertura.
4. Ejecutar `vendor/bin/pint --dirty` antes de finalizar.
5. Commit semántico: `feat(scope): descripción`.

---

**Desarrollado con ❤️ usando Laravel + Filament + Livewire**
