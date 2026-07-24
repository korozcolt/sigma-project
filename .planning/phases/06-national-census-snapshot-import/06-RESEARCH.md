# Phase 6: National Census Snapshot Import - Research

**Researched:** 2026-07-24
**Domain:** Bulk streaming CSV import (216K rows, ISO-8859-1) into a cédula-indexed national reference table with a divipol-code join, inside an existing Laravel 12 / MySQL app
**Confidence:** HIGH — every mechanic below is verified against the actual repo, the actual CSV, and the actual reference JSON; the divipol join was executed and confirmed against two real rows

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Import Mechanism**
- **D-01:** Use a streaming `LazyCollection` + chunked `upsert()` inside a new Artisan command (`php artisan census:import-national`) — NOT `LOAD DATA LOCAL INFILE`. Reason: avoids depending on `local_infile` (unconfirmed in target env) and gives idempotent re-runnable behavior by design (upsert) without fallback-detection code.
- **D-02:** No automatic mechanism-detection/fallback. One fixed mechanism only.

**Unmatched Divipol Codes**
- **D-03:** A CSV row whose divipol code doesn't match any `polling_places` row is still imported — with `polling_place_id = null` and the raw CSV `nombre`/`mesa` kept as a fallback reference. Never dropped.
- **D-04:** The import command never aborts based on unmatched percentage. It always completes and reports the unmatched % in output. No configurable abort threshold — a human reviews and decides.

**Duplicate Cédulas Within the Snapshot**
- **D-05:** If the CSV contains more than one row for the same cédula, the last row read wins (simple upsert semantics). Applies within a single import run.

**Re-import Policy**
- **D-06:** Re-running the command upserts in place — never truncates/reloads.
- **D-07:** A cédula present in an older import but absent from a newer one is NEVER deleted. Snapshot is best-effort last-resort data, not authoritative-about-absence. Orphaned rows from older snapshots are an accepted tradeoff.

### Claude's Discretion
- Exact `national_census_records` schema beyond the ARCHITECTURE.md shape (column types, extra indexes). Follow research's recommended shape.
- Exact chunk size for the `LazyCollection` + `upsert()` loop, and where/how the unmatched-% report is displayed (console table vs written report file).
- ISO-8859-1 → UTF-8 conversion approach (per-line `mb_convert_encoding` vs one-time `iconv` pre-pass) — pick the simpler to wire into the streaming read.

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope. The resolver service (Phase 8), source-flag/audit schema (Phase 7), operator UI (Phase 10), live-source spike (Phase 9), and reconciliation job (Phase 11) are explicitly NOT part of this phase.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| CENSO-02 | National census snapshot imported into an indexed, cédula-queryable table enriched with full department/municipality names and address (not just divipol codes) | New `national_census_records` table with `document_number` (string) unique index + nullable `polling_place_id` FK resolved at import via the verified divipol join (§Architecture Patterns, §Code Examples). Enrichment (dept/muni names + address) is read through the `polling_places` join, not duplicated onto the row. |
| CENSO-03 | Snapshot import validates its divipol codes against the current `polling_places` reference data and reports the unmatched percentage before go-live | Import counts rows whose `(dpto,mcpio,zona,puesto)` key misses the pre-loaded `polling_places` map, and prints `unmatched %` at the end (D-04). Verified: the current file joins 100% within Sincelejo; the mechanism is what CENSO-03 requires (§Code Examples, §Common Pitfalls Pitfall 2). |
</phase_requirements>

## Summary

This is a **narrow, well-bounded data-engineering phase** with no UI, no queue, no live source, and no cross-service work. The deliverable is exactly four artifacts: a migration (`national_census_records`), a model (`NationalCensusRecord`), an Artisan command (`census:import-national`), and its Pest test. Every mechanic it needs already has a verified precedent in this repo.

The single most important verified finding: **the divipol join works exactly as ARCHITECTURE.md predicted.** I executed the join against the real reference data — CSV columns `dpto/mcpio/zona/puesto` map 1:1 to `polling_places.(dane_department_code, dane_municipality_code, zone_code, place_code)`, and two sample rows (`28/1/99/9`→CHOCHO, `28/1/99/78`→LA GALLERA) resolve to the correct SUCRE/SINCELEJO polling places with matching names. The resolution technique is the exact keyed-map lookup `PollingPlaceSeeder` already uses.

