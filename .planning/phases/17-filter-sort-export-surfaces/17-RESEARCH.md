# Phase 17: Filter/Sort/Export Surfaces - Research

**Researched:** 2026-08-11
**Domain:** Laravel 12 / Eloquent subquery patterns, Filament v4 custom Filter + dynamic TextColumn, maatwebsite/excel `FromQuery` exports
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**UX del Filtro de Metadata (FILT-01)**
- **D-01:** One generic cascading "Metadata" filter (not one filter per catalog key). The filter shows a Select of active metadata keys; once a key is chosen, the value field renders according to that key's type (Select of `options` for `select`, `TextInput::numeric()` for `numeric`, `DatePicker` for `date`, plain `TextInput` for `text`) — mirroring the field-type-per-type pattern already established in the Phase 16 assignment forms.
- **D-02:** Only one metadata condition can be applied at a time (no AND-combination across two different metadata keys in the same query). Combining multiple metadata filters simultaneously is explicitly out of scope for this phase.

**Semántica del Filtro por Tipo**
- **D-03:** Filter matching is exact-value equality for ALL types — `numeric`, `text`, `date`, and `select` all filter on exact match of the current value. No range operators (≥/≤) for `numeric`/`date` in this phase.

**Columnas de Metadata en la Tabla (FILT-02)**
- **D-04:** For each active metadata key in the catalog, dynamically generate one `TextColumn` in every one of the four tables (Users, Coordinators, Leaders, AreaCoordinators), showing that user's current value for the key. Each column is `toggleable(isToggledHiddenByDefault: true)` and `sortable()`. Sorting must resolve the "current value" per user (latest row by `assigned_at` DESC, `id` DESC tiebreak per Phase 16 D-09) and, for `numeric`-typed keys, sort numerically (cast) rather than lexicographically.
- **D-05:** Sorting is triggered by clicking the column header, standard Filament table-sort UX — no separate "sort by metadata" control outside the normal column system.

**Alcance y Columnas de los Exports (FILT-03)**
- **D-06:** Exports in scope: `CoordinatorsExport`, `LeadersExport`, `AnnotatorsExport`, `WitnessesExport`. No new export class is created.
- **D-07:** `TopCoordinatorsExport`, `TopLeadersExport`, `TopPollingPlacesExport`, `DuplicatesExport`, `RejectionsExport`, `JurisdictionExport`, `VotersExport`, `ApoyosLideresCoordinadoresExport` are explicitly OUT of scope.
- **D-08:** Each in-scope export includes one column per **active** metadata key from the catalog — not just keys that happen to have an assigned value among the exported rows. Blank cell when the exported user has no value for that key.
- **D-09:** No Articuladores/AreaCoordinators export exists today and none is created in this phase.

### Claude's Discretion
- Exact SQL approach for resolving "current value per user per metadata key" at table-query scale (subquery, correlated join, or other) — a query-level equivalent to `MetadataAssignmentService::currentValues()` must be designed. Follow existing service-layer conventions; this is implementation detail, not a UX decision.
- Exact column key/id naming for the dynamically generated per-metadata-key TextColumns (e.g. `metadata_{id}` vs `metadata.{key}`), and where in the column list they're inserted relative to existing columns.
- Whether the generic metadata filter and the dynamic per-key columns share one underlying query-building helper (e.g. a `MetadataTableColumns`/`MetadataTableFilter` class in `app/Filament/Schemas/` or similar, mirroring the existing `App\Filament\Schemas\MetadataAssignment` naming pattern) — follow existing naming/organization conventions from Phase 16.
- Export column header wording for metadata columns — use the key's existing `label` field, consistent with how the assignment UI already displays labels.

### Deferred Ideas (OUT OF SCOPE)
- Combining multiple metadata-key filter conditions simultaneously (AND across keys) — deferred per D-02.
- Range filtering (≥/≤) for `numeric`/`date`-typed metadata keys — deferred per D-03; exact-match only in this phase.
- Extending metadata filter/sort to the Volt-based self-service panels (coordinador's líderes list, articulador's coordinadores list) — explicitly out of scope; ROADMAP.md's success criteria name "Filament tables" only.
- A generic "export all users" CSV/xlsx (not role-scoped) — does not exist today; not created in this phase (D-06).
- Metadata columns/filter/sort for Top-N reporting exports — deferred per D-07, out of FILT-03's scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| FILT-01 | Filament tables for users/coordinators/leaders/articuladores allow filtering by metadata key and value | `Architecture Patterns` → Pattern 2 (custom `Filter::make()` with subquery-based `where()` comparison), `Code Examples` |
| FILT-02 | Same tables allow sorting by a metadata key's value, with correct numeric order for numeric-typed keys | `Architecture Patterns` → Pattern 1 (`addSelect` correlated subquery with per-type `CAST`), verified against Filament's `applySort()` default behavior |
| FILT-03 | Existing CSV/xlsx exports for users/coordinadores/líderes include columns for assigned metadata | `Architecture Patterns` → Pattern 3 (reusing Pattern 1's `addSelect` on the export's `query()`, dynamic `headings()`/`map()`) |
</phase_requirements>

