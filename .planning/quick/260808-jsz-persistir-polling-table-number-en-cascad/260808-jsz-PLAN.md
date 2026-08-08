---
phase: quick-260808-jsz
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Services/PollingPlaceResolver.php
  - tests/Feature/Services/PollingPlaceResolverTest.php
  - app/Console/Commands/BackfillPollingTableNumber.php
  - tests/Feature/Console/BackfillPollingTableNumberTest.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "Automated resolutions (resolveAutomated(), used by ReconcileFallbackPollingPlaces and VoterValidationService::validateAgainstCensus()) persist the resolved mesa number to voters.polling_table_number, not just to the polling_place_resolutions audit trail"
    - "When the source doesn't return a mesa number but the resolved PollingPlace only has one possible mesa (max_tables === 1), the automated cascade defaults polling_table_number to '1' — but only when polling_table_number is currently null"
    - "When the source DOES return a real mesa number, it is always written to polling_table_number, overwriting whatever is currently there (real or previously-defaulted) — mirroring the existing polling_place_id guard's philosophy that real data can always correct stale/defaulted data; only the weaker single-mesa '1' default is guarded against overwriting anything"
    - "Apoyos affected by the historical bug (polling_place_id set, polling_table_number null) can be backfilled from the polling_place_resolutions audit trail or the single-mesa rule, using only already-local data — no live/paid lookups"
    - "The backfill command supports --dry-run and writes nothing when run in that mode"
  artifacts:
    - path: "app/Services/PollingPlaceResolver.php"
      provides: "persist() writes polling_table_number: always overwrites with result->tableNumber when it's non-null (real data always wins, matching the polling_place_id FK's overwrite philosophy), else fills '1' only when polling_table_number is currently null AND max_tables===1, else leaves the field untouched"
    - path: "app/Console/Commands/BackfillPollingTableNumber.php"
      provides: "census:backfill-polling-table-number Artisan command with --dry-run, mirroring BackfillPollingPlaceId.php's pattern"
    - path: "tests/Feature/Services/PollingPlaceResolverTest.php"
      provides: "Pest coverage for persist()'s new polling_table_number writes, including the overwrite-with-real-value and fill-only-default-when-null precedence"
    - path: "tests/Feature/Console/BackfillPollingTableNumberTest.php"
      provides: "Pest coverage for the new backfill command (history recovery, single-mesa default, dry-run)"
  key_links:
    - from: "app/Services/PollingPlaceResolver.php::persist()"
      to: "voters.polling_table_number"
      via: "$voter->update($updates) with a conditionally-added 'polling_table_number' key"
      pattern: "polling_table_number"
    - from: "app/Console/Commands/BackfillPollingTableNumber.php"
      to: "App\\Models\\Voter::pollingPlaceResolutions()"
      via: "whereNotNull('table_number')->recent()->first()"
      pattern: "pollingPlaceResolutions\\(\\)"
    - from: "app/Console/Commands/BackfillPollingTableNumber.php"
      to: "App\\Models\\PollingPlace::max_tables"
      via: "$voter->pollingPlace?->max_tables === 1"
      pattern: "max_tables"
---

<objective>
Fix a structural bug where `PollingPlaceResolver::persist()` — the sole write path for the ENTIRE automated resolution cascade (`resolveAutomated()`, used by `App\Jobs\ReconcileFallbackPollingPlaces` and `App\Services\VoterValidationService::validateAgainstCensus()`) — never writes the resolved mesa number (`voters.polling_table_number`) even though `PollingPlaceResolutionResult->tableNumber` carries a real value. The mesa number is currently only ever saved to the `polling_place_resolutions` audit table, never to the voter itself, for any voter resolved through the automated path. This is the exact sibling of the already-fixed `polling_place_id` bug (see `.planning/debug/resolved/polling-place-id-not-persisted-by-resolver.md`) — same root cause, same fix shape, same backfill-command precedent.

Additionally: when the source has no mesa number at all (`mesa_numero` blank/'0') but the resolved `PollingPlace` has `max_tables === 1`, it is safe to default `polling_table_number` to `'1'` — there is physically only one possible mesa at that puesto. This default must only ever fill a genuine gap (never overwrite anything already present). But when the source DOES carry a real mesa number, that real value must always win and overwrite whatever's currently stored — including a prior single-mesa default — so a defaulted '1' never permanently strands an apoyo with an incorrect mesa once the real number becomes discoverable on a later resolution. This mirrors how the sibling `polling_place_id` guard already permits overwriting with a newer non-null FK (`app/Services/PollingPlaceResolver.php:249-251`).

