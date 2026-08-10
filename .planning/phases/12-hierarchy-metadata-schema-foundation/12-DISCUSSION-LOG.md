# Phase 12: Hierarchy & Metadata Schema Foundation - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-10
**Phase:** 12-hierarchy-metadata-schema-foundation
**Areas discussed:** Alcance del catálogo de metadata, Modelo de valores (sobrescribir vs. historial), Precisión de valores numéricos

---

## Alcance del catálogo de metadata

| Option | Description | Selected |
|--------|-------------|----------|
| Global — una sola lista para todo SIGMA | Coincide con cómo lo describió el cliente y con el patrón existente de catálogos de referencia (Gremio, Subcategoria — ninguno aislado por campaña). Un superadmin gestiona una sola tabla `metadata_keys` sin `campaign_id`. | ✓ |
| Por campaña — cada campaña tiene su propio catálogo | `metadata_keys` tendría `campaign_id`; más flexible pero más complejo, ninguna llave compartible entre campañas. | |

**User's choice:** Global — una sola lista para todo SIGMA (recommended option)
**Notes:** No pushback; matched the client's original framing.

---

## Modelo de valores (sobrescribir vs. historial)

| Option | Description | Selected |
|--------|-------------|----------|
| Historial completo, append-only | Cada asignación crea una fila nueva en `user_metadata_values`; el valor "actual" es la más reciente por (usuario, llave). Auditoría nativa gratis (META-05), sin migraciones futuras si se necesita histórico. | ✓ |
| Sobrescribir — una fila por usuario+llave | Constraint único (usuario, llave); reasignar hace UPDATE. Más simple de filtrar/ordenar pero pierde el valor anterior salvo que se audite aparte. | |

**User's choice:** Historial completo, append-only (recommended option)
**Notes:** No pushback.

---

## Precisión de valores numéricos (dinero)

| Option | Description | Selected |
|--------|-------------|----------|
| Entero | Ejemplos del cliente son enteros en pesos (20000, 100000, 7000000, 200000, 900000), sin centavos. Simplifica orden numérico. | |
| Decimal | Permite centavos/fracciones si se necesitan alguna vez, a costo de mayor complejidad de columna. | |

**User's choice:** Decimal (free-text override — user rejected the binary framing and asked for decimal specifically, "por si las moscas" / just in case a fractional value is ever needed). User also flagged that the two catalog value types worth calling out explicitly are string and decimal.
**Notes:** User's exact words: "es que ojo como puedes verlo puede ser cualquier tipo de valor, pero si vamos a tiparlo hay string y decimal, por si las moscas."

---

## Claude's Discretion

- Exact migration/model/relation naming — mirror `coordinator_user_id`/`User::coordinator()`/`User::leaders()` naming convention exactly.
- Whether `canAccessPanel()` is wired for `area_coordinator` in this phase or deferred to Phase 14/15 (phase is schema-only, "no UI yet").
- `metadata_keys` soft-deactivate column shape (`active` boolean vs `deactivated_at` timestamp) — hard-delete already prohibited by REQUIREMENTS.md Out of Scope.

## Deferred Ideas

- Point-in-time/effective-dated metadata queries — already tracked as META-07 (v2, REQUIREMENTS.md).
- Metadata rollup/aggregation dashboards — already tracked as META-08 (v2, REQUIREMENTS.md).
