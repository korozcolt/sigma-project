# Phase 10: Operator Provenance & Fallback Controls - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-25
**Phase:** 10-operator-provenance-fallback-controls
**Areas discussed:** SRC-04 scope, SRC-01 badge placement, SRC-05 filter design, cost/role control on re-check

---

## SRC-04 — Manual Re-check Scope

| Option | Description | Selected |
|--------|-------------|----------|
| Already satisfies as-is, just formalize | Existing EditVoter button counts | ✓ |
| Add to ViewVoter (read-only view) | New Action needed there too | |
| Add as table row action | Re-check without opening any form | |

**User's choice:** Existing button already satisfies SRC-04 as written.

---

## SRC-01 — Badge Placement

| Option | Description | Selected |
|--------|-------------|----------|
| Table column | Visible in VotersTable list | ✓ |
| View page (infolist) | Next to existing "Fuente de Última Validación" pattern | ✓ |
| Edit form | Near document_number field | ✓ |

**User's choice:** All three locations.

**Follow-up — freshness timestamp:**

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, alongside badge in all 3 | Same as other date columns in the table | ✓ |
| Only in view page | Compact elsewhere | |
| No, badge only | Minimal SRC-01 scope | |

**User's choice:** Yes, in all three locations.

---

## SRC-05 — Triage Filter Design

| Option | Description | Selected |
|--------|-------------|----------|
| SelectFilter, 4 exact sources | Granular, matches existing status filter pattern | ✓ |
| Simple live/fallback toggle | Groups 3 non-live sources together | |
| Both | Toggle + granular filter | |

**User's choice:** SelectFilter with the 4 exact source values.

**Follow-up — dashboard widget:**

| Option | Description | Selected |
|--------|-------------|----------|
| No widget for now | Table filter is enough | |
| Yes, add a count widget | Proactive visibility for coordinator/admin | ✓ |

**User's choice:** Yes, add a fallback-count widget.

---

## Cost/Role Control on Re-check

| Option | Description | Selected |
|--------|-------------|----------|
| Leave open to everyone, as today | No new restriction | |
| Restrict to admin/coordinator | Budget-conscious roles only can force a paid lookup | ✓ |

**User's choice:** Restrict to admin/coordinator (implementation note: super_admin included per existing codebase convention of always pairing admin-level gates with super_admin — not asked separately, inferred from `EditVoter.php`'s `reassignDuplicateOwner` pattern).

---

## Claude's Discretion

- Exact column order / field placement in VotersTable and VoterForm.
- Exact wording of the new StatsOverviewWidget's Stat label/description.
- Whether the new SelectFilter passes `PollingPlaceSource::class` directly or an explicit options array.

## Deferred Ideas

- Bulk re-check (WIDGET-03) — already v2, not touched.
- Reconciliation-queue-depth widget (WIDGET-01) — already v2, distinct from the simpler count widget built here.
- Per-record reconciliation status narrative — already Out of Scope in REQUIREMENTS.md.
