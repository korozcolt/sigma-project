# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Versionamiento Semántico](https://semver.org/lang/es/).

## [Unreleased]

## [1.2.0] - 2026-08-10

Esta versión consolida todo el trabajo acumulado desde `0.8.2` que nunca se cerró en el changelog, incluyendo dos milestones completos (v1.0, v1.1) y el avance actual del milestone v1.2. A partir de aquí el número de versión del changelog queda alineado con los tags de milestone del proyecto (`v1.0`, `v1.1`, ahora `v1.2` en progreso).

### Added

**Aislamiento multi-campaña (base, previo a v1.0):**
- Campaign Context con selector en topbar para `super_admin`.
- Scopes globales de campaña en modelos multi-campaña, con enforcement en creación/updates y gate global contra accesos cruzados.
- Tests de aislamiento multi-campaña; suite Visual E2E en navegador real con Playwright (baselines por rol y flujo) y seeder `VisualE2ESeeder`.
- Estado `VoterStatus::REJECTED_OUT_OF_SCOPE` y validación automática de alcance territorial (`VoterTerritoryScope`, job `census:reconcile-territory` cada hora).
- Nuevo logo de SIGMA en todo el sistema.

**v1.0 — MVP Hardening (30/30 requisitos, 892/892 tests):**
- Renombre completo Votante→Apoyo con manejo de cédulas duplicadas, exclusión de líderes/coordinadores como Apoyo de sí mismos, clasificación Gremio/Subcategoría, e importación masiva CSV.
- Seis widgets de reportería de Apoyos (rankings, cobertura, rechazos, duplicados) con exportación CSV.
- Cierre de brechas de una auditoría completa: denegaciones de autorización con motivo explícito, seguridad de campaña en jobs, validación de censo con feedback en UI, dashboards con scope de propiedad, prevención de doble-voto a nivel de BD, cierre de jornada electoral con cola real y logging estructurado.
- OTP por SMS para creación de líderes; kill switch de mantenimiento con auto-bypass para Super Admin.

**v1.1 — Consulta de Puesto de Votación Resiliente:**
- Snapshot nacional del censo (216K filas) importado a una tabla indexada por cédula, enriquecida con divipol.
- `PollingPlaceResolver` unificado: cascada BD-de-campaña → snapshot → intento en vivo, con guardia anti-downgrade.
- `wsp.registraduria.gov.co` validado como fuente en vivo end-to-end (29/30 intentos reales exitosos).
- UI de procedencia del dato (badge, force-refresh restringido por rol, widget de triage de fallback), verificada humanamente en vivo.
- Job de reconciliación automática, horario, acotado y auditable.

**v1.2 — Articuladores + Metadata de Usuario (en progreso, fases 12-15 de 6):**
- Nuevo rol `articulador` (`area_coordinator`), un nivel jerárquico sobre coordinador, sin límite de coordinadores y sin anidamiento adicional.
- Autorización explícita (`CoordinatorPolicy`) y resolución de equipo transitivo en widgets/exports existentes.
- Recurso admin dedicado (`AreaCoordinatorResource`) para que super_admin/admin_campaign gestione articuladores y asigne/reasigne coordinadores (nuevos o ya existentes) vía un Select en el formulario del coordinador.
- Panel de auto-gestión propio para el articulador (`AreaCoordinatorPanelProvider`, espejo del panel de coordinador): listar, crear y editar sus propios coordinadores, con navegación completa en el panel Filament y el sidebar compartido.

### Changed
- Recursos, filtros y formularios de Filament alineados al contexto de campaña activo; widgets y estadísticas ahora usan la campaña seleccionada.
- Eliminado el enforcement de "una sola campaña activa".

### Fixed
- Aislamiento por campaña consistente en listados y exports críticos; error 500 en `/admin` por recursión en scope de membresía de campaña.
- Desincronización entre `status` y `polling_place_source` en Voters causada por dos cron jobs independientes actualizando cada campo por separado. Ver `.planning/debug/resolved/status-polling-place-source-desync.md`.
- `PollingPlaceResolver::persist()` nunca escribía `polling_place_id`, inflando el conteo de "apoyos sin puesto asignado". Ver `.planning/debug/resolved/polling-place-id-not-persisted-by-resolver.md`.
- Numeración de ranking en "Ranking de Puestos de Votación" se reseteaba en cada página. Ver `.planning/debug/resolved/top-polling-places-rank-resets-per-page.md`.
- Cédulas de menos de 10 dígitos (comunes en Colombia, 6-11 dígitos válidos) eran rechazadas al crear líderes/apoyos. Ver `.planning/debug/resolved/document-number-digits-exact-10-too-strict.md`.
- Validación de alcance territorial ignoraba el puesto de votación real ya resuelto. Ver `.planning/debug/resolved/voter-territory-scope-ignores-resolved-polling-place.md`.
- Error 500 al ver o listar apoyos con ciertos estados (`match` no exhaustivo sobre `VoterStatus`). Ver `.planning/debug/resolved/voter-status-match-missing-cases.md`.

Ver `.planning/MILESTONES.md` para el detalle completo de v1.0 y v1.1, y `.planning/PROJECT.md` para el estado y decisiones vigentes de v1.2.

## [0.8.2] - 2025-11-27

### Added
- Sistema completo de VoterResource en Filament
- Sistema completo de UserResource en Filament
- Tests para UserObserver y flags de clasificación (22 tests nuevos)

### Fixed
- Configuración de HTTPS y trust proxies para producción

## [0.8.1] - 2025-11-25

### Added
- Sistema completo de encuestas (Survey System)
- Dashboard interactivo con estadísticas
- Call Center completo con workflow de llamadas

### Changed
- Idioma del sistema configurado globalmente en español
- Actualización de documentación (PROGRESO.md, README.md)

## [0.7.0] - 2025-11-20

### Added
- Sistema de mensajería completo (MessageResource, MessageTemplateResource, MessageBatchResource)
- Traducción completa al español
- Módulo de cumpleaños y mensajería automatizada
- Call Center Workflow completo
- Sistema de llamadas de verificación

### Changed
- Actualización de badges en documentación a estilo for-the-badge

## [0.6.0] - 2025-11-15

### Added
- Sistema completo de encuestas (FASE 6.1)
- Módulo de cumpleaños (FASE 6.2)
- Sistema de Call Center (FASE 6.3)
- Workflow completo de llamadas de verificación

## [0.5.0] - 2025-11-10

### Added
- Sistema de validación y censo electoral completo (FASE 5)
- VoterResource con gestión completa de votantes
- Integración con censo electoral
- Sistema de validación de datos de votantes

## [0.1.0] - 2025-11-01

### Added
- Configuración inicial del proyecto
- Estructura base de Laravel 12
- Integración con Filament v4
- Configuración de base de datos
- Modelos base: User, Campaign, Voter
- Sistema de autenticación con Laravel Fortify
- Panel de administración con Filament
