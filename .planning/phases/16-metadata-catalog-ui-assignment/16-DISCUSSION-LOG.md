# Phase 16: Metadata Catalog UI & Assignment - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-10
**Phase:** 16-metadata-catalog-ui-assignment
**Areas discussed:** Alcance de subordinado directo, Ubicación de la UI de asignación, Asignación masiva, Validación y visualización por tipo

---

## Alcance de Subordinado Directo

### Líder handling

| Option | Description | Selected |
|--------|-------------|----------|
| Excluir líder por completo | No metadata assignment UI appears anywhere in the líder panel — líder has no eligible User subordinates today | ✓ |
| Incluir líder con estado vacío | Líder sees the UI but with an empty "no subordinates" state, future-proofed for potential sub-líderes | |
| Redefinir subordinado de líder = sus Apoyos | Allow assigning metadata to Voter records, not just Users — would require new schema | |

**User's choice:** Excluir líder por completo (recommended)
**Notes:** Confirmed the model has no `User`-type FK pointing to líder — only `registeredVoters()`, which is a different entity outside the metadata system's scope.

### Superadmin/admin_campaign scope

| Option | Description | Selected |
|--------|-------------|----------|
| Cualquier usuario de la campaña activa | No hierarchy restriction — full assignment visibility, consistent with existing unrestricted admin access | ✓ |
| Solo tope de jerarquía (articuladores + huérfanos) | Restricted to top-of-hierarchy users; everyone else assigned via cascading through the hierarchy | |

**User's choice:** Cualquier usuario de la campaña activa (recommended)

---

## Ubicación de la UI de Asignación

### Where individual assignment lives

| Option | Description | Selected |
|--------|-------------|----------|
| Sección/tab dentro del formulario de edición existente | New tab/section inside EditCoordinator/EditLeader/EditAreaCoordinator | ✓ |
| Acción dedicada en la tabla (modal) | A per-row "Assign Metadata" button opening a modal, no full edit-form navigation | |
| Recurso Filament separado | A standalone, log-style "Metadata Assignments" screen decoupled from role edit forms | |

**User's choice:** Sección/tab dentro del formulario de edición existente (recommended)

### Which panels need assignment capability

| Option | Description | Selected |
|--------|-------------|----------|
| Panel Coordinador | Coordinador assigns metadata to their own líderes | ✓ |
| Panel Articulador | Articulador assigns metadata to their own coordinadores | ✓ |

**User's choice:** Both selected (both recommended)
**Notes:** Panel Admin already has this capability implicitly since superadmin/admin_campaign gets unrestricted access per D-02 — not offered as a separate option since it's a given.

---

## Asignación Masiva (META-04)

### Bulk mechanism

| Option | Description | Selected |
|--------|-------------|----------|
| Bulk action en tabla: mismo valor para todos | Select multiple rows, choose one key + one value, applies identically | ✓ |
| Grid/formulario con valor distinto por fila | Spreadsheet-style form, different value per selected user in one submit | |

**User's choice:** Bulk action en tabla: mismo valor para todos (recommended)
**Notes:** Matches the literal wording of META-04 ("el mismo valor de metadata a varios subordinados"). CoordinatorsTable already has a BulkAction precedent to mirror.

### Multi-key support in one bulk action

| Option | Description | Selected |
|--------|-------------|----------|
| Una llave por acción masiva | Simple: pick key, value, targets; repeat for a second key | ✓ |
| Múltiples llaves en una sola acción | Repeater-style form for several key+value pairs in one bulk submit | |

**User's choice:** Una llave por acción masiva (recommended)

---

## Validación y Visualización por Tipo

### Numeric validation approach

| Option | Description | Selected |
|--------|-------------|----------|
| TextInput::numeric() con 2 decimales | Filament validates numeric input, allows decimals, still persisted as string | ✓ |
| Solo enteros, sin decimales | Simpler, but contradicts Phase 12's explicit intent to leave room for fractional values | |

**User's choice:** TextInput::numeric() con 2 decimales (recommended)

### Current value vs history display

| Option | Description | Selected |
|--------|-------------|----------|
| Solo valor actual, con acción para reasignar | Simple view: key + current value + who/when; reassign inserts a new row (append-only) but UI only shows latest | ✓ |
| Valor actual + timeline de historial expandible | Same as above plus an expandable accordion showing every past assignment for that key/user | |

**User's choice:** Solo valor actual, con acción para reasignar (recommended)
**Notes:** Full history stays queryable in DB regardless (Phase 12's append-only design) — this only affects what's rendered in this phase's UI.

---

## Claude's Discretion

- Exact naming of the new "direct subordinate" resolver method on `User`
- Form field type per metadata key type: `Select` for `select` (populated from `options` JSON), `DatePicker` for `date`, plain `TextInput` for `text` — follows directly from Phase 12's schema, not discussed as an open question
- Exact Filament resource naming for the metadata-key catalog (mirroring `GremioResource`)
- Navigation placement details (ordering, icon, label wording) within each panel

## Deferred Ideas

- Point-in-time/effective-dated metadata queries and expandable history view — already tracked as META-07 in REQUIREMENTS.md v2
- Metadata rollup/aggregation dashboards — already tracked as META-08 in REQUIREMENTS.md v2
- Extending metadata assignment to Voters/Apoyos (to give líder something to assign) — explicitly considered and rejected for this phase; would need new schema
