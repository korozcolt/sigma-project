# Phase 15: Articulador Self-Service Panel - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-10
**Phase:** 15-articulador-self-service-panel
**Areas discussed:** CRUD pattern, OTP verification, Panel scope, Invitation link, Reassignment field

---

## CRUD pattern

| Option | Description | Selected |
|--------|-------------|----------|
| Páginas Volt (como coordinador) | Mirror exacto de coordinator.leaders / create-leader / edit-leader — rutas Volt bajo /articulador con el layout app normal, fuera del panel Filament. Es el patrón real que usa "auto-gestión" hoy. | ✓ |
| Filament Resource en el panel | Nuevo CoordinatorResource dentro de AreaCoordinatorPanelProvider (mirror de AreaCoordinatorResource de Fase 14, scoped a "mis propios coordinadores"). | |

**User's choice:** Páginas Volt (como coordinador)
**Notes:** Scouting the codebase revealed the coordinador's actual "self-service" experience for managing leaders is Volt pages under `/coordinator`, not a Filament Resource — `CoordinatorPanelProvider` only carries Dashboard/DiaD. This matches the real established pattern rather than a literal reading of ROADMAP.md's "AreaCoordinatorPanelProvider" phrase.

---

## OTP verification

| Option | Description | Selected |
|--------|-------------|----------|
| Sí, requerir OTP | Mirror exacto del flujo de creación de líder: enviar código SMS, verificar antes de guardar. | |
| No, sin OTP | Mirror del formulario admin AreaCoordinatorForm de Fase 14 (sin OTP) — más simple. | ✓ |

**User's choice:** No, sin OTP
**Notes:** Diverges intentionally from the leader-creation flow. `IdentityLookupService` document-number autofill still applies (separate concern from OTP).

---

## Panel scope

| Option | Description | Selected |
|--------|-------------|----------|
| Dashboard + Día D | Mirror exacto de CoordinatorPanelProvider: Dashboard, página Día D, widgets (CampaignStatsOverview, TerritorialDistributionChart, TopLeadersTable). | ✓ |
| Solo gestión de coordinadores | Panel mínimo — nada de Dashboard/Día D/widgets. | |

**User's choice:** Dashboard + Día D
**Notes:** These widgets already resolve an articulador's transitive team correctly per Phase 13 (AUTHZ-01).

---

## Invite link

| Option | Description | Selected |
|--------|-------------|----------|
| No, solo creación directa | El criterio de éxito de Fase 15 en ROADMAP.md solo pide "crea un nuevo coordinador vía un formulario" — no menciona enlace de invitación. | ✓ |
| Sí, incluir enlace de invitación | Mirror completo del flujo del coordinador (generateLeaderInvitationLink + vista pública register-leader). | |

**User's choice:** No, solo creación directa
**Notes:** Deferred as a future idea if operational need arises.

---

## Reassignment (area_coordinator_user_id field on self-service edit)

| Option | Description | Selected |
|--------|-------------|----------|
| No, campo oculto/bloqueado | El coordinador queda fijo al articulador que lo creó — reasignar solo desde el panel admin. | ✓ |
| Sí, editable | El articulador puede reasignar su propio coordinador a otro articulador o dejarlo sin articulador. | |

**User's choice:** No, campo oculto/bloqueado
**Notes:** Prevents an articulador from reassigning coordinadores away from or into their own team via the self-service surface; admin-only via existing `CoordinatorForm` Select (Phase 14).

---

## Claude's Discretion

- Exact form field set/layout for create/edit Volt views — mirror `create-leader.blade.php`/`edit-leader.blade.php` adapted to coordinador fields.
- Reuse `IdentityLookupService` name-lock UX and municipality/neighborhood cascading selects as-is.
- Query scoping for "my own coordinadores": `area_coordinator_user_id = auth()->id()`, layered with existing `CoordinatorPolicy`.
- List page pagination/search mirrors `coordinator/leaders.blade.php` exactly.

## Deferred Ideas

- Shareable self-registration invitation link for coordinadores (mirroring `generateLeaderInvitationLink` + public `register-leader`).
- Self-service reassignment of a coordinador's articulador (kept admin-only).
