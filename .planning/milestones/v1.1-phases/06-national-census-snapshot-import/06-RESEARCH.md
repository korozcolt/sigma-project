# Phase 6: National Census Snapshot Import - Research

**Researched:** 2026-07-24
**Domain:** Bulk import of a 216K-row, ISO-8859-1, semicolon-delimited national census CSV into a new cédula-indexed reference table, enriched via a divipol join against the seeded `polling_places`, inside Laravel 12 / PHP 8.4 / Pest 4.
**Confidence:** HIGH — every recommendation is grounded in the actual repo (verified the CSV bytes, the divipol seed, the seeder/importer precedents, the migration types, and the SQLite test harness).

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** Use a streaming `LazyCollection` + chunked `upsert()` inside a new Artisan command (`php artisan census:import-national`) — NOT `LOAD DATA LOCAL INFILE`. Avoids the unconfirmed `local_infile` server/client dependency; idempotent by design.
- **D-02:** No mechanism-detection/fallback. One fixed mechanism only.
- **D-03:** A CSV row whose divipol code doesn't match any `polling_places` row is still imported — `polling_place_id = null`, raw `nombre`/`mesa` kept as fallback. Never dropped.
- **D-04:** The command never aborts based on the unmatched percentage. It always completes and reports the unmatched % (e.g. "2.3% had no matching polling place"). No configurable abort threshold — a human reviews and decides.
- **D-05:** Duplicate cédulas within the CSV: last row read wins (simple upsert semantics).
- **D-06:** Re-running upserts in place — never truncates/reloads.
- **D-07:** A cédula present in an older import but absent from a newer one is NEVER deleted. Removals are never inferred from absence. Accumulated orphan rows are an accepted tradeoff for this milestone.

### Claude's Discretion

- Exact `national_census_records` schema beyond ARCHITECTURE.md's shape (column types, indexes beyond the cédula index).
- Exact chunk size for the `LazyCollection` + `upsert()` loop.
- Where/how the unmatched-% report is displayed (console table vs written file).
- ISO-8859-1 → UTF-8 approach (`mb_convert_encoding` per-line vs one-time `iconv` pre-pass) — pick the simpler.

### Deferred Ideas (OUT OF SCOPE)

None deferred within this phase. Explicitly NOT in scope for Phase 6: wiring the snapshot into the voter lookup cascade (`PollingPlaceResolver` — Phase 8), any UI (Phase 10), source-flag/audit schema (Phase 7), reconciliation job (Phase 11), live-source spike (Phase 9).
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| CENSO-02 | National census snapshot imported into an indexed, cédula-queryable table enriched with full department/municipality names and address (not just divipol codes) | New `national_census_records` table (unique-indexed `document_number`); enrichment via `polling_place_id` FK resolved at import from the divipol join → `PollingPlace` carries `address` + `belongsTo` Department/Municipality (the human names). See "Standard Stack", "Architecture Patterns", schema table. |
| CENSO-03 | Snapshot import validates its divipol codes against the current `polling_places` reference data and reports the unmatched percentage before go-live | In-memory keyed map of `polling_places` (`dd-mm-zz-pp` → id) mirroring `PollingPlaceSeeder`; per-row resolve; count `polling_place_id === null`; report unmatched % on completion (D-04). See "Code Examples", Pitfall 2. |
</phase_requirements>

## Summary

This is a **narrow, well-bounded batch-import phase** with no external service dependencies and no UI. Everything needed is already installed and there are two near-exact precedents in the repo: `PollingPlaceSeeder` (the divipol keyed-map join + `upsert()` batching) and `CensusImporter::importInBatches()` (chunked bulk write). The job is to combine those two patterns into one streaming Artisan command that reads the Latin-1 CSV, resolves each row's `polling_place_id` against an in-memory map of the seeded `polling_places`, and upserts into a new `national_census_records` table keyed uniquely on cédula.

The verified facts that shape the plan: the file is **ISO-8859-1 / CRLF / semicolon-delimited, 216,527 data rows** (216,528 lines incl. header); the four join codes are CSV columns `dpto`(idx 3), `mcpio`(idx 6), `zona`(idx 8), `puesto`(idx 10), which map directly to `polling_places.{dane_department_code, dane_municipality_code, zone_code, place_code}` (all `unsignedSmallInteger`); and the seed genuinely covers these codes (rural `zona=99`, Sincelejo `puesto=9`/`30` all present — so most rows will match and the unmatched % reflects real 2023-vs-current drift, not a shape mismatch).