A second finding worth surfacing to the planner: **this "national" snapshot is not national — it is Sincelejo-only.** All 216,527 data rows have `dpto=28, mcpio=1` (SUCRE/SINCELEJO); there are 9 zonas and 30 puestos. The table and command should still be built national-capable (the schema and join have no Sincelejo assumptions), but the CENSO-03 unmatched-% report will only ever exercise Sincelejo codes for this file, and the current file's expected unmatched rate is ~0%. Do not hardcode anything to department 28.

**Primary recommendation:** Build `census:import-national` as a `LazyCollection`-streamed reader that (1) decodes each line ISO-8859-1→UTF-8, (2) resolves `polling_place_id` from an in-memory `polling_places` map keyed `"dd-mm-zz-pp"`, (3) chunks into `upsert()` batches keyed on the unique `document_number`, and (4) tallies matched/unmatched rows to print an unmatched-% report at the end. Mirror `PollingPlaceSeeder`'s map+flush structure and `CensusImporter`'s batching; store `document_number` as a **string** (matching `voters` and `census_records`) so Phase 8's resolver joins cleanly.

## Standard Stack

Nothing new is installed. Every capability is native to the current stack.

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `Illuminate\Support\LazyCollection` | Laravel 12 (installed) | Stream the 216K-row file line-by-line at constant memory | Framework-native; the idiomatic Laravel answer to "iterate a huge file without loading it into RAM" |
| Eloquent `upsert()` | Laravel 12 (installed) | Idempotent chunked insert-or-update keyed on `document_number` | Already the house idiom for bulk load — `PollingPlaceSeeder::flush()` uses it verbatim. Satisfies D-05/D-06 idempotency by construction |
| `mb_convert_encoding()` | PHP 8.4 mbstring (bundled) | Per-line ISO-8859-1 → UTF-8 decode of accented names ("LA PEÑATA") | Zero-dependency, the standard fix for the "Malformed UTF-8" this Latin-1 file otherwise throws. Do NOT use deprecated `utf8_decode()` |
| `Illuminate\Console\Command` | Laravel 12 (installed) | The `census:import-national` command shell + progress/report output | Auto-registered in `app/Console/Commands/` in L12; `ImportColombiaData` is the in-repo precedent (progress bar, `$this->info`) |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `fgetcsv()` / native line read | PHP 8.4 | Parse semicolon-delimited rows inside the LazyCollection generator | The file is a known fixed 13-column schema — native parsing is enough; no CSV library needed |
| `iconv` (CLI) | system | Optional one-time whole-file transcode instead of per-line decode | Only if you prefer a pre-pass over inline `mb_convert_encoding`; per-line is simpler to wire into the stream and needs no temp file (recommended) |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `LazyCollection` + `upsert()` | `LOAD DATA LOCAL INFILE` + SQL join | **Explicitly rejected by D-01/D-02.** Faster but needs `local_infile` enabled (unconfirmed in target env) and does not run on the SQLite `:memory:` test DB — the chosen path is testable and env-independent |
| Native `fgetcsv` + `mb_convert_encoding` | `league/csv` `CharsetConverter` | A tested reader with built-in charset conversion, but adds a Composer dependency for a fixed-schema file that native code handles. Not worth it here |
| `maatwebsite/excel` | — | Installed but memory-heavy/slow for a 216K-row server-side load; keep it for the user-facing Apoyo admin uploads it already serves |

**Installation:** None. `composer require` is not needed for this phase.

**Version verification:** No new packages, so no registry check required. Confirmed installed: Laravel `v12`, PHP `8.4.14`, mbstring enabled (`mb_convert_encoding` available).

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Console/Commands/
│   └── ImportNationalCensus.php      # signature: census:import-national {file?} {--chunk=1000}
└── Models/
    └── NationalCensusRecord.php      # national reference model, NO campaign scope
database/
├── migrations/
│   └── 2026_07_24_000000_create_national_census_records_table.php
└── factories/
    └── NationalCensusRecordFactory.php
tests/
└── Feature/
    └── ImportNationalCensusTest.php  # small fixture CSV → assert count, FK resolution, encoding, idempotency
