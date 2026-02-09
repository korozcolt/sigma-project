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

### Fixed
- Aislamiento por campaña consistente en listados y exports críticos.
- Error 500 en `/admin` por recursión en scope de membresía de campaña.

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