## Summary

All the SQL techniques this phase needs are native Laravel query-builder features, verified directly against the installed vendor source (Laravel 12 framework, `filament/filament` v4.2.0, `maatwebsite/excel` 3.1.67) rather than assumed from training data. No new package is required.

The core problem — "current value of `UserMetadataValue` per (user, metadata_key), where the table is append-only and 'current' means latest by `assigned_at` DESC, `id` DESC" — has a single canonical solution that serves all three concerns (column display, sortable column, filter, export) with **one shared query-building method**: a correlated scalar subquery. Laravel's `Builder::addSelect(['alias' => $subqueryBuilder])` (documented as "Selecting Subqueries") attaches one row-scoped value per user as a real column alias; that alias is then both directly readable on the hydrated model (`$record->metadata_5`) and directly sortable via a plain `->orderBy('metadata_5', $direction)` — confirmed by reading Filament's `Column::applySort()` source, which calls `$query->orderBy($sortColumn, $direction)` **unqualified** (no `users.` prefix) when the column name has no dot, so it naturally resolves against the SELECT-list alias in both MySQL and SQLite. For `numeric`-typed keys, embedding `CAST(value AS DECIMAL(20,4))` directly inside the subquery's `SELECT` clause (rather than in `ORDER BY`) makes the alias itself numeric, so `sortable()` needs **no custom `query:` callback at all** — the default path already sorts correctly.

For the filter, Laravel's `where($queryableColumn, $operator, $value)` overload (verified in `Illuminate\Database\Query\Builder::where()`) natively supports passing an Eloquent `Builder` as the "column" — it wraps it as `WHERE (subquery) = ?`. This is the same "latest row" subquery (unaliased, `SELECT value ... LIMIT 1`), reused for exact-match comparison. No `whereExists`/`whereRaw` gymnastics needed.

Both the `CAST(value AS DECIMAL(20,4))` expression and the correlated-subquery pattern were verified to behave identically on this project's two live database engines: MySQL 8.0.45 (dev/prod, confirmed via `SELECT VERSION()`) and SQLite (bundled with PHP 8.4, 3.45.2; tests run on `:memory:` per `phpunit.xml`). A direct `sqlite3` CLI test confirmed `CAST('10' AS DECIMAL(20,4))` sorts numerically and gracefully degrades non-numeric strings to `0` rather than erroring — the same behavior expected from MySQL's non-strict-mode CAST.

Because all four Filament resources (`UserResource`, `CoordinatorResource`, `LeaderResource`, `AreaCoordinatorResource`) share the same underlying `App\Models\User` / `users` table (differentiated only by a `->role(...)` scope in `getEloquentQuery()`), and the four in-scope exports also query `User::query()`, exactly **one** shared method (e.g., on `MetadataAssignmentService` or a new sibling service) can build the `addSelect` array and the filter subquery, consumed identically by the Filament `Table::modifyQueryUsing()` hook and by each export class's `query()` method. This avoids hand-rolling three different resolutions of "current value" (one for columns, one for the filter, one for exports) — reuse is the primary "Don't Hand-Roll" risk this phase must guard against, since the temptation (and the trap `MetadataAssignmentService::currentValues()` demonstrates) is to fetch-all-then-group in PHP, which does not scale to a filtered/sorted/paginated table query.