database/external-data/
└── censo_decoded_202310210734.csv    # source (already present, do not modify)
```

### Pattern 1: In-memory divipol map + streamed chunked upsert (the whole command)
**What:** Pre-load `polling_places` into a PHP array keyed by `"dd-mm-zz-pp"`, then stream the CSV, resolve each row's `polling_place_id` from the map, and flush in chunked `upsert()` batches. This is `PollingPlaceSeeder`'s `keyBy`+`flush` structure applied to a streamed source instead of a decoded JSON array.
**When to use:** This is THE pattern for the phase.
**Why the map fits in memory:** the full national `divipole-nacional.json` is only 13,755 places; the Sincelejo subset this file joins against is ~270. A keyed array of a few thousand small rows is trivial RAM.

```php
// Source: PollingPlaceSeeder.php (keyed-map + upsert precedent) + Laravel LazyCollection docs
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;
use Illuminate\Support\LazyCollection;

// 1. Build the divipol → polling_place_id map once (small, national-capable)
$placeMap = PollingPlace::query()
    ->get(['id', 'dane_department_code', 'dane_municipality_code', 'zone_code', 'place_code'])
    ->keyBy(fn (PollingPlace $p): string => "{$p->dane_department_code}-{$p->dane_municipality_code}-{$p->zone_code}-{$p->place_code}")
    ->map->id;

$matched = 0;
$unmatched = 0;
$now = now();

