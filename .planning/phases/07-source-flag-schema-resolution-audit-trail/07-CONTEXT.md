# Phase 7: Source-Flag Schema & Resolution Audit Trail - Context

**Gathered:** 2026-07-24
**Status:** Ready for planning

<domain>
## Phase Boundary

Make a voter's polling-place source a first-class, persisted, indexed attribute on the `voters` table, and capture every change to it in an append-only audit history table. This phase delivers SRC-03 only. It does NOT build the resolver/fallback cascade (Phase 8), does NOT build any UI badge/filter/re-check action (Phase 10), and does NOT build the reconciliation job (Phase 11) — it only lays the schema + model + relations those phases will read from and write to.

</domain>

<decisions>
## Implementation Decisions

### Source Enum Values
- **D-01:** New `PollingPlaceSource` backed string enum (in `app/Enums/`) with exactly 4 cases: `LIVE`, `DB_RECONSTRUCTION`, `SNAPSHOT`, `MANUAL`.
- **D-02:** `LIVE` is adapter-agnostic — it covers ANY live-source result regardless of which underlying adapter answered (the current Registraduría endpoints, and `wsp.registraduria.gov.co` once/if validated in Phase 9, and any future live adapter). The Redis cache tier is also folded into `LIVE` — a cache hit is just a performance layer over an already-live result, not a distinct source. The enum never distinguishes which specific live adapter or cache state produced the result. If a future need arises to know exactly which adapter answered, that detail belongs in the audit table's `notes` field (D-04), never as a new enum case.
- **D-03:** `DB_RECONSTRUCTION` = the existing campaign-scoped `census_records`+`polling_places` reconstruction tier (today's "Layer 2" in `HasRegistraduriaPolling`). `SNAPSHOT` = the new national snapshot from Phase 6 (`national_census_records`). `MANUAL` = a direct operator edit/correction, unrelated to any lookup tier.

### System Actor for Headless Writes
- **D-05:** The new audit table's actor column (`resolved_by`) is a **nullable** FK to `users` — no seeded system/bot user. When a human acts (Phase 8's interactive UI path), `resolved_by` is their user ID. When the headless reconciliation job (Phase 11) makes an automatic change, `resolved_by` is `null` — the accompanying `resolved_via = 'reconciliation'` value (D-08) already makes clear the change was automated. This is the one place this phase's design legitimately diverges from `ValidationHistory.validated_by`, which is non-null because a human always drives validations there.

### Audit Row Contents
- **D-06:** Each audit row stores, in addition to `previous_source`/`new_source`/`resolved_by`/`resolved_via`/`notes`: the resolved `polling_place_id` and `table_number` **at that point in time** (a value snapshot, not just the source label). This lets the history answer "which specific polling place did this resolution produce," not only "what tier answered" — useful for auditing whether an automatic reconciliation actually moved a voter to the correct place.
- **D-07:** Table name: `polling_place_resolutions`. Model: `PollingPlaceResolution`.

