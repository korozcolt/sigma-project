# Phase 17: Filter/Sort/Export Surfaces - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-11
**Phase:** 17-filter-sort-export-surfaces
**Areas discussed:** UX del filtro de metadata, Semántica del filtro por tipo, Columnas de metadata en la tabla, Alcance y columnas de los exports

---

## UX del Filtro de Metadata

| Option | Description | Selected |
|--------|-------------|----------|
| Filtro genérico en cascada | Un solo filtro 'Metadata': Select de llave activa → campo de valor según tipo. Panel no crece con el catálogo; solo una llave a la vez. | ✓ |
| Un filtro por llave activa | Filter::make() distinto por cada llave activa. Permite combinar varias llaves (AND), pero el panel crece sin límite. | |

**User's choice:** Filtro genérico en cascada (recomendado)
**Notes:** Follow-up asked explicitly whether single-condition-at-a-time was acceptable (no AND across different metadata keys) — confirmed yes, sufficient for FILT-01's literal wording.

---

## Semántica del Filtro por Tipo

| Option | Description | Selected |
|--------|-------------|----------|
| Exacto para todos los tipos | numeric/text/date/select — todos filtran por igualdad exacta. Rango queda fuera de esta fase. | ✓ |
| Rango para numeric/date, exacto para select/text | numeric/date ganan campos desde/hasta; select exacto, text "contiene". Más superficie de UI. | |

**User's choice:** Exacto para todos los tipos (recomendado)
**Notes:** None beyond selection.

---

## Columnas de Metadata en la Tabla

| Option | Description | Selected |
|--------|-------------|----------|
| Columna por llave activa, toggleable | TextColumn generada por llave activa, sortable (numérico si type=numeric), toggleable oculta por defecto. | ✓ |
| Orden solo desde el filtro, sin columnas nuevas | No se agregan columnas; orden vía control separado fuera del sistema normal de columnas. | |

**User's choice:** Columna por llave activa, toggleable (recomendado)
**Notes:** None beyond selection.

---

## Alcance y Columnas de los Exports

| Option | Description | Selected |
|--------|-------------|----------|
| AnnotatorsExport + WitnessesExport | Únicos exports existentes que parten de la tabla Users, accionables desde ListUsers. No se crea export genérico nuevo. | ✓ |
| Solo Coordinators + Leaders, ninguno de Users | AnnotatorsExport/WitnessesExport quedan fuera del alcance de FILT-03. | |

**User's choice:** AnnotatorsExport + WitnessesExport (recomendado)
**Notes:** CoordinatorsExport and LeadersExport were already confirmed in scope prior to this question (explicitly named in FILT-03's requirement text).

Follow-up — export column set:

| Option | Description | Selected |
|--------|-------------|----------|
| Todas las llaves activas del catálogo | Columnas consistentes export tras export; puede haber columnas en blanco si una llave no tiene ningún valor asignado en las filas exportadas. | ✓ |
| Solo llaves con al menos un valor asignado en el export | Sin columnas vacías, pero el conjunto de columnas cambia entre descargas según qué filas salgan. | |

**User's choice:** Todas las llaves activas (recomendado)
**Notes:** None beyond selection.

---

## Claude's Discretion

- Exact SQL approach for resolving "current value per user per metadata key" at table-query scale.
- Exact column key/id naming for dynamically generated per-metadata-key TextColumns, and column ordering.
- Whether the filter and dynamic columns share one underlying helper class (naming/location).
- Export column header wording for metadata columns.

## Deferred Ideas

- Combining multiple metadata-key filter conditions simultaneously (AND across keys).
- Range filtering (≥/≤) for numeric/date-typed metadata keys.
- Extending metadata filter/sort to the Volt-based self-service panels (coordinador líderes list, articulador coordinadores list).
- A generic "export all users" CSV/xlsx (role-agnostic) — does not exist today, not created in this phase.
- Metadata columns/filter/sort for Top-N reporting exports (TopCoordinatorsExport, TopLeadersExport, etc.).