// 2. Stream the file at constant memory; decode Latin-1 per line
LazyCollection::make(function () use ($path) {
    $handle = fopen($path, 'r');
    fgetcsv($handle, 0, ';'); // skip header
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        yield $row;
    }
    fclose($handle);
})
    ->chunk(1000)
    ->each(function (LazyCollection $chunk) use ($placeMap, &$matched, &$unmatched, $now): void {
        $batch = [];
        foreach ($chunk as $row) {
            // columns: 0 divipol,1 codificado,2 cedula,3 dpto,4 cero,5 cero,6 mcpio,7 ref1,8 zona,9 ref2,10 puesto,11 nombre,12 mesa
            $dpto = (int) $row[3];
            $mcpio = (int) $row[6];
            $zona = (int) $row[8];
            $puesto = (int) $row[10];
            $pollingPlaceId = $placeMap["{$dpto}-{$mcpio}-{$zona}-{$puesto}"] ?? null;
            $pollingPlaceId === null ? $unmatched++ : $matched++;

            $batch[] = [
                'document_number' => trim($row[2]),
                'polling_place_id' => $pollingPlaceId,
                'dane_department_code' => $dpto,
                'dane_municipality_code' => $mcpio,
                'zone_code' => $zona,
                'place_code' => $puesto,
                'polling_station_name' => mb_convert_encoding(trim($row[11]), 'UTF-8', 'ISO-8859-1'),
                'table_number' => trim($row[12]),
                'imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 3. Idempotent flush keyed on the unique document_number (D-05 last-row-wins, D-06 upsert-in-place)
        NationalCensusRecord::query()->upsert(
            $batch,
            ['document_number'],
            ['polling_place_id', 'dane_department_code', 'dane_municipality_code', 'zone_code', 'place_code', 'polling_station_name', 'table_number', 'imported_at', 'updated_at'],
        );
    });

// 4. CENSO-03 report (D-04: always completes, never aborts)
$total = $matched + $unmatched;
$pct = $total > 0 ? round($unmatched / $total * 100, 2) : 0.0;
$this->info("Importadas: {$total} | con puesto: {$matched} | sin puesto: {$unmatched} ({$pct}% sin coincidencia divipol)");
```

### Pattern 2: National reference model (no campaign scope)
**What:** `NationalCensusRecord` is a plain Eloquent model — no `HasCampaignContext` trait, no `campaign_id`, sibling to `PollingPlace`.
**Why:** See Pitfall 1 — adding campaign scope is the isolation-leak failure class. The model belongs to the "national reference data" category (`polling_places`, `departments`, `municipalities`), not the campaign-operational category (`census_records`, `voters`).

```php
// Source: PollingPlace.php (sibling reference model shape)
class NationalCensusRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number', 'polling_place_id',
        'dane_department_code', 'dane_municipality_code', 'zone_code', 'place_code',
        'polling_station_name', 'table_number', 'imported_at',
    ];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime'];
    }

    public function pollingPlace(): BelongsTo
    {
        return $this->belongsTo(PollingPlace::class);
    }
}
```

### Recommended schema (`national_census_records`)
Follows the ARCHITECTURE.md shape with types verified against the CSV data profile.

| Column | Type | Notes |
|--------|------|-------|
| `id` | `id()` | PK |
| `document_number` | `string`, **`->unique()`** | The `cedula` column. **String, not BIGINT** — matches `voters.document_number` and `census_records.document_number` (both `string`) so Phase 8's resolver joins without type/format friction. The unique index IS the required cédula index and the upsert conflict target |
| `polling_place_id` | `foreignId()->nullable()->constrained()->nullOnDelete()` | Resolved at import; nullable per D-03 |
| `dane_department_code` | `unsignedSmallInteger` | Raw divipol kept for traceability + re-join if `polling_places` re-seeded. Matches `polling_places` column type. Max seen: 28 |
| `dane_municipality_code` | `unsignedSmallInteger` | Matches `polling_places`. Max seen: 1 |
| `zone_code` | `unsignedSmallInteger` | Matches `polling_places`. Max seen: 99 |
| `place_code` | `unsignedSmallInteger` | Matches `polling_places`. Max seen: 98 |
| `polling_station_name` | `string`, nullable | The CSV `nombre` — fallback reference when FK is null (D-03) |
| `table_number` | `string`, nullable | The CSV `mesa`. Max seen: 50 |
| `imported_at` | `timestamp`, nullable | Snapshot provenance (which run) |
| timestamps | `timestamps()` | Optional; harmless. `census_records` keeps them |

Indexes: the `document_number` unique index is the only required one (it serves both the cédula lookup and the upsert conflict key). No `campaign_id`, no campaign-context trait.

### Anti-Patterns to Avoid
- **Adding `campaign_id` to this table** — it is national reference data; a `campaign_id` here is the isolation-leak vector (Pitfall 1). Do not add it, do not add `HasCampaignContext`.
- **`LOAD DATA LOCAL INFILE`** — rejected by D-01, and it does not run against the SQLite `:memory:` test DB, so the command would be untestable.
- **Storing `document_number` as BIGINT** — max value fits unsigned INT today (2,000,015,490), but string matches the rest of the codebase and avoids leading-zero and join-type mismatches with `voters.document_number` in Phase 8.
- **`PollingPlace::firstOrCreate()` per row** — never create polling places from this import (Pitfall table-bloat). Resolve against the existing seed only; miss → null.
- **One giant `insert()` of 216K rows** — memory blow-up; stream + chunk.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Streaming a huge file at constant memory | Manual `fopen`/`feof` loop with your own buffering | `LazyCollection::make(generator)->chunk()` | Framework-native, lazy, composes with `->chunk()->each()` cleanly |
| Idempotent bulk insert-or-update | Per-row `updateOrCreate` (216K queries) or manual "SELECT then INSERT/UPDATE" | `upsert($rows, ['document_number'], [...])` | One statement per chunk; DB-level conflict handling gives D-05/D-06 for free |
| ISO-8859-1 → UTF-8 conversion | Byte-level `str_replace` of accented chars, or `utf8_decode()` | `mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1')` | Correct for the whole Latin-1 range; `utf8_decode` is deprecated in PHP 8.2+ |
| Divipol code → polling_place_id lookup | Per-row DB query against `polling_places` | Pre-loaded keyed-array map (`keyBy` "dd-mm-zz-pp") | 216K DB round-trips → one query; identical to `PollingPlaceSeeder` |
| Command scaffolding / progress output | Hand-rolled arg parsing | `php artisan make:command` + `$this->output->createProgressBar()` | In-repo precedent: `ImportColombiaData` |

**Key insight:** Every piece of this phase already exists somewhere in the repo. The command is essentially `PollingPlaceSeeder` (keyed map + chunked upsert) reading a streamed Latin-1 CSV instead of a decoded JSON blob, wrapped in an `ImportColombiaData`-style command shell.

## Runtime State Inventory