Two non-obvious correctness issues drive the design and are the highest-value findings here: (1) tests run on **SQLite `:memory:`**, and SQLite raises `"ON CONFLICT DO UPDATE command cannot affect row a second time"` if a single multi-row upsert statement contains two rows with the same conflict key — so duplicate cédulas within a chunk **must be deduped in PHP (keep last) before the upsert**, which also implements D-05 deterministically; and (2) PHP 8.4 deprecates the implicit `escape` argument of `fgetcsv`/`str_getcsv`, so pass `escape: ''` explicitly.

**Primary recommendation:** One Artisan command `census:import-national` = `LazyCollection` stream (skip header, `mb_convert_encoding` per line, `str_getcsv(';', '"', '')`) → `->chunk(1000)` → dedupe chunk by `document_number` (last wins) → resolve `polling_place_id` from a pre-loaded in-memory map → `NationalCensusRecord::upsert(rows, ['document_number'], [...])` → tally & print unmatched %. Ship with a Pest test over a tiny fixture CSV.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Artisan command | Laravel 12 (installed) | `census:import-national` host for the import | Auto-registered from `app/Console/Commands/` in L12; repeatable/re-runnable. Repo already has 6 commands here. |
| `Illuminate\Support\LazyCollection` | Laravel 12 (installed) | Stream the 216K-row file without loading it into memory | Standard Laravel large-file idiom; the only approach that also runs on the SQLite test DB (LOAD DATA does not). |
| Eloquent `upsert()` | Laravel 12 (installed) | Idempotent chunked insert-or-update on unique `document_number` | Exactly the idiom `PollingPlaceSeeder::flush()` already uses; gives D-06 idempotency for free. |
| PHP `mbstring` (`mb_convert_encoding`) | bundled w/ PHP 8.4.23 | ISO-8859-1 → UTF-8 per line | Built-in; the standard fix for the "Malformed UTF-8" corruption this file otherwise causes ("LA PEÑATA"). |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `iconv` (CLI) | system | One-shot transcode `iconv -f ISO-8859-1 -t UTF-8` | Only if you prefer converting the file once up-front over per-line `mb_convert_encoding`. Per-line is simpler to wire into the stream and is recommended. |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `LazyCollection` + `upsert()` | `LOAD DATA LOCAL INFILE` + staging table | Faster on MySQL, but requires `local_infile` (unconfirmed in target env), can't run on the SQLite test DB, and adds fallback-detection complexity. **Ruled out by D-01/D-02.** |
| Native `str_getcsv` + `mbstring` | `league/csv` `CharsetConverter` | Tested reader with built-in charset conversion, but adds a Composer dependency for a fixed-schema known file. Not worth it. |
| `maatwebsite/excel` | — | Installed, but memory-heavy/slow for a 216K server-side load. Keep it for user-facing admin uploads only. |

**Installation:**
```bash
# Nothing to install. All dependencies are bundled/already present.
php artisan make:command ImportNationalCensus --no-interaction   # -> app/Console/Commands, signature: census:import-national
php artisan make:model NationalCensusRecord -mf --no-interaction # model + migration + factory
```

**Version verification:** `php -v` → PHP 8.4.23 (mbstring/iconv bundled). Laravel 12 + Eloquent `upsert()` confirmed in use (`PollingPlaceSeeder::flush()`). No registry packages to verify — no new dependencies.

## Architecture Patterns

### Recommended Structure (files this phase adds)
```
app/Console/Commands/ImportNationalCensus.php   # census:import-national — stream + resolve + upsert + report
app/Models/NationalCensusRecord.php             # model; belongsTo PollingPlace; NO HasCampaignContext
database/migrations/XXXX_create_national_census_records_table.php
database/factories/NationalCensusRecordFactory.php
tests/Feature/ImportNationalCensusTest.php      # fixture-CSV import test
tests/fixtures/census/national-sample.csv       # tiny ISO-8859-1 fixture (accent + dup cédula + unmatched row)
```

