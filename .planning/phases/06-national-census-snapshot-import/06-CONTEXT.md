# Phase 6: National Census Snapshot Import - Context

**Gathered:** 2026-07-24
**Status:** Ready for planning

<domain>
## Phase Boundary

Load the 216,528-row national census snapshot (`censo_decoded_202310210734.csv`) into a cédula-indexed, location-enriched reference table (`national_census_records`), kept strictly separate from campaign-scoped data. Validate the snapshot's divipol codes against the current `polling_places` seed and report the unmatched percentage before go-live. This phase delivers CENSO-02 and CENSO-03 only — it does NOT wire the snapshot into the voter lookup cascade (that's Phase 8's `PollingPlaceResolver`) and does NOT build any UI (that's Phase 10).

</domain>

<decisions>
## Implementation Decisions

### Import Mechanism
- **D-01:** Use a streaming `LazyCollection` + chunked `upsert()` inside a new Artisan command (`php artisan census:import-national`) — NOT `LOAD DATA LOCAL INFILE`. Reason: avoids a dependency on `local_infile` being enabled server + client side (unconfirmed in the target environment per research), and gives idempotent, re-runnable behavior by design (upsert) without extra fallback-detection code.
- **D-02:** No automatic mechanism-detection/fallback (e.g., "try LOAD DATA, fall back to LazyCollection if unavailable"). One fixed mechanism only — simpler to maintain and test, and works identically in any environment without server configuration.

### Unmatched Divipol Codes
- **D-03:** A CSV row whose divipol code (dpto/mcpio/zona/puesto) doesn't match any `polling_places` row is still imported — with `polling_place_id = null` and the raw CSV `nombre`/`mesa` kept as a fallback reference. The row is never dropped entirely.
- **D-04:** The import command never aborts/fails based on the unmatched percentage. It always completes and reports the unmatched % in its output (e.g., "2.3% of rows had no matching polling place"). No configurable abort threshold — a human reviews the reported percentage and decides whether to act.

### Duplicate Cédulas Within the Snapshot
- **D-05:** If the CSV contains more than one row for the same cédula, the last row read from the file wins (simple upsert semantics — no first-wins logic, no conflict-report flow). This applies within a single import run.

### Re-import Policy
- **D-06:** Re-running the import command (same file or a newer snapshot) upserts in place — it never truncates/reloads the table. Consistent with the idempotency requirement (CENSO success criteria #5) and with D-05 ("last row wins" applies per-run against existing DB rows too).
- **D-07:** A cédula present in an older import but absent from a newer one is NEVER deleted. The national snapshot is a best-effort, last-resort fallback data source, not an authoritative source of truth about who is/isn't in the census — a newer file could be a partial/cropped export, so removals are never inferred from absence. Over time this can accumulate "orphaned" rows from older snapshot versions; that's an accepted tradeoff for this milestone.

### Claude's Discretion
- Exact `national_census_records` schema beyond what's already specified in ARCHITECTURE.md (column types, indexes beyond the required cédula index) — follow the research's recommended shape (`document_number` unique/indexed, `polling_place_id` nullable FK, raw divipol codes kept for traceability, `polling_station_name` fallback, `imported_at` timestamp).
- Exact chunk size for the `LazyCollection` + `upsert()` loop, and where/how the unmatched-% report is displayed (console output table vs a written report file) — pick whatever fits Laravel/Artisan conventions cleanly.
- ISO-8859-1 → UTF-8 conversion approach (native `mb_convert_encoding` per-line vs a one-time `iconv` pre-pass) — either is fine per Stack research; pick the simpler one to wire into the streaming read.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone research (this phase's primary technical grounding)
- `.planning/research/SUMMARY.md` — executive summary, phase 6 rationale, and the full requirement/pitfall cross-references
- `.planning/research/STACK.md` §"CSV import approach (216K rows, ISO-8859-1, semicolon-delimited)" — import mechanism options, encoding handling, indexing strategy
- `.planning/research/ARCHITECTURE.md` §"Decision 1: National snapshot is a NEW table" and §"Decision 4: Import via an Artisan command" — exact schema, why not to reuse `census_records`, the divipol-join technique
- `.planning/research/PITFALLS.md` — Pitfall 2 (campaign-isolation leak via the snapshot table), Pitfall 9 (jurisdiction/divipol code drift corrupting the dentro/fuera report)

### Source data
- `database/external-data/censo_decoded_202310210734.csv` — the 216,528-row national snapshot to import (ISO-8859-1, CRLF, semicolon-delimited; columns: `divipol;codificado;cedula;dpto;cero;cero;mcpio;ref1;zona;ref2;puesto;nombre;mesa`)
- `database/external-data/divipole-nacional.json` — the reference dataset already seeded into `polling_places`; used to understand the divipol code shape this import must join against

### Existing code precedents to reuse
- `database/seeders/PollingPlaceSeeder.php` — the exact divipol-code-keyed-map join technique this import should mirror (department/municipality lookup maps, `normalizeName`/`normalizeMunicipalityName` helpers, `upsert()` batching)
- `app/Services/CensusImporter.php` — existing batched-insert precedent for campaign-scoped census imports; structural reference only (this phase's target table is national-scope, not campaign-scoped)
- `app/Models/PollingPlace.php` + `database/migrations/2026_01_22_000001_create_polling_places_table.php` — the join target and its exact key columns (`dane_department_code`, `dane_municipality_code`, `zone_code`, `place_code`)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `PollingPlaceSeeder`'s keyed-map join technique (pre-load `polling_places` into an in-memory map keyed by normalized department/municipality name or divipol codes, then look up per-row) — directly reusable pattern for resolving `polling_place_id` per CSV row.
- `PollingPlace` model's unique key shape (`dane_department_code`, `dane_municipality_code`, `zone_code`, `place_code`) — the exact join key the new import must match against.

### Established Patterns
- Artisan commands live in `app/Console/Commands/`, auto-registered in Laravel 12 (no manual registration needed).
- Existing migrations follow a `database/migrations/YYYY_MM_DD_HHMMSS_description.php` naming convention with explicit `up()`/`down()`.
- `upsert()` batching (seen in `PollingPlaceSeeder::flush()`) is the established idiom for bulk insert-or-update in this codebase.

### Integration Points
- The new `national_census_records` table joins to `polling_places` via the divipol code columns — this is the seam Phase 8's `PollingPlaceResolver` will read from later.
- No UI, no voter-facing changes, no queue/job in this phase — purely a migration + model + Artisan command + its test.

</code_context>

<specifics>
## Specific Ideas

No specific UI/visual references (this phase has no user-facing surface). The concrete decisions above (D-01 through D-07) fully specify the expected behavior of the import command.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope. Broader multi-adapter live-source work, source-flag/audit schema, the resolver service, operator UI, and the reconciliation job are already scoped to Phases 7-11 respectively and were not re-litigated here.

</deferred>

---

*Phase: 06-national-census-snapshot-import*
*Context gathered: 2026-07-24*