> This is an ADD phase (new table/model/command), not a rename/refactor. No existing runtime state is being renamed. Included for completeness because the phase writes bulk data.

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | New `national_census_records` table created by this phase; no existing datastore key/collection is renamed | Migration only |
| Live service config | None — no external service touched (no n8n, Datadog, Registraduría, queue) | None — verified: phase is migration + model + command + test |
| OS-registered state | None — the command is run manually/ad-hoc; NOT scheduled in `routes/console.php` (scheduling belongs to Phase 11's reconciliation job, not this import) | None |
| Secrets/env vars | None. `LOAD DATA` was rejected, so no `local_infile`/`PDO::MYSQL_ATTR_LOCAL_INFILE` env or connection flag is needed | None |
| Build artifacts | None — no package rename, no compiled artifact | None |

## Common Pitfalls

### Pitfall 1: The snapshot table becomes a campaign-isolation leak (milestone Pitfall 2)
**What goes wrong:** Someone adds `campaign_id` or the `HasCampaignContext` trait "to be safe," or a later phase writes campaign-scoped data derived from a snapshot read without asserting the correct `campaign_id`.
**Why it happens:** The word "census" pattern-matches to the campaign-scoped `census_records` table.
**How to avoid:** Keep `national_census_records` strictly read-only reference data — no `campaign_id`, no campaign trait, no writes from campaign flows. This phase only *populates* it from the CSV; it never reads voter/campaign data. (The write-path isolation test belongs to Phase 8's resolver, not here.)
**Warning signs:** A `campaign_id` column on the migration; importing `HasCampaignContext`.

### Pitfall 2: Divipol drift / silent join misses (milestone Pitfall 9) — this is exactly what CENSO-03 guards
**What goes wrong:** A CSV divipol code doesn't match any `polling_places` row (code reissued, puesto relocated), the join silently returns null, and nobody knows coverage before go-live.
**Why it happens:** Blind import with no matched/unmatched accounting.
**How to avoid:** The command MUST tally matched vs unmatched and print the unmatched % (D-04). This is not optional polish — it IS CENSO-03. **Verified for the current file:** both sampled rows joined, and the file is 100% within Sincelejo divipol space, so the expected unmatched rate is ~0%. The mechanism must still exist and report, because a future national file will have real misses.
**Warning signs:** A row written with `polling_place_id = null` and no corresponding increment to the unmatched counter; the command finishing without printing a coverage line.

### Pitfall 3: UTF-8 corruption on accented names (success criterion #3)
**What goes wrong:** Names like "LA PEÑATA" import as "LA PE�ATA" or throw "Malformed UTF-8".
**Why it happens:** The file is ISO-8859-1 (verified: `charset=iso-8859-1`) but PHP/MySQL assume UTF-8.
**How to avoid:** `mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1')` on the `nombre` field (and any text field) as it is read. Assert this explicitly in the test with a fixture row containing `Ñ`.
**Warning signs:** Replacement characters (`�`) in `polling_station_name`; a test that only uses ASCII names (won't catch it).

### Pitfall 4: Non-idempotent re-import (success criterion #5)
**What goes wrong:** Re-running the command duplicates cédula rows or truncates good data.
**Why it happens:** Using `insert()` instead of `upsert()`, or adding a `--fresh`/truncate option.
**How to avoid:** `upsert()` keyed on the unique `document_number` (D-06). Do NOT add a truncate/`--fresh` flag (D-07 — never delete rows absent from a newer file). The unique index on `document_number` is what makes upsert idempotent.
**Warning signs:** Row count doubling on a second run; a `->delete()` or `truncate()` anywhere in the command.

### Pitfall 5: SQLite test DB vs upsert conflict target
**What goes wrong:** `upsert()` needs the unique index to exist to know the conflict target; on SQLite (the test DB) a missing/miswritten unique index makes upsert insert duplicates instead of updating.
**Why it happens:** Tests run on `sqlite :memory:` (verified in `phpunit.xml`), which is a different engine than prod MySQL.
**How to avoid:** Ensure the migration declares `document_number` unique. Write the idempotency test to run the import twice and assert the row count is unchanged and a changed field was updated — this catches a wrong conflict key on both engines.
**Warning signs:** Idempotency test passes on MySQL but the second run duplicates on SQLite (or vice-versa).

## Code Examples

### Verify the divipol join actually resolves (executed during research — HIGH confidence)
```
CSV row:  280019909010,00;280019909;1100696116;28;0;0;1;;99;0;9;CHOCHO;10
          → dpto=28 mcpio=1 zona=99 puesto=9
polling_places / divipole-nacional.json key 28-1-99-9
          → { departamento: SUCRE, municipio: SINCELEJO, puesto: "CHOCHO", direccion: "IE SAN ISIDRO DE CHOCO" }  ✅

CSV row:  ...;28;0;0;1;;99;;78;LA GALLERA;5  → 28-1-99-78
          → { SUCRE, SINCELEJO, "LA GALLERA", "I.E LA GALLERA" }  ✅
```
Column order confirmed against the header: `divipol;codificado;cedula;dpto;cero;cero;mcpio;ref1;zona;ref2;puesto;nombre;mesa`. The join key is columns index `3,6,8,10` (`dpto,mcpio,zona,puesto`).

### Command test skeleton (Pest, in-repo command-test style)
```php
// Source: tests/Feature/DispatchBirthdayWebhooksTest.php ($this->artisan(...) pattern) + RefreshDatabase
use App\Models\NationalCensusRecord;
use App\Models\PollingPlace;

it('imports the snapshot, resolves polling_place_id, and reports unmatched %', function () {
    $place = PollingPlace::factory()->create([
        'dane_department_code' => 28, 'dane_municipality_code' => 1,
        'zone_code' => 99, 'place_code' => 9,
    ]);

    // fixture CSV: one matching row (28/1/99/9) + one unmatched row + one accented name (Ñ)
    $this->artisan('census:import-national', ['file' => base_path('tests/Fixtures/censo_sample.csv')])
        ->assertSuccessful();

    expect(NationalCensusRecord::where('document_number', '1100696116')->first()->polling_place_id)
        ->toBe($place->id);
    expect(NationalCensusRecord::whereNull('polling_place_id')->count())->toBe(1); // D-03
    expect(NationalCensusRecord::where('polling_station_name', 'LA PEÑATA')->exists())->toBeTrue(); // encoding
});

it('is idempotent on re-run', function () {
    // run twice → same row count, updated fields (D-06)
});
```
The fixture CSV must be authored **saved as ISO-8859-1** (not UTF-8) so the encoding assertion is meaningful.

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `utf8_encode()` / `utf8_decode()` | `mb_convert_encoding(..., 'UTF-8', 'ISO-8859-1')` | Deprecated PHP 8.2 | Must use mbstring; the old functions silently mangle bytes |
| Kernel-registered scheduled commands | `routes/console.php` `Schedule::` | Laravel 11+ | N/A here (this command is manual/ad-hoc, not scheduled) |
| `$casts` property | `casts()` method | Laravel 11+ | Use `casts()` for `imported_at` (follow `census_records` era, but method form is current) |

**Deprecated/outdated:** `utf8_decode` (do not use); `LOAD DATA LOCAL INFILE` is not deprecated but is explicitly rejected for this phase by D-01/D-02.

## Open Questions

1. **`document_number` type: string vs BIGINT**
   - What we know: max cédula is 2,000,015,490 (fits unsigned INT); `voters.document_number` and `census_records.document_number` are both `string`.
   - What's unclear: nothing blocking — this is a Claude's-discretion schema call.
   - Recommendation: **string**, for cross-table join consistency with `voters` (Phase 8 resolver joins these). Documented above.

2. **Progress/report output: console vs written file**
   - What we know: D-04 requires the unmatched % be reported; the mechanism is discretion.
   - Recommendation: console line (matches `ImportColombiaData`/`PollingPlaceSeeder` output style). A progress bar over 216K rows is optional nicety. No report file needed unless the planner wants an artifact.

3. **"National" file is Sincelejo-only**
   - What we know: 100% of rows are `dpto=28, mcpio=1`.
   - What's unclear: whether a truly national file will be supplied later this milestone.
   - Recommendation: build national-capable (no dept hardcoding); note in the phase summary that CENSO-03's report will show ~0% unmatched for this file, which is correct, not a bug.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP mbstring (`mb_convert_encoding`) | ISO-8859-1 decode | ✓ | PHP 8.4.14 | `iconv` CLI pre-pass |
| Laravel `LazyCollection` / `upsert` | streaming + idempotent load | ✓ | Laravel 12 | — |
| Source CSV file | the import itself | ✓ | `database/external-data/censo_decoded_202310210734.csv` (216,527 data rows, iso-8859-1) | — |
| Seeded `polling_places` reference data | divipol→id join + CENSO-03 report | ✓ (seeder exists; `divipole-nacional.json` present, 13,755 rows) | — | Import still runs with all rows null-matched if unseeded (report would show 100% unmatched — a useful signal) |
| `LOAD DATA LOCAL INFILE` / `local_infile` | NOT USED (D-01) | n/a | — | n/a — deliberately avoided |

**Missing dependencies with no fallback:** None.
**Missing dependencies with fallback:** None — all present. Note: the test suite runs on `sqlite :memory:` (verified in `phpunit.xml`), which is exactly why the LazyCollection+upsert path (not `LOAD DATA`) is the correct, testable choice.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 (PHPUnit 12 under the hood) |
| Config file | `phpunit.xml` (DB: `sqlite` `:memory:`) |
| Quick run command | `php artisan test --filter=ImportNationalCensus` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CENSO-02 | Import loads rows into cédula-unique `national_census_records`; lookup by `document_number` returns the row; `polling_place_id` resolved via divipol join | unit/feature | `php artisan test --filter=ImportNationalCensus` | ❌ Wave 0 |
| CENSO-02 | Accented Latin-1 name imports without corruption (`LA PEÑATA`) | feature | same | ❌ Wave 0 |
| CENSO-02 | Unmatched divipol row is imported with `polling_place_id = null` (D-03), not dropped | feature | same | ❌ Wave 0 |
| CENSO-03 | Command reports the unmatched percentage and never aborts (D-04) | feature (assert console output / returned counts) | same | ❌ Wave 0 |
| CENSO-02 (idempotency, success criterion #5) | Re-running the import produces no duplicate cédula rows (D-06) | feature | same | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter=ImportNationalCensus`
- **Per wave merge:** `php artisan test` (full suite)
- **Phase gate:** Full suite green + `vendor/bin/pint --dirty` clean before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/ImportNationalCensusTest.php` — covers CENSO-02, CENSO-03 (create)
- [ ] `tests/Fixtures/censo_sample.csv` — small **ISO-8859-1-encoded** fixture: ≥1 matching row, ≥1 unmatched-divipol row, ≥1 accented name (`Ñ`), ≥1 duplicate cédula (to prove last-row-wins/idempotency)
- [ ] `database/factories/NationalCensusRecordFactory.php` — for arranging assertions (`PollingPlaceFactory` already exists)
- [ ] Framework install: none needed — Pest 4 already present

## Sources

### Primary (HIGH confidence)
- Repo files (authoritative, read directly this session): `database/seeders/PollingPlaceSeeder.php` (keyed-map + chunked `upsert` precedent), `app/Services/CensusImporter.php` (batching precedent), `app/Models/PollingPlace.php` + `database/migrations/2026_01_22_000001_create_polling_places_table.php` (join target + column types), `database/migrations/2025_11_03_170817_create_census_records_table.php` (`document_number` string convention), `app/Console/Commands/ImportColombiaData.php` (command/progress precedent), `routes/console.php`, `phpunit.xml` (sqlite :memory: test DB), `tests/Feature/DispatchBirthdayWebhooksTest.php` (`$this->artisan` test pattern)
- Source data executed against this session: `database/external-data/censo_decoded_202310210734.csv` (216,527 data rows, `charset=iso-8859-1`, 0 duplicate cédulas, all `dpto=28/mcpio=1`, max cédula 2,000,015,490) and `database/external-data/divipole-nacional.json` (13,755 places; divipol join to CHOCHO/LA GALLERA confirmed)
- Milestone research: `.planning/research/STACK.md` (§CSV import), `.planning/research/ARCHITECTURE.md` (Decisions 1 & 4), `.planning/research/PITFALLS.md` (Pitfalls 2 & 9)

### Secondary (MEDIUM confidence)
- Laravel 12 `LazyCollection` / `upsert()` semantics — framework docs, consistent with in-repo `PollingPlaceSeeder` usage

### Tertiary (LOW confidence)
- None. Every claim in this document is verified against the repo or executed against the data.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all native, all with in-repo precedents
- Architecture / schema: HIGH — mirrors `polling_places`/`PollingPlaceSeeder`; join executed and confirmed
- Divipol join correctness: HIGH — ran it against two real rows, both resolved
- Pitfalls: HIGH — inherited from milestone research + verified data profile (encoding, duplicates, cardinality)

**Research date:** 2026-07-24
**Valid until:** stable (~30 days) — the source file and reference seed are static; no fast-moving external dependency