### `national_census_records` schema (verified against the join target)
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `document_number` | string, **unique**, indexed | CSV `cedula` (idx 2). The lookup key. **Unique constraint is required** — it is the `upsert` conflict target for D-05/D-06. String to match existing `CensusRecord.document_number` / voter cédula convention. |
| `polling_place_id` | `foreignId`, **nullable**, `nullOnDelete` | Resolved at import via divipol join (D-03 allows null). |
| `dane_department_code` | `unsignedSmallInteger` | CSV `dpto` (idx 3). **Use smallint, not string** — matches `polling_places` column types so the join/re-join is type-clean. |
| `dane_municipality_code` | `unsignedSmallInteger` | CSV `mcpio` (idx 6). |
| `zone_code` | `unsignedSmallInteger` | CSV `zona` (idx 8). |
| `place_code` | `unsignedSmallInteger` | CSV `puesto` (idx 10). |
| `polling_station_name` | string, nullable | CSV `nombre` (idx 11) — fallback when FK is null. |
| `table_number` | string, nullable | CSV `mesa` (idx 12). |
| `imported_at` | timestamp | Snapshot provenance / which run. |

- **No `campaign_id`, no `HasCampaignContext` trait, no `timestamps()`** — this is static national reference data, a sibling of `polling_places`, not campaign-scoped operational data (Pitfall 5). This is also the campaign-isolation guarantee for CENSO-02.
- **Refinement vs ARCHITECTURE.md:** it suggested `string` for the raw divipol columns "for traceability." Store them as `unsignedSmallInteger` instead so they match `polling_places` exactly (that migration uses `unsignedSmallInteger` for all four codes) — avoids a string-vs-int join mismatch if the map is ever bypassed for a SQL re-join. Cast CSV values with `(int)`, exactly as `PollingPlaceSeeder` does.

### Pattern 1: In-memory keyed-map divipol resolution (mirror `PollingPlaceSeeder`)
**What:** Pre-load all `polling_places` (13,755 seeded rows) into an array keyed by `"{dd}-{mm}-{zz}-{pp}"` → `id`. Resolve each CSV row against the map in O(1). No per-row DB query (avoids 216K N+1 queries).
**When:** Once at command start, before the stream loop.
```php
// Mirrors PollingPlaceSeeder's keyBy map technique.
$placeMap = PollingPlace::query()
    ->get(['id', 'dane_department_code', 'dane_municipality_code', 'zone_code', 'place_code'])
    ->keyBy(fn (PollingPlace $p) => "{$p->dane_department_code}-{$p->dane_municipality_code}-{$p->zone_code}-{$p->place_code}")
    ->map->id;
// per row:
$key = ((int) $dpto).'-'.((int) $mcpio).'-'.((int) $zona).'-'.((int) $puesto);
$pollingPlaceId = $placeMap[$key] ?? null;   // null => counts toward unmatched % (D-03/D-04)
```

### Pattern 2: Streaming Latin-1 read + chunked, deduped upsert
**What:** `LazyCollection` generator over the file handle; skip header; transcode each line; parse with explicit escape; chunk; dedupe by cédula (last wins); upsert.
```php
// Source pattern: PollingPlaceSeeder::flush() upsert + CensusImporter::importInBatches() chunking.
LazyCollection::make(function () use ($path) {
    $handle = fopen($path, 'rb');
    fgets($handle); // skip header row
    while (($line = fgets($handle)) !== false) {
        yield mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1');
    }
    fclose($handle);
})
->map(fn (string $line) => str_getcsv(rtrim($line, "\r\n"), ';', '"', '')) // PHP 8.4: explicit escape ''
->chunk(1000)
->each(function ($chunk) use ($placeMap, &$total, &$unmatched) {
    $rows = [];
    foreach ($chunk as $c) {
        // $c[2]=cedula, [3]=dpto, [6]=mcpio, [8]=zona, [10]=puesto, [11]=nombre, [12]=mesa
        $key = ((int) $c[3]).'-'.((int) $c[6]).'-'.((int) $c[8]).'-'.((int) $c[10]);
        $pid = $placeMap[$key] ?? null;
        if ($pid === null) { $unmatched++; }
        $total++;
        $rows[$c[2]] = [ // keyed by cedula => within-chunk dedupe, LAST WINS (D-05)
            'document_number' => $c[2],
            'polling_place_id' => $pid,
            'dane_department_code' => (int) $c[3],
            'dane_municipality_code' => (int) $c[6],
            'zone_code' => (int) $c[8],
            'place_code' => (int) $c[10],
            'polling_station_name' => $c[11] ?: null,
            'table_number' => $c[12] ?: null,
            'imported_at' => now(),
        ];
    }
    NationalCensusRecord::upsert(
        array_values($rows),
        ['document_number'],
        ['polling_place_id', 'dane_department_code', 'dane_municipality_code',
         'zone_code', 'place_code', 'polling_station_name', 'table_number', 'imported_at'],
    );
});
```

