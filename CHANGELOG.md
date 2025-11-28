# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Versionamiento Semántico](https://semver.org/lang/es/).

## [Unreleased]

### Added
- Implementación completa de Pest Browser Testing (v4.1.1) para tests visuales
- Tests browser para flujo completo de votación en Día D (5 tests)
- Tests browser para gestión de eventos electorales (11 tests)
- Instalación y configuración de Playwright para testing en múltiples navegadores
- Sistema de ElectionEvent para gestionar simulacros y eventos reales
- Modelo VoteRecord para evidencia electoral detallada
- Middleware IsElectionDay para validar acceso durante eventos activos
- Página ManageElectionEvents para gestión centralizada de eventos
- Soporte para múltiples simulacros con datos separados
- Integración completa User-Voter en sistema de roles

### Changed
- Actualizado tests/Pest.php para incluir directorio Browser
- Mejorados selectores CSS en tests browser para componentes de Filament
- Optimizados tiempos de ejecución de tests visuales

### Fixed
- Corregido problema de mixed content en producción (HTTPS)
- Resueltos 4 tests fallando relacionados con registration skip y formato SMS
- Corregido manejo de selectores en modales de Filament Actions
- Resuelto problema de memoria en ejecución de tests

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