### Data Type for `resolved_via`
- **D-08:** `resolved_via` is a **plain string column**, matching `ValidationHistory.validation_type`'s existing precedent exactly — not a backed enum. Initial values used by later phases: `'interactive'` (Phase 8's UI-driven resolutions) and `'reconciliation'` (Phase 11's headless job). New values can be added later as plain strings without a migration or enum change.

### Claude's Discretion
- Whether `PollingPlaceSource` implements Filament's `HasColor`/`HasDescription`/`HasIcon`/`HasLabel` interfaces now (following the exact `VoterStatus` precedent) or only when Phase 10 actually needs the badge — implementing it now costs little and keeps the enum consistent with the codebase's established enum convention; deferring it is also fine since Phase 7 has no UI. Either is acceptable; lean toward implementing it now since the interfaces are trivial `match` expressions and Phase 10 will need them regardless.
- Exact migration column types/nullability details beyond what's specified above (e.g., whether `polling_place_source` defaults to `null` for existing voters vs. requiring a backfill) — no existing voter has ever had a `polling_place_source` before this phase, so `nullable` with no default/backfill is the natural choice, but the planner may confirm this against the actual current `polling_place_id` population state.
- Index strategy on `voters.polling_place_source` (plain index vs. composite with `campaign_id`) — follow whatever the reconciliation job's query pattern needs (Phase 11 will query `WHERE polling_place_source = 'snapshot'`, likely per-campaign or global; a plain index on the column is the safe default, composite if profiling suggests otherwise).

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone research (this phase's primary technical grounding)
- `.planning/research/SUMMARY.md` — executive summary, Phase 7 rationale
- `.planning/research/ARCHITECTURE.md` §"Decision 2: Source flag = column on `voters` (current) + `polling_place_resolutions` table (history)" — the exact schema shape, and why the `ValidationHistory` pattern is reused but not the same table
- `.planning/research/PITFALLS.md` — Pitfall 1 (snapshot data must never silently downgrade a live-verified result — precedence enforcement is Phase 8's job, but the schema this phase creates must support it), Pitfall 3 (reconciliation job has no human actor — resolved by D-05 in this phase's schema)

### Existing code precedents to reuse
- `app/Models/ValidationHistory.php` + `database/migrations/2025_11_03_171233_create_validation_histories_table.php` — the exact shape/scopes (`forVoter`, `byType`, `recent`) to mirror for `PollingPlaceResolution` (with `resolved_by` nullable instead of non-null, per D-05)
- `app/Enums/VoterStatus.php` — the exact backed-enum-with-Filament-interfaces pattern (`HasColor`, `HasDescription`, `HasIcon`, `HasLabel`) to mirror for `PollingPlaceSource`
- `app/Models/Voter.php` — where the new `polling_place_source` + `polling_place_resolved_at` columns attach (existing `$fillable`, `casts()`, and `validationHistories(): HasMany` relation at lines 118-121 show exactly where to add the parallel `pollingPlaceResolutions(): HasMany` relation)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `ValidationHistory`'s shape (`voter_id` FK cascadeOnDelete, two enum-cast status columns, actor FK, a type-discriminator string column, nullable `notes` text, indexes on `voter_id`/type-column/`created_at`) — directly reusable structure for `PollingPlaceResolution`, with the one actor-nullability divergence (D-05).
- `VoterStatus` enum's Filament-interface implementation pattern — directly reusable for `PollingPlaceSource`.

### Established Patterns
- Enums live in `app/Enums/`, are backed string enums, implement Filament's `HasColor`/`HasDescription`/`HasIcon`/`HasLabel` when they'll ever back a badge.
- Models use the `casts()` method form (not the `$casts` property) per project convention, and enum columns are cast to the enum class directly.
- New nullable columns on `voters` follow the existing pattern of `census_validated_at`, `call_verified_at` etc. — nullable timestamp/enum columns added via migration with no backfill for pre-existing rows.

### Integration Points
- `voters.polling_place_source` (enum, nullable) + `voters.polling_place_resolved_at` (nullable timestamp) — the current, queryable state. Phase 11's reconciliation job will filter `Voter::where('polling_place_source', PollingPlaceSource::SNAPSHOT)`.
- New `polling_place_resolutions` table + `PollingPlaceResolution` model — the append-only history. Phase 8's resolver and Phase 11's job both write here.
- `Voter::pollingPlaceResolutions(): HasMany` — new relation alongside the existing `validationHistories()`.

</code_context>

<specifics>
## Specific Ideas

No specific UI/visual references (this phase has no user-facing surface). The concrete decisions above (D-01 through D-08) fully specify the expected schema.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. The resolver/cascade (Phase 8), UI badge/filter/re-check (Phase 10), and reconciliation job (Phase 11) were not re-litigated here; this phase only prepares the schema they'll depend on.

</deferred>

---

*Phase: 07-source-flag-schema-resolution-audit-trail*
*Context gathered: 2026-07-24*