### Anti-Patterns to Avoid
- **Per-row `PollingPlace::where(...)->first()`** inside the loop → 216K queries. Use the pre-loaded map.
- **`firstOrCreate` on `polling_places` from the import** → spawns near-duplicate places (Pitfall 9 in milestone research). This import only *reads* `polling_places`; it never creates them. Unmatched rows get `null`, not a new place.
- **Adding `campaign_id` / `HasCampaignContext`** to the snapshot table → campaign-isolation leak (Pitfall 5). Keep it national + read-only.
- **`utf8_decode()` / `utf8_encode()`** → deprecated in PHP 8.2+, mangles bytes. Use `mb_convert_encoding`.
- **Truncating before load** → violates D-06/D-07. Upsert only; never delete.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Idempotent bulk insert-or-update | Manual "SELECT then INSERT/UPDATE" per cédula | Eloquent `upsert(rows, ['document_number'], [...])` | One statement per chunk; handles D-05/D-06 natively. Repo precedent: `PollingPlaceSeeder::flush()`. |
| Divipol → `polling_place_id` lookup | Bespoke join SQL or repeated queries | In-memory keyed map (Pattern 1) | Mirrors `PollingPlaceSeeder`; O(1) per row; already proven at this scale. |
| Latin-1 → UTF-8 | Byte-level `str_replace` hacks / `utf8_encode` | `mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1')` | Standard, correct, bundled. |
| Streaming a big file | `file()` / `file_get_contents` (loads all into memory) | `LazyCollection` over `fgets` | Constant memory; the 512M test limit and prod both stay safe. |
| Human names for a cédula | Denormalize dept/muni names into the snapshot | `polling_place_id` FK → `PollingPlace` → `belongsTo` Department/Municipality + `address` | Names/address already live on the seeded reference data; the FK is the enrichment (CENSO-02). Only keep `polling_station_name` as the null-FK fallback. |

**Key insight:** This phase is a *composition* of two existing repo patterns, not new invention. Deviating from the `PollingPlaceSeeder` + `CensusImporter` shapes is the main risk.

## Runtime State Inventory

> Not applicable. This is a greenfield additive phase (new table + new command + new model), not a rename/refactor/data-migration of existing runtime state. No stored strings are being renamed; no live service config, OS-registered state, secrets/env vars, or build artifacts are affected. Verified: no existing `NationalCensusRecord` model or `national_census_records` migration exists (`app/Models/` has only `CensusRecord.php`). The only data touched is the new table populated from a local CSV.

## Common Pitfalls

### Pitfall 1: SQLite chokes on duplicate cédulas within one upsert statement
**What goes wrong:** A chunk containing two rows with the same cédula produces a multi-row `INSERT ... ON CONFLICT(document_number) DO UPDATE`. SQLite (the test DB, `:memory:`) errors: `ON CONFLICT DO UPDATE command cannot affect row a second time`. MySQL tolerates it (last wins) but the test suite would fail.
**Why it happens:** `upsert()` emits one statement for the whole chunk; SQLite forbids updating the same row twice in a single statement.
**How to avoid:** Dedupe each chunk in PHP before upserting — build the batch array **keyed by `document_number`** so the last occurrence overwrites earlier ones (this is exactly D-05 "last row wins"), then `array_values()` it. Cross-chunk duplicates are fine (separate statements, later chunk wins naturally).
**Warning signs:** Test passes on MySQL, fails on SQLite with the "affect row a second time" message; or a fixture with a dup cédula blows up.

