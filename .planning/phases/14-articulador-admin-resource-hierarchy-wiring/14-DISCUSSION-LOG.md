# Phase 14: Articulador Admin Resource & Hierarchy Wiring - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-10
**Phase:** 14-articulador-admin-resource-hierarchy-wiring
**Areas discussed:** AreaCoordinatorResource form fields, Selector coordinador→articulador UX, Alcance de campaña del selector, Tabla de listado de AreaCoordinatorResource

---

## AreaCoordinatorResource — campos del formulario

| Option | Description | Selected |
|--------|-------------|----------|
| Espejo de CoordinatorForm sin "también será líder" | Mismas secciones (Personal, Contacto, Ubicación, Acceso); se quita el toggle porque un articulador no es líder | ✓ |
| Versión reducida | Solo nombre, email, documento, contraseña — sin ubicación/teléfono | |

**User's choice:** Espejo de CoordinatorForm sin "también será líder"
**Notes:** Confirmado como el recomendado — consistencia de UX con el resto del admin panel prevaleció sobre simplificar el formulario.

---

## Selector coordinador→articulador — UX de asignación

| Option | Description | Selected |
|--------|-------------|----------|
| Selector simple en CoordinatorForm | Campo Select "Articulador" en el form de crear/editar coordinador, mismo patrón que el Select de municipio | ✓ |
| Acción dedicada de "Reasignar articulador" | Como "Reasignar dueño de duplicado" — Action separada en tabla/vista | |
| Ambas: selector + bulk-action | Selector individual + bulk-action de reasignación masiva en CoordinatorsTable | |

**User's choice:** Selector simple en CoordinatorForm
**Notes:** Bulk-reassignment considerado y explícitamente descartado para esta fase (deferred idea).

**Follow-up — ¿Opcional u obligatorio?**

| Option | Description | Selected |
|--------|-------------|----------|
| Opcional / nullable | Coincide con ARTIC-03; FK ya es nullable desde Fase 12 | ✓ |
| Obligatorio | Todo coordinador nuevo debe tener articulador desde su creación | |

**User's choice:** Opcional / nullable

---

## Alcance de campaña del selector de articuladores

| Option | Description | Selected |
|--------|-------------|----------|
| Filtrado a la campaña activa/del coordinador | Mismo patrón que el Select de municipio — solo articuladores de la campaña activa | ✓ |
| Sin filtrar | Todos los articuladores del sistema, sin importar campaña | |

**User's choice:** Filtrado a la campaña activa/del coordinador
**Notes:** Consistente con el aislamiento estricto de campaña del proyecto y con AUTHZ-03 (Fase 13).

---

## Tabla de listado de AreaCoordinatorResource

| Option | Description | Selected |
|--------|-------------|----------|
| Con contador de coordinadores asignados | Columna "Coordinadores" con conteo (withCount) | ✓ |
| Espejo exacto de CoordinatorsTable, sin contador | Mismas columnas que la tabla de coordinadores, sin nada específico de jerarquía | |

**User's choice:** Con contador de coordinadores asignados

---

## Claude's Discretion

- Nombres de clases/estructura de directorio para `AreaCoordinatorResource` (mirroring `Coordinators/`)
- Lógica de `afterCreate()` para asignación de rol + campaña (mirroring `CreateCoordinator`)
- `relationship()` vs `Select::make(...)->options(...)` para el campo articulador en `CoordinatorForm`
- Label/icono/orden de navegación del nuevo recurso en el grupo "Gestión"

## Deferred Ideas

- Acceso de panel propio del articulador (`canAccessPanel()` wiring) — Fase 15
- Bulk-reassignment action — considerado, no elegido para esta fase
- Visibilidad de filas de articulador en `TopCoordinatorsTable`/`ApoyosLideresCoordinadoresTable`/`TerritorialOwnershipTable` — deferred desde Fase 13, sigue deferred hasta Fase 15