**Primary recommendation:** Build one query-level helper (e.g. `MetadataAssignmentService::withCurrentValueSelects(Builder $query, ?Collection $keys = null)` and `::applyMetadataFilter(Builder $query, MetadataKey $key, string $value)`) using the `addSelect`-correlated-subquery pattern verified below. Call it from `Table::modifyQueryUsing()` on all four tables (for columns + default `sortable()`), from a custom `Filter::make('metadata')->schema([...])->query(...)` (for the filter), and from each export class's `query()` method (for FILT-03). Do not introduce window functions, raw joins, or PHP-side grouping — the subquery approach is simpler, portable across MySQL 8 and SQLite, and verified safe for Filament's pagination COUNT(*) query (Laravel strips `columns`/`select` bindings for the count query when there's no `GROUP BY`/`HAVING`, confirmed in `Query\Builder::runPaginationCountQuery()`).

## Standard Stack

No new packages required. This phase is entirely implementable with what's already installed.

### Core (already installed — verified versions)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| laravel/framework | v12 (installed) | `addSelect`/subquery-`where` query builder | Native Eloquent feature, no package needed |
| filament/filament | v4.2.0 (confirmed via `composer show`) | `Filter::make()->schema()->query()`, `TextColumn::sortable()`, `Table::modifyQueryUsing()` | Existing admin panel framework |
| maatwebsite/excel | 3.1.67 (confirmed via `composer show`, released 2025-08-26) | `FromQuery`/`WithHeadings`/`WithMapping` exports | Already used by all 4 in-scope export classes |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Correlated subquery (`addSelect`/subquery-`where`) | Window function (`ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY assigned_at DESC, id DESC)`) via a raw CTE/derived table | Both MySQL 8 and SQLite 3.45+ support window functions, so this is technically viable — but it requires a derived-table join (`whereRaw`/`joinSub`) instead of the framework-native `addSelect`/subquery-`where` overloads, is harder to reuse identically across Filament columns, the filter, and the export `query()` methods, and is not needed at this data scale (per-campaign user counts, not millions of rows). Not recommended. |
| Correlated subquery | PHP-side grouping (mirroring `MetadataAssignmentService::currentValues()`) | This is the *existing* single-user pattern. It does not scale to a filtered/sorted/paginated table query — you cannot `ORDER BY` or `WHERE` on a value computed after the SQL query already ran. This is exactly what 16-RESEARCH.md flagged as needing "a window function or a subquery join" for Phase 17. Do not reuse `currentValues()` for table-scale work. |

**Version verification performed:**
```bash
composer show maatwebsite/excel   # 3.1.67, released 2025-08-26
composer show filament/filament   # v4.2.0, released 2025-11-02
php artisan tinker --execute="echo DB::connection()->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);"  # 8.0.45 (dev/prod MySQL)
php -r "echo (new PDO('sqlite::memory:'))->query('select sqlite_version()')->fetchColumn();"  # 3.45.2 (PHP-bundled, used by tests per phpunit.xml)
```

## Architecture Patterns

### Recommended Project Structure

No new folders. Extend the existing Phase 16 structure:

```
app/
├── Services/
│   └── MetadataAssignmentService.php   # ADD: withCurrentValueSelects(), applyMetadataFilter()
├── Filament/
│   └── Schemas/
│       ├── MetadataAssignment.php      # existing (Phase 16) — untouched
│       ├── MetadataTableColumns.php    # NEW — dynamic TextColumn[] builder
│       └── MetadataTableFilter.php     # NEW — the D-01 cascading Filter::make()
├── Exports/
│   ├── CoordinatorsExport.php          # MODIFY: query() adds selects, headings()/map() append per-key
│   ├── LeadersExport.php               # MODIFY: same
│   ├── AnnotatorsExport.php            # MODIFY: same
│   └── WitnessesExport.php             # MODIFY: same
```

### Pattern 1: `addSelect` correlated subquery for "current value" (columns + default sort)

**What:** Attach one aliased column per active metadata key to the base `users` query, each resolving to the metadata value with the D-09 latest-row semantics baked in (`ORDER BY assigned_at DESC, id DESC LIMIT 1`), with a per-type `CAST` for `numeric` keys so the alias itself sorts correctly.

**When to use:** Any time you need "the current value of key X for each row of a Users/Coordinators/Leaders/AreaCoordinators/export query" — this is the ONE mechanism for all of FILT-02 (columns+sort) and FILT-03 (exports).

**Verified mechanics (read directly from vendor source, not recalled from training):**
- `Illuminate\Database\Query\Builder::addSelect($column)` (`vendor/laravel/framework/.../Query/Builder.php:438`) accepts `['alias' => $subqueryBuilder]` and internally calls `selectSub($subqueryBuilder, 'alias')`, auto-selecting `from.*` first if no columns were set yet.
- `Filament\Tables\Columns\Concerns\InteractsWithTableQuery::applySort()` (`vendor/filament/tables/src/Columns/Concerns/InteractsWithTableQuery.php:110`) — when no custom `sortQuery` closure is set, calls `$query->orderBy($sortColumn, $direction)` where `$sortColumn` is exactly the string passed to `TextColumn::make(...)`, **not qualified with a table prefix**. This means `TextColumn::make('metadata_5')->sortable()` "just works" against a `addSelect(['metadata_5' => ...])` alias — no custom `sortable(query: ...)` needed.
- `Illuminate\Database\Query\Builder::runPaginationCountQuery()` (`vendor/laravel/framework/.../Query/Builder.php:3346`) — for the common no-`GROUP BY`/no-`HAVING` case (this phase's case), the pagination COUNT(*) query is built via `cloneWithout(['columns', 'orders', 'limit', 'offset'])->cloneWithoutBindings(['select', 'order'])`, which **drops the `addSelect` subqueries and their bindings entirely**. Confirms `addSelect` subqueries add zero overhead to Filament's pagination count query.

```php
// Source: verified against vendor/laravel/framework (Laravel 12) and this project's schema
// app/Services/MetadataAssignmentService.php (extend the existing class)

use App\Models\UserMetadataValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

public function withCurrentValueSelects(Builder $query, ?Collection $keys = null): Builder
{
    $keys ??= $this->activeKeys();
    $table = $query->getModel()->getTable(); // 'users' for all four resources

    foreach ($keys as $key) {
        $valueExpression = $key->type === 'numeric'
            ? DB::raw('CAST(value AS DECIMAL(20,4))')
            : 'value';

        $query->addSelect([
            "metadata_{$key->id}" => UserMetadataValue::query()
                ->select($valueExpression)
                ->whereColumn('user_id', "{$table}.id")
                ->where('metadata_key_id', $key->id)
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')
                ->limit(1),
        ]);
    }

    return $query;
}
```

`DB::raw()` for the `CAST` expression matches existing precedent in this codebase (`app/Filament/Widgets/CampaignStatsOverview.php`, `TerritorialDistributionChart.php`, `CallCenterStatsWidget.php` all use `DB::raw`) — this is a narrow, justified raw-expression use, distinct from CLAUDE.md's "avoid `DB::`, prefer `Model::query()`" guidance (which targets `DB::table()`/`DB::select()` replacing Eloquent, not `DB::raw()` inside an Eloquent query).

**Sort direction UX note (date/text/select keys):** `date`-typed values are stored as `Y-m-d` strings (per `MetadataAssignment`'s `DatePicker::format('Y-m-d')`), which sort correctly lexicographically — no `CAST` needed for `date`. `text`/`select` keys sorting lexicographically is expected/acceptable (FILT-02 only mandates numeric correctness for `numeric`-typed keys).

### Pattern 2: Subquery-`where` for exact-match filter (FILT-01)

**What:** A custom Filament `Filter::make('metadata')` whose `query()` callback compares the "latest value" subquery directly against the user-selected value, using Laravel's native subquery-comparison overload of `where()`.

**Verified mechanics:**
- `Illuminate\Database\Query\Builder::where()` (`vendor/laravel/framework/.../Query/Builder.php`, `isQueryable($column) && ! is_null($operator)` branch) — when `$column` is an `EloquentBuilder`/`Closure`/`Query\Builder` **and** an `$operator` is given, Laravel treats it as "run a subquery and compare the result... with the given value", producing `WHERE (subquery) = ?`. `isQueryable()` explicitly includes `EloquentBuilder` instances. This is Laravel's documented "Subquery Where Clauses" pattern (Query Builder docs), confirmed directly against the installed framework source, not assumed.
- `Filament\Tables\Filters\Concerns\HasSchema`/`InteractsWithTableQuery` (both read from `vendor/filament/tables/src/Filters/`) confirm `Filter::make('name')->schema([...])->query(fn (Builder $query, array $data) => ...)` is the correct v4 API for a custom multi-field filter — `schema()` overrides the default single-checkbox form field, and `query()`'s `$data` array is the filter's submitted form state (keyed by the field names inside `schema()`).

```php
// Source: verified against vendor/filament/tables v4.2.0 and vendor/laravel/framework (Laravel 12)
// app/Filament/Schemas/MetadataTableFilter.php

use App\Models\MetadataKey;
use App\Models\UserMetadataValue;
use App\Services\MetadataAssignmentService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class MetadataTableFilter
{
    public static function make(): Filter
    {
        return Filter::make('metadata')
            ->label('Metadata')
            ->schema([
                Select::make('metadata_key_id')
                    ->label('Llave')
                    ->options(fn (): array => app(MetadataAssignmentService::class)->activeKeyOptions())
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('value', null)),

                // ...same per-type value fields as MetadataAssignment::modalSchema(), D-01
            ])
            ->query(function (Builder $query, array $data): Builder {
                if (blank($data['metadata_key_id'] ?? null) || blank($data['value'] ?? null)) {
                    return $query;
                }

                $key = app(MetadataAssignmentService::class)->findActiveKey($data['metadata_key_id']);

                if (! $key) {
                    return $query;
                }

                $table = $query->getModel()->getTable();

                $latestValue = UserMetadataValue::query()
                    ->select('value')
                    ->whereColumn('user_id', "{$table}.id")
                    ->where('metadata_key_id', $key->id)
                    ->orderByDesc('assigned_at')
                    ->orderByDesc('id')
                    ->limit(1);

                return $query->where($latestValue, '=', (string) $data['value']);
            });
    }
}
```

D-03 mandates exact-value string equality for every type — this filter compares the raw `value` string column (no `CAST`), independent of Pattern 1's numeric-cast column used for sorting. Do not reuse the numeric-cast alias for the filter comparison — `'10' = '10.0000'` would not match textually even though they're numerically equal, and D-03 explicitly wants simple equality, not numeric comparison.

### Pattern 3: Reuse Pattern 1 inside export `query()` (FILT-03)

**What:** Each in-scope export's `query()` method already returns an Eloquent `Builder` (`FromQuery` contract). Call `withCurrentValueSelects()` on it before returning, then read `$row->{"metadata_{$key->id}"}` inside `map()` — no extra queries per row (avoids the N+1 the phase description explicitly warns about).

```php
// Source: pattern verified against maatwebsite/excel 3.1.67 FromQuery contract + Pattern 1 above
// app/Exports/CoordinatorsExport.php (illustrative diff)

class CoordinatorsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected Collection $activeMetadataKeys;

    public function __construct(/* ...existing params... */)
    {
        // ...existing assignment...
        $this->activeMetadataKeys = app(MetadataAssignmentService::class)->activeKeys();
    }

    public function query(): Builder
    {
        $builder = $this->queryBuilder ? (clone $this->queryBuilder) : User::query();

        $builder->with(['municipality', 'neighborhood']);

        if (! $this->queryBuilder) {
            $builder
                ->when($this->campaignIds, fn ($q) => $q->whereHas('campaigns', fn ($qq) => $qq->whereIn('campaigns.id', $this->campaignIds)))
                ->when($this->municipalityIds, fn ($q) => $q->whereIn('municipality_id', $this->municipalityIds))
                ->role('coordinator');
        }

        return app(MetadataAssignmentService::class)->withCurrentValueSelects($builder, $this->activeMetadataKeys);
    }

    public function headings(): array
    {
        return [
            'ID', 'Nombre', 'Email', 'Teléfono', 'Municipio', 'Barrio', 'Campañas', 'Apoyos Registrados', 'Fecha de Creación',
            ...$this->activeMetadataKeys->pluck('label')->all(), // D-08: label verbatim, one per active key
        ];
    }

    public function map($coordinator): array
    {
        return [
            // ...existing 9 columns...
            ...$this->activeMetadataKeys->map(fn ($key) => $coordinator->{"metadata_{$key->id}"} ?? '')->all(), // D-08: blank when no value
        ];
    }
}
```

Important: the `Controller` that builds a `$queryBuilder` and passes it into the export (e.g. `CoordinatorsExportController`) does **not** need to call `withCurrentValueSelects()` itself — it's applied once, inside each export's own `query()` method, after the controller's `$queryBuilder` (or the export's own default builder) is resolved. This keeps the metadata-select logic in exactly one place per export class and mirrors the existing `->with(['municipality', 'neighborhood'])` line already inside `query()`.

### Anti-Patterns to Avoid
- **Reusing `MetadataAssignmentService::currentValues()`/`currentValueFor()` for table-scale work:** these fetch all history rows for a *single* user and dedupe in PHP (`Collection::unique('metadata_key_id')`) — correct for the Phase 16 single-record "current values" panel, but it cannot be filtered/sorted/paginated at the SQL level. Do not call these methods per-row inside a `getStateUsing()` closure for the dynamic columns — that reintroduces the N+1 the phase explicitly warns against.
- **Casting in `ORDER BY` instead of `SELECT`:** `->orderByRaw('CAST(metadata_5 AS DECIMAL(20,4))')` would work but defeats the purpose of `sortable()`'s default unqualified-column behavior and requires a custom `sortable(query: ...)` closure per numeric column. Baking the `CAST` into the `addSelect` subquery's own `SELECT` clause (Pattern 1) means the alias is already numeric and the column's plain `->sortable()` needs no override.
- **Applying the numeric-cast alias to the filter's equality check:** D-03 wants exact string-value equality for every type, not numeric comparison — Pattern 2 deliberately re-derives its own uncast `value`-only subquery rather than reusing Pattern 1's numeric-cast alias.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| "Latest row per (user, key)" at query scale | A PHP loop that fetches all `UserMetadataValue` rows and reduces them per column/row | `addSelect` correlated subquery (Pattern 1) | SQL-level resolution scales with pagination/filtering/sorting; PHP-side grouping only works for a single already-loaded record (Phase 16's use case), not a queryable table |
| Numeric sort of a string column | Manual `usort()`/`Collection::sortBy` after fetching all rows | `CAST(value AS DECIMAL(20,4))` inside the `addSelect` subquery | Correct numeric order must survive pagination — sorting a single fetched page in PHP after the fact would be sorting the wrong 15/25 rows (the ones the *lexicographic* DB-level order picked), not the true top-N |
| Cross-database CAST/window portability | Hand-testing MySQL-only syntax and hoping it "also works" on SQLite | The verified `CAST(value AS DECIMAL(20,4))` form (works identically on MySQL 8.0.45 and SQLite 3.45+, confirmed via direct `sqlite3` CLI test) | Tests run on SQLite `:memory:` (`phpunit.xml`), production runs MySQL — an approach that only works on one engine will pass locally against MySQL but fail CI, or vice versa |

**Key insight:** The single correlated-subquery method (Pattern 1) is deliberately reused for columns, sorting, AND exports rather than writing three separate resolutions — this is the phase's central "don't repeat the append-only-latest-row logic" risk, mirroring how `MetadataAssignmentService` already centralizes assignment logic in one place per Phase 16 convention.

## Common Pitfalls

### Pitfall 1: Forgetting `Table::modifyQueryUsing()` on all four resources
**What goes wrong:** The dynamic `TextColumn`s render but show blank/error, or `sortable()` throws "unknown column" — because the `addSelect` aliases were never attached to the table's base query.
**Why it happens:** `TextColumn::make('metadata_5')` alone does nothing to the query; the `addSelect` must be applied separately via `$table->modifyQueryUsing(fn (Builder $query) => app(MetadataAssignmentService::class)->withCurrentValueSelects($query))`, verified as the correct Filament v4 Table-level hook (`Filament\Tables\Table\Concerns\HasQuery::modifyQueryUsing()`, already used elsewhere in this codebase for Filament Forms, e.g. `CoordinatorForm.php`).
**How to avoid:** Add the `modifyQueryUsing()` call in the same `configure()` method as the dynamic columns/filter, on all four `*Table.php` files.
**Warning signs:** Filament error "column not found" on first sort click, or the metadata column always renders blank.

### Pitfall 2: `toggleable(isToggledHiddenByDefault: true)` does not skip the `addSelect`
**What goes wrong:** Because `Table::modifyQueryUsing()` runs once against the whole query (not per visible column), every active metadata key's subquery executes on every table load, even for columns hidden by the D-04 toggle default. For a small catalog (a handful of keys) this is negligible; for a catalog that grows very large, this could add meaningful per-row subquery overhead even when the operator never toggles those columns on.
**Why it happens:** Filament's column-visibility toggle is a display-layer concern; it does not automatically prune `addSelect` calls made outside the column's own query-building lifecycle.
**How to avoid:** Not a blocker for this phase's expected catalog size (Phase 16's `MetadataKeyResource` has no seeded data — expect low dozens of keys at most). Flag as an Open Question below in case the planner wants a `visible()`-aware version (e.g., only building `addSelect` for keys backing a currently-toggled-on column, which Filament does not expose cleanly pre-render).
**Warning signs:** Table load time growing noticeably as the metadata catalog grows past ~20-30 active keys.

### Pitfall 3: MySQL strict-mode behavior on non-numeric `value` for a `numeric`-typed key
**What goes wrong:** If historic data somehow has a non-numeric `value` row for a `numeric`-typed key (shouldn't happen given `MetadataAssignmentService::validateValue()`'s `is_numeric()` guard, but the table has no DB-level CHECK constraint), `CAST('abc' AS DECIMAL(20,4))` on MySQL 8 in strict SQL mode returns `0` with a truncation **warning**, not an error (confirmed behavior for `CAST`, distinct from implicit-conversion errors elsewhere in MySQL strict mode) — SQLite behaves the same way (verified directly, returns `0`). This is a MEDIUM-confidence claim for the MySQL side specifically (verified via general MySQL CAST semantics, not re-tested against this project's exact `sql_mode` setting) — worth a quick manual `SELECT CAST('abc' AS DECIMAL(20,4))` sanity check against the actual dev DB connection during implementation if paranoid.
**How to avoid:** Rely on the existing `validateValue()` write-time guard (already enforced by `MetadataAssignmentService::assign()`) as the real data-integrity backstop; treat the `CAST` fallback-to-zero as a defensive last resort, not the primary correctness mechanism.

### Pitfall 4: Reusing the numeric-cast alias for the exact-match filter
**What goes wrong:** If the filter (Pattern 2) reused Pattern 1's `CAST`-ed `addSelect` alias for its equality comparison, a numeric value typed as `"10"` in the filter's `TextInput::numeric()` might fail to match a stored `"10.00"` (both numerically 10, but `CAST(...) = 10` vs `CAST(...) = 10.00` — actually DECIMAL comparison would pass, so this specific example is fine — but it silently changes D-03's contract from "string equality" to "numeric equality" without the user asking for it, and doesn't work at all for `text`/`select`/`date` types which have no cast).
**How to avoid:** Keep Pattern 2's subquery independent of Pattern 1's cast alias — plain `value` string equality, per D-03. Documented explicitly in Pattern 2 above.

### Pitfall 5: `UserMetadataValue`'s `restrictOnDelete()` FK does not protect against dangling `metadata_key_id` filter values
**What goes wrong:** Not really a pitfall for this phase since `MetadataKey`s are soft-deactivated (`is_active`), never hard-deleted (per META-01/Out of Scope table) — `findActiveKey()` already guards the filter/column generation to only ever reference keys that still exist and are active. No action needed, documented here only to confirm the existing `is_active` scoping (already used by `activeKeys()`/`findActiveKey()`) is sufficient and no additional existence check is needed in the new Filter/Column code.

## Code Examples

### Dynamic column generation (FILT-02)

```php
// Source: pattern verified against filament/tables v4.2.0 TextColumn/Column API
// app/Filament/Schemas/MetadataTableColumns.php

namespace App\Filament\Schemas;

use App\Models\MetadataKey;
use App\Services\MetadataAssignmentService;
use Filament\Tables\Columns\TextColumn;

class MetadataTableColumns
{
    /**
     * @return array<int, TextColumn>
     */
    public static function make(): array
    {
        return app(MetadataAssignmentService::class)->activeKeys()
            ->map(fn (MetadataKey $key): TextColumn => TextColumn::make("metadata_{$key->id}")
                ->label($key->label)
                ->toggleable(isToggledHiddenByDefault: true)
                ->sortable())
            ->all();
    }
}
```

```php
// app/Filament/Resources/Users/Tables/UsersTable.php (illustrative diff — same pattern for the other 3 tables)

use App\Filament\Schemas\MetadataTableColumns;
use App\Filament\Schemas\MetadataTableFilter;
use App\Services\MetadataAssignmentService;

return $table
    ->modifyQueryUsing(fn (Builder $query) => app(MetadataAssignmentService::class)->withCurrentValueSelects($query))
    ->columns([
        // ...existing columns...
        ...MetadataTableColumns::make(),
    ])
    ->filters([
        // ...existing filters...
        MetadataTableFilter::make(),
    ])
    // ...rest unchanged...
```

## State of the Art

| Old Approach (Phase 16, single-record) | New Approach (Phase 17, table-scale) | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `MetadataAssignmentService::currentValues(User $subject)` — fetch all rows for one user, `Collection::unique('metadata_key_id')` in PHP | `MetadataAssignmentService::withCurrentValueSelects(Builder $query)` — correlated `addSelect` subquery, resolved per-row at the SQL level | This phase (17) | Enables filtering/sorting/pagination/export against "current value," which the PHP-side approach structurally cannot support |

**Deprecated/outdated:** Nothing in this phase deprecates Phase 16's `currentValues()`/`currentValueFor()` — they remain correct and necessary for the single-record "Metadata" section panel (`resources/views/filament/components/metadata-current-values.blade.php`). Phase 17 adds a parallel, query-scale mechanism; it does not replace the existing one.

## Open Questions

1. **Should `addSelect` be scoped to only currently-toggled-visible columns, to avoid subquery overhead for hidden columns?**
   - What we know: Filament's column-toggle state is a per-user session/UI concern (`persistColumnSearchesInSession()` is already used on `UsersTable`), not naturally available at the point `Table::modifyQueryUsing()` runs before columns are rendered.
   - What's unclear: Whether Filament v4 exposes a reliable way to read "which toggleable columns are currently visible for this user" from inside `modifyQueryUsing()` before the table renders.
   - Recommendation: Skip this optimization for the initial implementation — build `addSelect` for all active keys unconditionally (Pitfall 2 above). Given the expected catalog size (dozens of keys, not hundreds) and typical page sizes, this is not expected to be a real performance problem. Revisit only if the metadata catalog grows large and table load times regress.

2. **Exact confirmation of MySQL 8.0's `sql_mode` and its effect on `CAST(non_numeric AS DECIMAL)`**
   - What we know: SQLite (bundled 3.45.2, used by the test suite) confirmed via direct CLI test to gracefully return `0` for non-numeric `CAST` targets, no error.
   - What's unclear: This project's exact MySQL `sql_mode` (not inspected) — MySQL 8 defaults to `STRICT_TRANS_TABLES` among others, and while general MySQL documentation confirms `CAST` truncation to `DECIMAL` produces a warning (not an error) even in strict mode, this specific database's configuration was not directly queried.
   - Recommendation: During implementation, run `SELECT @@sql_mode;` and `SELECT CAST('abc' AS DECIMAL(20,4));` directly against the dev DB connection as a 30-second sanity check before relying on this behavior in production. Low risk given `validateValue()` already prevents non-numeric data from being written for `numeric`-typed keys at the source.

## Sources

### Primary (HIGH confidence — direct vendor source inspection, this project's exact installed versions)
- `vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php` (Laravel 12, installed) — `addSelect()` (line 438), `where()` subquery-comparison branch, `isQueryable()`, `runPaginationCountQuery()`/`cloneForPaginationCount()` (pagination COUNT(*) safety)
- `vendor/filament/tables/src/Columns/Concerns/InteractsWithTableQuery.php` (filament/filament v4.2.0, installed) — `applySort()` default unqualified `orderBy()` behavior
- `vendor/filament/tables/src/Filters/Filter.php`, `BaseFilter.php`, `Concerns/HasSchema.php`, `Concerns/InteractsWithTableQuery.php` (filament/filament v4.2.0, installed) — custom `Filter::make()->schema()->query()` API
- `vendor/filament/tables/src/Table/Concerns/HasQuery.php` — `Table::modifyQueryUsing()`
- This project's own `app/Services/MetadataAssignmentService.php`, `app/Models/UserMetadataValue.php`, `app/Models/MetadataKey.php`, `app/Filament/Schemas/MetadataAssignment.php`, all four `*Table.php` files, all four in-scope `app/Exports/*.php` files, `database/migrations/2026_08_10_120*.php` — read directly, current state as of 2026-08-11
- Direct `sqlite3` CLI test (`sqlite3 :memory: "... CAST(value AS DECIMAL(20,4)) ..."`) — confirmed numeric-sort correctness and graceful non-numeric fallback to `0`
- `composer show maatwebsite/excel` / `composer show filament/filament` — confirmed exact installed versions (3.1.67 / v4.2.0)
- `php artisan tinker` / `php -r` DB version checks — confirmed MySQL 8.0.45 (dev/prod) and SQLite 3.45.2 (PHP-bundled, used by tests per `phpunit.xml`)

### Secondary (MEDIUM confidence)
- MySQL 8 `CAST(... AS DECIMAL)` non-numeric-to-zero-with-warning behavior under `STRICT_TRANS_TABLES` — general MySQL documentation knowledge, not re-verified against this project's exact `sql_mode`; flagged as Open Question 2 with a concrete verification step for implementation time.

### Tertiary (LOW confidence)
- None — every load-bearing technical claim in this document was verified against either the installed vendor source or a direct CLI/tinker test, not left as unverified training-data recollection.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new packages; versions confirmed via `composer show`
- Architecture (subquery patterns): HIGH — every method call verified against installed vendor source, not training-data assumption
- Pitfalls: HIGH for Pitfalls 1/2/4/5 (verified against source/logic); MEDIUM for Pitfall 3 (MySQL strict-mode CAST behavior, flagged for a cheap manual check)

**Research date:** 2026-08-11
**Valid until:** Stable — tied to `filament/filament` v4.2.0 and `laravel/framework` v12, both pinned in `composer.json`. Re-verify if either is upgraded before this phase is planned/executed, or if `.env`'s `DB_CONNECTION`/MySQL version changes.