### Pitfall 2: Divipol drift / unmatched codes (CENSO-03's whole reason to exist)
**What goes wrong:** 2023 snapshot codes that no longer match the current `polling_places` seed resolve to `polling_place_id = null`. Verified the seed *does* cover the relevant codes (rural `zona=99`, `98`, `90` and Sincelejo `puesto` values are present), so this is genuine drift, not a systematic mismatch — but some percentage will miss.
**Why it happens:** Municipalities merged, codes reissued, puestos relocated between 2023 and today.
**How to avoid:** This is a feature, not a bug to hide (D-03/D-04): import the row with `null` FK, keep raw `nombre`/`mesa`, and **report the unmatched %** at the end so a human reviews it before go-live. Never abort. Cast both sides to `(int)` before keying the map (the JSON seed has mixed int/string code types; the seeder already `(int)`-casts — match that).
**Warning signs:** A surprisingly high unmatched % (would indicate a column-index or int-cast bug, not real drift) — sanity-check against the sample rows which are known to match.

### Pitfall 3: Encoding corruption on accented names
**What goes wrong:** Reading the file as UTF-8 corrupts "LA PEÑATA", "SAN MARTÍN", etc. ("Malformed UTF-8").
**Why it happens:** File is ISO-8859-1/CRLF (verified via `file`).
**How to avoid:** `mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1')` per line before parsing. The `;` delimiter is ASCII, so converting the whole line before `str_getcsv` is safe. `rtrim($line, "\r\n")` to drop the CRLF.
**Warning signs:** Mojibake in `polling_station_name`; test asserting `PEÑATA` fails.

### Pitfall 4: PHP 8.4 CSV escape deprecation
**What goes wrong:** `str_getcsv`/`fgetcsv` without an explicit `escape` argument raise a deprecation in PHP 8.4.23 and can mis-handle backslashes.
**How to avoid:** Pass `escape: ''` explicitly: `str_getcsv($line, ';', '"', '')`.
**Warning signs:** Deprecation notices in test output; fields containing `\` parsed oddly.

### Pitfall 5: Snapshot table becomes a campaign-isolation leak
**What goes wrong:** Adding a `campaign_id` or the `HasCampaignContext` trait, or writing to `polling_places`/campaign tables from this import, splices national data into campaign scope (SIGMA has had a real cross-campaign leak before).
**How to avoid:** `national_census_records` is national, read-only reference data — no `campaign_id`, no trait, no writes to campaign-scoped tables. This phase never touches `voters`/`census_records`. Test asserts the table has no `campaign_id` column.
**Warning signs:** A `campaign_id` column on the migration; any write to a campaign-scoped model from the command.

## Code Examples

### Unmatched-% report (console output — CENSO-03)
```php
// After the stream completes:
$pct = $total > 0 ? round($unmatched / $total * 100, 2) : 0.0;
$this->info("Importadas: {$total} filas.");
$this->warn("Sin puesto de votación coincidente: {$unmatched} ({$pct}%).");
// Non-aborting (D-04): always returns success; human reviews the %.
return self::SUCCESS;
```

### Reaching enriched names for a cédula (CENSO-02 acceptance shape)
```php
// A lookup returns full names + address via the FK, not just codes:
$record = NationalCensusRecord::with('pollingPlace.department', 'pollingPlace.municipality')
    ->where('document_number', $cedula)->first();
