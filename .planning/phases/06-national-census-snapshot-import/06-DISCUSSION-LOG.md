# Phase 6: National Census Snapshot Import - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-24
**Phase:** 06-national-census-snapshot-import
**Areas discussed:** Import mechanism, Unmatched divipol codes, Duplicate cédulas, Re-import policy

---

## Import Mechanism

| Option | Description | Selected |
|--------|-------------|----------|
| LazyCollection + chunked upsert | 100% PHP/Artisan, no `local_infile` dependency, idempotent by design via upsert | ✓ |
| LOAD DATA LOCAL INFILE + staging table | Faster native MySQL load, but requires `local_infile` ON server + client (unconfirmed) | |

**User's choice:** LazyCollection + chunked upsert
**Notes:** Also asked whether to add automatic mechanism-detection with fallback (try LOAD DATA, fall back to LazyCollection) vs a single fixed mechanism. User chose a single fixed mechanism — simpler to maintain/test.

---

## Unmatched Divipol Codes

| Option | Description | Selected |
|--------|-------------|----------|
| Import with polling_place_id null | Row still lands in the table with a null FK + raw CSV name/mesa as fallback | ✓ |
| Discard the row entirely | Row never appears in national_census_records if its code doesn't resolve | |

**User's choice:** Import with polling_place_id null
**Notes:** Follow-up question on abort threshold — options were "never fail, only report" (selected) vs "set an abort threshold" (e.g. 10%). User chose to never fail; the import always completes and reports the unmatched percentage for a human to review.

---

## Duplicate Cédulas in the Snapshot

| Option | Description | Selected |
|--------|-------------|----------|
| Last row in the file wins | Simple upsert semantics, no conflict tracking | ✓ |
| First row wins | Keep first occurrence, ignore subsequent ones | |
| Log as a conflict for manual review | Keep first, but flag duplicates in a report for an admin to resolve | |

**User's choice:** Last row wins
**Notes:** No additional follow-up needed — straightforward upsert behavior.

---

## Re-import Policy

| Option | Description | Selected |
|--------|-------------|----------|
| Upsert in place, never truncate | Each run updates/inserts by cédula without wiping first | ✓ |
| Truncate and reload everything | Full rebuild each run; brief empty window, loses any manual edits | |

**User's choice:** Upsert in place
**Notes:** Follow-up question on stale-row handling when a newer file no longer includes a previously-imported cédula — options were "rows stay, never deleted" (selected) vs "rows are deleted if absent from the latest file." User chose to never delete: the snapshot is a last-resort fallback, not an authoritative source, so absence from a newer file shouldn't be treated as a removal signal.

---

## Claude's Discretion

- Exact `national_census_records` schema details beyond the required shape already specified in ARCHITECTURE.md (column types, additional indexes).
- Chunk size for the LazyCollection + upsert loop, and where the unmatched-% report is displayed (console output vs a written file).
- ISO-8859-1 → UTF-8 conversion approach (native `mb_convert_encoding` per-line vs a one-time `iconv` pre-pass).

## Deferred Ideas

None — discussion stayed within Phase 6 scope.
