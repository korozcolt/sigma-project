# Phase 7: Source-Flag Schema & Resolution Audit Trail - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-24
**Phase:** 07-source-flag-schema-resolution-audit-trail
**Areas discussed:** Source enum values, System actor for headless writes, Audit row contents, Data type for resolved_via

---

## Source Enum Values

| Option | Description | Selected |
|--------|-------------|----------|
| live, db_reconstruction, snapshot, manual | 4 cases; `live` folds in both direct live lookups and Redis-cached results, agnostic to which live adapter answered | ✓ |
| live, cache, snapshot, manual | 4 cases; treats cache as a separate, distinct origin from live | |

**User's choice:** live, db_reconstruction, snapshot, manual
**Notes:** User raised a free-text clarification mid-discussion: `wsp.registraduria.gov.co/censo/consultar/` should be treated as ANOTHER live-source alternative (alongside the existing dead endpoints), not a separate enum category. Confirmed back with the user: `live` is adapter-agnostic — it covers any live adapter that answers (current endpoints, wsp if validated in Phase 9, and any future additions) without the enum distinguishing which one. User confirmed this understanding as correct.

---

## System Actor for Headless Writes

| Option | Description | Selected |
|--------|-------------|----------|
| resolved_by nullable | FK to users, nullable; null = automated/headless change, paired with resolved_via='reconciliation' | ✓ |
| Seed a 'system'/bot user | Real seeded user row, keeps FK non-null like ValidationHistory.validated_by | |

**User's choice:** resolved_by nullable
**Notes:** This also resolves the RECON-03 blocker previously flagged in STATE.md as needing a decision before Phase 11 planning.

---

## Audit Row Contents

| Option | Description | Selected |
|--------|-------------|----------|
| Snapshot polling_place_id + table_number resolved at that time | Row stores the actual resolved value, not just the source label | ✓ |
| Only the source change (no value snapshot) | Minimal, matches ValidationHistory's own minimalism (doesn't store full voter state either) | |

**User's choice:** Snapshot polling_place_id + table_number resolved at that time

---

## Data Type for `resolved_via`

| Option | Description | Selected |
|--------|-------------|----------|
| Plain string, matching validation_type | Exact consistency with the ValidationHistory precedent being mirrored | ✓ |
| New backed enum (e.g. ResolutionVia) | More type-safe, consistent with VoterStatus/PollingPlaceSource, but breaks symmetry with the closest precedent | |

**User's choice:** Plain string, matching validation_type

---

## Claude's Discretion

- Whether `PollingPlaceSource` implements Filament's HasColor/HasDescription/HasIcon/HasLabel interfaces now vs. deferring to Phase 10 — leaning toward implementing now since it's low-cost and matches the VoterStatus convention.
- Exact migration nullability/defaults for `polling_place_source`/`polling_place_resolved_at` on existing voter rows.
- Index strategy on `voters.polling_place_source` (plain vs. composite with campaign_id).

## Deferred Ideas

None — discussion stayed within Phase 7 scope.