$record->pollingPlace?->municipality?->name;   // e.g. "SINCELEJO"
$record->pollingPlace?->department?->name;      // e.g. "SUCRE"
$record->pollingPlace?->address;                // street address
$record->polling_station_name;                  // fallback when pollingPlace is null (D-03)
```

## State of the Art
| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `utf8_encode()`/`utf8_decode()` | `mb_convert_encoding(...,'UTF-8','ISO-8859-1')` | PHP 8.2 deprecation | Must use mbstring. |
| Implicit `fgetcsv`/`str_getcsv` escape | Explicit `escape: ''` argument | PHP 8.4 | Pass `''` to silence deprecation + get predictable parsing. |

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP mbstring (`mb_convert_encoding`) | Latin-1 → UTF-8 | ✓ | bundled w/ 8.4.23 | `iconv` CLI |
| Census CSV file | The import itself | ✓ | 216,528 lines (verified present) | — |
| MySQL (prod `sigma_sincelejo`) | Prod import | ✓ (app runs on it) | 8 | — |
| SQLite `:memory:` | Test run of the command | ✓ | via phpunit.xml | — |

**Missing dependencies with no fallback:** None.
**Note:** No `local_infile`, no external services, no queue, no network — this phase is fully self-contained and testable offline. That is precisely why D-01 (`LazyCollection`) is the right call: it runs identically on SQLite (test) and MySQL (prod).

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 / PHPUnit 12 |
| Config file | `phpunit.xml` (DB = SQLite `:memory:`, `QUEUE_CONNECTION=sync`) |
| Quick run command | `php artisan test --filter=ImportNationalCensus` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CENSO-02 | Import populates cédula-indexed table; enriched names reachable via `polling_place_id` FK | feature | `php artisan test --filter=ImportNationalCensus` | ❌ Wave 0 |
| CENSO-02 | Accented Latin-1 name imports intact ("LA PEÑATA") | feature | same | ❌ Wave 0 |
| CENSO-02 | Table has no `campaign_id` (isolation guarantee) | feature | same | ❌ Wave 0 |
| CENSO-03 | Unmatched divipol row imported with `polling_place_id = null` + reported % | feature | same | ❌ Wave 0 |
| D-05 | Duplicate cédula in file → last row wins (single DB row) | feature | same | ❌ Wave 0 |
| D-06 | Re-running import is idempotent (no duplicate cédula rows) | feature | same | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter=ImportNationalCensus`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`.

### Wave 0 Gaps
- [ ] `tests/Feature/ImportNationalCensusTest.php` — covers CENSO-02, CENSO-03, D-05, D-06
- [ ] `tests/fixtures/census/national-sample.csv` — tiny **ISO-8859-1** fixture: one matching row, one accented name ("LA PEÑATA"), one unmatched-divipol row, one duplicate cédula (to prove last-wins + SQLite dedupe). Must be authored/saved as ISO-8859-1, not UTF-8, or the encoding test is meaningless.
- [ ] `database/factories/NationalCensusRecordFactory.php` — for seeding lookups in this and later phases.
- [ ] Test must seed a handful of `PollingPlace` rows (via `PollingPlace::factory()`) whose codes match the fixture's matching row, so FK resolution is exercised.

## Open Questions

1. **Actual production unmatched %** — Can't be known without running against the prod-seeded `polling_places`. Verified the *shape* matches and the sample codes exist in the seed, so it should be low, but the real number is only knowable at import time. That is by design (CENSO-03 reports it; a human judges it). No blocker.
2. **Is cédula globally unique in the snapshot?** Sample rows are all distinct; D-05 assumes possible dupes and handles them (last wins). The unique constraint + upsert is correct either way. No action needed.

## Sources

### Primary (HIGH confidence)
- Repo `database/external-data/censo_decoded_202310210734.csv` — `file` confirms ISO-8859 + CRLF; `wc -l` = 216,528; header + column order + accented rows inspected directly.
- Repo `database/external-data/divipole-nacional.json` — 13,755 rows; verified `zona=99/98/90` and Sincelejo `puesto` codes present; 35 departments; codes stored mixed int/string (seeder `(int)`-casts).
- Repo `database/seeders/PollingPlaceSeeder.php` — keyed-map join + `upsert()` batching precedent.
- Repo `app/Services/CensusImporter.php` — chunked bulk-write precedent (`importInBatches`).
- Repo `database/migrations/2026_01_22_000001_create_polling_places_table.php` + `app/Models/PollingPlace.php` — join target: `unsignedSmallInteger` code columns, unique divipol index, `belongsTo` Department/Municipality, `address`.
- Repo `phpunit.xml` — test DB is SQLite `:memory:`; `tests/Feature/ElectionEventClosureTest.php` — Pest command/job test style.
- `.planning/research/{STACK,ARCHITECTURE,PITFALLS}.md` — milestone-level grounding (import approach, Decision 1 & 4, Pitfalls 2 & 9).

### Secondary (MEDIUM confidence)
- SQLite "ON CONFLICT DO UPDATE command cannot affect row a second time" behavior — well-established SQLite constraint; drives the in-PHP dedupe. Reproducible in the test harness if a dup slips into a chunk.
- PHP 8.4 `fgetcsv`/`str_getcsv` `escape` deprecation — PHP 8.4 changelog behavior; mitigated by explicit `escape: ''`.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new deps; two exact repo precedents.
- Architecture/schema: HIGH — schema types verified against the live join target; column indices verified against real CSV bytes.
- Pitfalls: HIGH — SQLite dedupe and encoding are concrete and reproducible; unmatched-% is real-drift (seed coverage verified).

**Research date:** 2026-07-24
**Valid until:** 2026-08-23 (stable domain; no fast-moving external deps).