Purpose: Reports and widgets that depend on `voters.polling_table_number` (e.g. the Apoyo view page, exports) currently show "Sin resolver" for every automated resolution even when the real mesa number is known and sitting in the audit trail, undercounting data completeness the same way the `polling_place_id` bug did. Guarding against downgrades while still letting real data correct defaults keeps this data trustworthy, per this project's hard constraint that reporting numbers must reflect campaign reality.

Output: `persist()` fixed to write `polling_table_number` (with the corrected precedence: real values always win, the single-mesa default only fills gaps), a new `census:backfill-polling-table-number` Artisan command to fix already-affected historical apoyos, and Pest coverage for both.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@app/Services/PollingPlaceResolver.php
@app/Services/PollingPlaceResolutionResult.php
@app/Console/Commands/BackfillPollingPlaceId.php
@app/Models/Voter.php
@app/Models/PollingPlace.php
@app/Models/PollingPlaceResolution.php
@.planning/debug/resolved/polling-place-id-not-persisted-by-resolver.md
@tests/Feature/Services/PollingPlaceResolverTest.php
@tests/Feature/Console/BackfillPollingPlaceIdTest.php
</context>

<interfaces>
From `app/Services/PollingPlaceResolutionResult.php` (no changes needed — already carries what's needed):
```php
final readonly class PollingPlaceResolutionResult
{
    public function __construct(
        public PollingPlaceSource $source,
        public array $fields,
        public ?int $pollingPlaceId = null,
        public ?string $tableNumber = null,
    ) {}
}
```

From `app/Models/Voter.php` (already exists, no changes needed):
```php
public function pollingPlace(): BelongsTo            // -> PollingPlace, field 'max_tables' (int)
public function pollingPlaceResolutions(): HasMany    // -> PollingPlaceResolution, field 'table_number' (?string)
```

From `app/Models/PollingPlaceResolution.php` (already exists, no changes needed):
```php
public function scopeRecent(Builder $query): void   // orderBy('created_at', 'desc')
```

From `app/Models/PollingPlace.php` (already exists, no changes needed):
```php
protected $fillable = [..., 'max_tables', ...];   // int, bumped upward-only elsewhere in resolveOrCreatePollingPlace()
```

Current `persist()` update-building shape (`app/Services/PollingPlaceResolver.php`, ~line 244-252) — this is what Task 1 extends. Note how the sibling `polling_place_id` guard already overwrites unconditionally whenever `$result->pollingPlaceId !== null` (never gates on the voter's current value) — the `polling_table_number` fix's "real value always wins" branch follows this exact same shape:
```php
$updates = [
    'polling_place_source' => $result->source,
    'polling_place_resolved_at' => now(),
];

if ($result->pollingPlaceId !== null) {
    $updates['polling_place_id'] = $result->pollingPlaceId;
}

$voter->update($updates);
```
</interfaces>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Fix persist() to write polling_table_number (real values always win, single-mesa default only fills gaps)</name>
  <files>app/Services/PollingPlaceResolver.php, tests/Feature/Services/PollingPlaceResolverTest.php</files>
  <behavior>
    - persist() given a result carrying a non-null `tableNumber` ALWAYS writes it to `voters.polling_table_number`, overwriting whatever is currently there — whether the voter's current value is null, a real prior value, or a previously-applied single-mesa default.
    - persist() given a fresh voter (polling_table_number null) and a result with `tableNumber === null` but `pollingPlaceId` pointing at a PollingPlace with `max_tables === 1` writes `'1'`.
    - persist() given a voter that ALREADY has a non-null `polling_table_number` and a result with `tableNumber === null` (regardless of `max_tables`) NEVER overwrites it with the single-mesa default — the default only ever fills a currently-null value.
    - persist() given a result with `tableNumber === null` and either `pollingPlaceId === null` or a resolved PollingPlace with `max_tables !== 1` leaves `polling_table_number` untouched (stays whatever it already was — null or otherwise).
    - persist() given a voter whose `polling_table_number` was previously set to `'1'` by the single-mesa default, and a LATER result carrying a real, different `tableNumber`, overwrites `'1'` with that real value — a defaulted number never permanently strands an apoyo once the real mesa becomes known.
  </behavior>
  <action>
    In `app/Services/PollingPlaceResolver.php`'s `persist()` method (~line 228-268), add a `polling_table_number` write to the `$updates` array. The precedence is: a real `tableNumber` from the result ALWAYS wins and overwrites (mirrors the `polling_place_id` FK block immediately above it, which already overwrites unconditionally on any non-null value); the single-mesa `'1'` default is the ONLY branch guarded against overwriting, since it's a weaker inference than either a real value or a previously-applied default:

    ```php
    if ($result->tableNumber !== null) {
        $updates['polling_table_number'] = $result->tableNumber;
    } elseif ($voter->polling_table_number === null && $result->pollingPlaceId !== null) {
        $pollingPlace = PollingPlace::find($result->pollingPlaceId);

        if ($pollingPlace?->max_tables === 1) {
            $updates['polling_table_number'] = '1';
        }
    }
    ```

    Place this block directly after the existing `polling_place_id` block, before `$voter->update($updates)`. `App\Models\PollingPlace` is already imported at the top of this file — no new `use` statement needed.

    Extend the class docblock on `persist()` with a short paragraph mirroring the existing `polling_place_id` paragraph, referencing this as the sibling bug fix and linking to `.planning/debug/resolved/polling-place-id-not-persisted-by-resolver.md` for context (same root cause, same class of fix, for `polling_table_number` instead of the FK). Explicitly note that a real `tableNumber` always overwrites (same overwrite philosophy as the FK block), while the `'1'` single-mesa default is deliberately guarded to only fill a currently-null value — never overwriting an existing real or defaulted number, since we can't distinguish the two from the column alone and a weaker default must never clobber a stronger prior write.

    Add these Pest tests to `tests/Feature/Services/PollingPlaceResolverTest.php`, placed near the existing `polling_place_id` write tests (Test 12b-12e, in the `============ polling_place_id write in persist() ============` section) — add a new `============ polling_table_number write in persist() ============` section immediately after it:

    1. `persist writes polling_table_number on a fresh voter when the result carries one` — voter with `polling_table_number: null`, result with `tableNumber: '7'`, `pollingPlaceId: $this->pollingPlace->id`. Assert `$voter->fresh()->polling_table_number` equals `7`.
    2. `persist defaults polling_table_number to 1 when the result carries none but the resolved PollingPlace has max_tables===1` — update `$this->pollingPlace` to `max_tables: 1` first. Voter with `polling_table_number: null`, result with `tableNumber: null`, `pollingPlaceId: $this->pollingPlace->id`. Assert `$voter->fresh()->polling_table_number` equals `1`.
    3. `persist does NOT default polling_table_number when the resolved PollingPlace has max_tables greater than 1` — explicitly set `$this->pollingPlace`'s `max_tables` to a FIXED value greater than 1 (e.g. `PollingPlace::factory()->create([..., 'max_tables' => 5])`, or update the existing fixture with `->update(['max_tables' => 5])` before the assertion) — do NOT rely on the factory's default `max_tables`, which is `fake()->numberBetween(1, 20)` (`database/factories/PollingPlaceFactory.php:31`) and would make this test flaky (~1/20 runs draws exactly 1). Voter with `polling_table_number: null`, result with `tableNumber: null`, `pollingPlaceId: $this->pollingPlace->id`. Assert `$voter->fresh()->polling_table_number` is `null`.
    4. `persist ALWAYS overwrites an already-set polling_table_number when the result carries a real tableNumber` — voter with `polling_table_number: 3`, result with `tableNumber: '9'`, `pollingPlaceId: $this->pollingPlace->id`. Assert `$voter->fresh()->polling_table_number` becomes `9` (real data always corrects, matching the `polling_place_id` FK's overwrite behavior).
    5. `persist does NOT overwrite an already-set polling_table_number with the single-mesa default` — `$this->pollingPlace` with `max_tables: 1`. Voter with `polling_table_number: 3`, result with `tableNumber: null`, `pollingPlaceId: $this->pollingPlace->id`. Assert `$voter->fresh()->polling_table_number` stays `3`.
    6. `persist corrects a previously-defaulted polling_table_number once a later real tableNumber becomes available` — voter with `polling_table_number: '1'` (simulating an earlier single-mesa default write), result with `tableNumber: '4'`, `pollingPlaceId: $this->pollingPlace->id` (any `max_tables`, e.g. the fixture's default is fine here since the real-value branch doesn't consult `max_tables`). Assert `$voter->fresh()->polling_table_number` becomes `4` — the defaulted `'1'` never permanently strands the apoyo once the real mesa number is discovered.

    Use the same `PollingPlaceResolver([])`/`PollingPlaceResolutionResult(...)`/`resolvedVia: 'reconciliation'` call shape already used by the neighboring `persist` tests in this file.
  </action>
  <verify>
    <automated>cd "/Volumes/NAS(MAC)/Data/Herd/sigma-project" && php artisan test --filter="polling_table_number" tests/Feature/Services/PollingPlaceResolverTest.php</automated>
  </verify>
  <done>persist() writes polling_table_number under the corrected precedence (real values always overwrite; the single-mesa default only fills a currently-null value), and all 6 new tests plus the full existing PollingPlaceResolverTest.php suite pass.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Create census:backfill-polling-table-number command with Pest coverage</name>
  <files>app/Console/Commands/BackfillPollingTableNumber.php, tests/Feature/Console/BackfillPollingTableNumberTest.php</files>
  <behavior>
    - Command finds candidates via `Voter::whereNotNull('polling_place_id')->whereNull('polling_table_number')`.
    - For each candidate, recovers the mesa number from the most recent `PollingPlaceResolution` (via `Voter::pollingPlaceResolutions()`) with a non-null `table_number`, if one exists, and writes it.
    - If no history match exists, falls back to the single-mesa rule: if the voter's linked `PollingPlace` has `max_tables === 1`, writes `'1'`.
    - If neither applies, skips the voter (leaves `polling_table_number` null).
    - `--dry-run` computes and reports the same three counters (via-history / via-default / skipped) without writing any changes.
  </behavior>
  <action>
    Create `app/Console/Commands/BackfillPollingTableNumber.php`, mirroring `app/Console/Commands/BackfillPollingPlaceId.php`'s conventions exactly (declare(strict_types=1), explicit `use` statements, Spanish `$description`/messages, `--dry-run` option, `DB::transaction` for the real write path). Unlike the sibling command, this one needs no `PollingPlaceResolver` dependency at all — both recovery paths (`pollingPlaceResolutions()` audit history, `pollingPlace()->max_tables`) are plain local Eloquent relations already on `Voter`/`PollingPlace`, so it makes zero live/paid calls by construction. This command's scope is unchanged by Task 1's precedence fix: it only ever operates on voters where `polling_table_number` IS NULL (a pure gap-fill for historical data), so the "real value always overwrites" precedence introduced in `persist()` does not apply here — there is nothing to overwrite by construction:

    ```php
    <?php

    declare(strict_types=1);

    namespace App\Console\Commands;

    use App\Models\Voter;
    use Illuminate\Console\Command;
    use Illuminate\Support\Facades\DB;

    class BackfillPollingTableNumber extends Command
    {
        protected $signature = 'census:backfill-polling-table-number {--dry-run : List affected apoyos without writing any changes}';

        protected $description = 'Reconstruye polling_table_number para apoyos cuyo puesto de votación ya está enlazado pero cuya mesa nunca quedó guardada, usando solo datos ya locales (historial de resoluciones o la regla de mesa única), sin realizar ninguna consulta en vivo/pagada';

        public function handle(): int
        {
            $dryRun = (bool) $this->option('dry-run');

            $candidates = Voter::query()
                ->whereNotNull('polling_place_id')
                ->whereNull('polling_table_number')
                ->with('pollingPlace')
                ->get();

            $viaHistory = 0;
            $viaDefault = 0;
            $skipped = 0;

            $process = function () use ($candidates, $dryRun, &$viaHistory, &$viaDefault, &$skipped) {
                foreach ($candidates as $voter) {
                    $fromHistory = $voter->pollingPlaceResolutions()
                        ->whereNotNull('table_number')
                        ->recent()
                        ->first();

                    if ($fromHistory !== null) {
                        if (! $dryRun) {
                            $voter->update(['polling_table_number' => $fromHistory->table_number]);
                        }

                        $viaHistory++;

                        continue;
                    }

                    if ($voter->pollingPlace?->max_tables === 1) {
                        if (! $dryRun) {
                            $voter->update(['polling_table_number' => '1']);
                        }

                        $viaDefault++;

                        continue;
                    }

                    $skipped++;
                }
            };

            if ($dryRun) {
                $process();
            } else {
                DB::transaction($process);
            }

            $this->info(sprintf(
                '%d de %d apoyo(s) %s: %d desde el historial de resoluciones, %d por la regla de mesa única. %d no pudieron re-derivarse con datos locales.%s',
                $viaHistory + $viaDefault,
                $candidates->count(),
                $dryRun ? 'serían actualizados' : 'fueron actualizados',
                $viaHistory,
                $viaDefault,
                $skipped,
                $dryRun ? ' (dry-run, sin cambios)' : ''
            ));

            return self::SUCCESS;
        }
    }
    ```

    Create `tests/Feature/Console/BackfillPollingTableNumberTest.php`, mirroring `tests/Feature/Console/BackfillPollingPlaceIdTest.php`'s `beforeEach` fixture shape (Department + Municipality + PollingPlace factories). Cover:

    1. `backfills polling_table_number from the most recent polling_place_resolutions history row` — a voter with `polling_place_id` set and `polling_table_number: null`; create two `PollingPlaceResolution::factory()` rows for that voter with different `table_number` values, the later-created one being the one that should win (use explicit `created_at` or creation order to control "most recent"). Assert the voter's `polling_table_number` after running the command equals the most recent row's `table_number`.
    2. `applies the single-mesa default when no history exists but the linked PollingPlace has max_tables===1` — voter with `polling_place_id` pointing at a PollingPlace with `max_tables: 1`, no `PollingPlaceResolution` rows, `polling_table_number: null`. Assert it becomes `1` after running the command.
    3. `skips a voter with no history and a PollingPlace with max_tables greater than 1` — PollingPlace with a FIXED `max_tables` value greater than 1 (e.g. `'max_tables' => 5`, not the factory default, to avoid the same flakiness risk as Task 1's Test 3), no history rows. Assert `polling_table_number` stays `null`.
    4. `ignores voters whose polling_table_number is already set` — voter with `polling_place_id` set and `polling_table_number: 3`, plus a history row with a different `table_number`. Assert it stays `3` (not selected as a candidate at all, since the whereNull() filter excludes it).
    5. `dry-run mode writes nothing` — same setup as test 1 (history match exists). Run with `['--dry-run' => true]`. Assert `polling_table_number` stays `null` after the run.

    Run `vendor/bin/pint --dirty` on both new/modified files after this task to ensure PSR-12/project style compliance before finishing.
  </action>
  <verify>
    <automated>cd "/Volumes/NAS(MAC)/Data/Herd/sigma-project" && php artisan test tests/Feature/Console/BackfillPollingTableNumberTest.php</automated>
  </verify>
  <done>census:backfill-polling-table-number command exists, follows BackfillPollingPlaceId.php's exact conventions, supports --dry-run with zero writes, and all 5 new tests pass.</done>
</task>

</tasks>

<verification>
Run the full affected suite to confirm no regressions across both the resolver fix and the new command:
```
cd "/Volumes/NAS(MAC)/Data/Herd/sigma-project" && php artisan test tests/Feature/Services/PollingPlaceResolverTest.php tests/Feature/Services/PollingPlaceResolverPriorityTest.php tests/Feature/Console/BackfillPollingPlaceIdTest.php tests/Feature/Console/BackfillPollingTableNumberTest.php tests/Feature/Jobs/ReconcileFallbackPollingPlacesTest.php
```
All tests pass. Run `vendor/bin/pint --dirty` across the full worktree to confirm no stray style diffs remain.

Do NOT run `census:backfill-polling-table-number` against any real database in this task — same scope boundary as the sibling `census:backfill-polling-place-id` command; execution against sigma-betha/Aldemar production data is left to the user, per the sibling bug's precedent in `.planning/debug/resolved/polling-place-id-not-persisted-by-resolver.md`.
</verification>

<success_criteria>
- `PollingPlaceResolver::persist()` writes `voters.polling_table_number` for every automated resolution that carries a real mesa number, ALWAYS overwriting whatever is currently there (matching the `polling_place_id` FK's overwrite philosophy) — and defaults to `'1'` when the resolved puesto has exactly one possible mesa, but ONLY when `polling_table_number` is currently null.
- A defaulted `'1'` never permanently strands an apoyo: a later resolution carrying a real, different mesa number correctly overwrites it.
- A new `census:backfill-polling-table-number` Artisan command exists, mirrors `census:backfill-polling-place-id`'s conventions, supports `--dry-run`, and correctly recovers historical mesa numbers from `polling_place_resolutions` or applies the single-mesa default.
- All new and existing Pest tests pass (including a fixed, non-flaky Test 3 that pins `max_tables` to a value greater than 1 instead of relying on the factory's random default); `vendor/bin/pint --dirty` clean.
- `ViewVoter.php` is left untouched — it already renders `polling_table_number` correctly from the prior quick task (260808-f0x), and will now show real values instead of "Sin resolver" once persist()'s fix and/or the backfill command run.
</success_criteria>

<output>
After completion, create `.planning/quick/260808-jsz-persistir-polling-table-number-en-cascad/260808-jsz-SUMMARY.md`
</output>
