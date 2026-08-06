# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Versionamiento Semántico](https://semver.org/lang/es/).

## [Unreleased]

### Added
- Campaign Context con selector en topbar para `super_admin`.
- Scopes globales de campaña en modelos multi-campaña.
- Enforcements en creación/updates para fijar `campaign_id` desde el contexto.
- Gate global para bloquear accesos cruzados por campaña.
- Tests de aislamiento multi-campaña.
- Suite Visual E2E en navegador real con Playwright (baselines por rol y flujo).
- Seeder `VisualE2ESeeder` para crear usuarios y datos mínimos de pruebas visuales.

### Changed
- Recursos, filtros y formularios de Filament alineados al contexto de campaña.
- Widgets y páginas de estadísticas ahora usan campaña seleccionada.
- Eliminado el enforcement de “una sola campaña activa”.

### Added
- Estado `VoterStatus::REJECTED_OUT_OF_SCOPE` y validación automática de alcance territorial (`VoterTerritoryScope`, job/comando `census:reconcile-territory` cada hora) — marca/revierte apoyos fuera del municipio/departamento definido para su campaña, usando el puesto de votación resuelto (Registraduría/censo) como fuente de verdad en vez del campo `municipality_id` del propio apoyo. Ver `.planning/debug/resolved/voter-territory-scope-ignores-resolved-polling-place.md`.
- Nuevo logo de SIGMA en todo el sistema (favicons, panel Filament, login/registro, sidebars).

### Fixed
- Aislamiento por campaña consistente en listados y exports críticos.
- Error 500 en `/admin` por recursión en scope de membresía de campaña.
- Desincronización entre `status` y `polling_place_source` en Voters: un apoyo podía quedar con estado "Pendiente de Revisión" mientras su fuente de puesto de votación ya era "En Vivo", porque dos cron jobs independientes (`census:reconcile-live` y `census:reconcile-validation`) actualizaban cada campo por separado sin coordinarse. `ReconcileFallbackPollingPlaces` ahora sincroniza `status` en la misma pasada (sin llamadas nuevas al resolver/2captcha); se agregó `polling_place_source` al flujo de registro manual de apoyos; y se agregó el comando `census:backfill-live-status-desync` para corregir registros históricos afectados sin ninguna consulta pagada. Ver `.planning/debug/resolved/status-polling-place-source-desync.md`.
- `PollingPlaceResolver::persist()` nunca escribía `polling_place_id` (solo `polling_place_source`), inflando el conteo de "apoyos sin puesto asignado" y subcontando el ranking de puestos de votación. Ver `.planning/debug/resolved/polling-place-id-not-persisted-by-resolver.md`.
- Numeración de ranking en "Ranking de Puestos de Votación" se reseteaba en cada página (el #1 real solo debía aparecer una vez, no repetirse por página). Ver `.planning/debug/resolved/top-polling-places-rank-resets-per-page.md`.
- Cédulas de menos de 10 dígitos (comunes en Colombia, 6-11 dígitos válidos) eran rechazadas al crear líderes/apoyos; mensaje de error también mostraba el nombre del campo sin traducir. Ver `.planning/debug/resolved/document-number-digits-exact-10-too-strict.md`.
- Validación de alcance territorial ignoraba el puesto de votación real ya resuelto, comparando contra un campo desactualizado del propio apoyo. Ver `.planning/debug/resolved/voter-territory-scope-ignores-resolved-polling-place.md`.
- Error 500 al ver o listar apoyos con ciertos estados (`match` no exhaustivo sobre `VoterStatus` en la vista de detalle y en la tabla principal). Ver `.planning/debug/resolved/voter-status-match-missing-cases.md`.

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
