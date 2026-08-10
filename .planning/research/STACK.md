# Stack Research

**Domain:** JSON metadata column with a controlled key catalog + Filament v4 filter/sort by JSON key (MySQL)
**Researched:** 2026-08-10
**Confidence:** HIGH

## Recommended Stack

### Core Technologies

No new core technologies needed. Everything required already exists in the current stack:

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| MySQL | 8.0.45 (confirmed via `SELECT VERSION()` against the project DB) | Native JSON column type + JSON path querying | Laravel's JSON where/order clauses require MariaDB 10.3+, MySQL 8.0+, PostgreSQL 12+, or SQLite 3.39+. Project is on MySQL 8.0.45 — comfortably above the minimum. No document DB or extension needed. |
| Laravel 12 query builder / Eloquent | 12.x (existing) | `->` JSON path operator, `whereJsonContains`, `orderByRaw` | Native, first-class JSON support ships in the framework already in use. No package required for basic key/value filter or sort. |
| Filament v4 Tables | 4.x (existing) | `SelectFilter`/`Filter` with custom `query()`, `TextColumn::sortable(query: ...)` | Both filter and sort already support fully custom `Builder` closures — the exact extension point needed to reach into a JSON column. This is the same mechanism the codebase already uses for relationship-based sorts (e.g. `leaders_count` in `CoordinatorsTable`). |

### Supporting Libraries

None required. No new Composer package is warranted for this milestone's scope (single flat JSON key/value map + a validated catalog table).

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| *(none)* | — | — | — |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| `php artisan tinker` / `database-query` (Boost) | Verify JSON query syntax against real data before wiring into Filament | Use to sanity-check `->where('metadata->biaticos', ...)` and `orderByRaw(...)` against the actual `users` table before adding to `UsersTable`/`CoordinatorsTable`/`LeadersTable`. |

## Native Pattern (recommended approach)

### 1. Schema: JSON column on `users`

```php
// migration
Schema::table('users', function (Blueprint $table) {
    $table->json('metadata')->nullable()->after('coordinator_user_id');
});
```

Cast on the model:

```php
// app/Models/User.php
protected function casts(): array
{
    return [
        // ...existing casts
        'metadata' => 'array',
    ];
}
```

`array` cast (not a custom `AsArrayObject`/`AsCollection` cast) is sufficient — the metadata is a flat `key => value` map, not nested structured data, and Laravel round-trips a plain array cast to/from a MySQL `json` column with no extra ceremony.

### 2. Controlled key catalog (separate table, not an enum)

A superadmin-managed catalog belongs in its own table (e.g. `metadata_keys`: `id`, `key` (unique, slug-like), `label`, `type` — text/number/select, `options` (nullable JSON for select-type values), `is_active`) rather than a PHP enum, because:
- The catalog must be editable at runtime by a superadmin (per the milestone's own requirement), so it cannot be a compiled/deployed artifact like an enum.
- Filament gives you a CRUD resource "for free" against a real table (superadmin manages keys the same way every other reference-data table in this app is managed — mirrors `Gremios`/`Subcategorias` which already follow this exact pattern in the codebase).
- Assignment UI (on User forms) can populate its `Select`/repeatable options by querying `MetadataKey::where('is_active', true)->pluck(...)`, giving validation against the catalog "for free" instead of hand-rolling a validation rule against a hardcoded list.

This is a hand-built table, not a package — Laravel doesn't need a dedicated catalog package for what is a 4-5 column reference table.

### 3. Writing values

```php
$user->metadata = array_merge($user->metadata ?? [], ['biaticos' => '50000']);
$user->save();

// or targeted partial update without loading the whole array (MySQL 5.7+/MariaDB 10.3+):
User::where('id', $id)->update(['metadata->biaticos' => '50000']);
```

Only ever write keys that exist in `metadata_keys` (validate in the Form Request / Livewire form against the catalog) — the JSON column itself has no schema enforcement, so the catalog is the only thing keeping it "controlled."

### 4. Filtering by JSON key/value in Filament v4

```php
use Filament\Tables\Filters\SelectFilter;

SelectFilter::make('metadata_biaticos')
    ->label('Biáticos')
    ->options(fn () => /* distinct or catalog-defined options for this key */)
    ->query(function (Builder $query, array $data) {
        if (blank($data['value'] ?? null)) {
            return $query;
        }

        return $query->where('metadata->biaticos', $data['value']);
    });
```

For a fully dynamic filter (any catalog key, not one filter per key), build filters at runtime from `MetadataKey::active()->get()` inside `->filters()` (a `map()` over the catalog producing one `SelectFilter`/`Filter` per key), using `->where("metadata->{$key}", $value)` per filter — the `->` operator is safe here only because `$key` comes from the trusted catalog table, never raw user input (PDO cannot bind column/path names, so this must be validated against the catalog before interpolation, per Laravel's own query builder docs warning).

### 5. Sorting by JSON key/value in Filament v4

```php
use Illuminate\Database\Eloquent\Builder;

TextColumn::make('metadata_biaticos')
    ->label('Biáticos')
    ->state(fn ($record) => $record->metadata['biaticos'] ?? null)
    ->sortable(query: function (Builder $query, string $direction): Builder {
        return $query->orderByRaw(
            'CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.biaticos")) AS CHAR) ' . $direction
        );
    });
```

Notes:
- `TextColumn::sortable(query: Closure)` is the exact same extension point already used elsewhere in this codebase for computed/relationship columns — this is a drop-in continuation of an existing pattern, not a new one.
- Use `JSON_EXTRACT` + `JSON_UNQUOTE` (or the `->>` shorthand, MySQL 5.7.13+) rather than the plain `->` accessor inside `orderByRaw`, since `orderByRaw` is a raw SQL string, not query-builder JSON path syntax — the `->`/`->>` sugar Laravel exposes on `where()`/`orderBy()` (`$query->orderBy('metadata->biaticos', $direction)`) also works directly and is simpler; prefer it unless a numeric cast is needed for correct numeric sort order (JSON values are stored as strings without a cast, so `CAST(... AS UNSIGNED)` /`AS DECIMAL` is needed if a key's `type` in the catalog is numeric).
- If a metadata key's catalog `type` is numeric, cast accordingly (`AS UNSIGNED` or `AS DECIMAL(10,2)`) so `50000` doesn't sort before `9000` as a string.

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|--------------------------|
| Native `->` operator / `whereJsonContains` / `orderByRaw` + `JSON_EXTRACT` | `spatie/laravel-schemaless-attributes` | If the metadata grew into deeply nested, typed, multi-column schemaless data with its own query DSL needs. Actively maintained (Packagist last update 2026-04-21), MySQL 5.7+ compatible, and would fit the existing Spatie-heavy stack (already using `spatie/laravel-permission`). Not warranted here: this milestone's metadata is a single flat key/value map validated against one catalog table — the package's main value (a fluent object + multiple schemaless columns per model) isn't needed, and it doesn't solve the catalog-validation or Filament filter/sort requirements either, so it would be an extra dependency with no reduction in hand-written code. |
| MySQL native `json` column, queried directly | MySQL **generated/virtual columns** (`$table->string(...)->virtualAs('metadata->>"$.biaticos"')->index()`) per catalog key | Only worth it once the `users` table is large enough (tens of thousands+ rows) that `JSON_EXTRACT`-based filtering/sorting shows measurable latency, AND the catalog is stable enough to justify a migration per key. Since the catalog is explicitly runtime-editable by a superadmin (not deploy-time), a virtual column per key would require a migration on every catalog change — defeats the "predefined but manageable" design goal. Revisit only if a specific key becomes a genuine hot path (e.g. used in every dashboard widget) and campaign user counts grow well past current scale. |
| Hand-built `metadata_keys` catalog table + Filament resource | `ptplugins/filament-auto-filters` (auto-generates filters from column definitions, supports Filament 3/4/5, handles JSON columns) | If the number of filterable metadata keys grows large enough that hand-writing one `SelectFilter`/`sortable` pair per key (or per catalog row via a `map()`) becomes real maintenance burden. Not needed for this milestone's scope (a handful of catalog keys like `biaticos`, `almuerzo`, `incentivo`); adding a third-party table/filter-generation package for a few keys is disproportionate, and CLAUDE.md project conventions call for approval before dependency changes. |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|--------------|
| A document database (MongoDB, etc.) or a separate EAV (entity-attribute-value) table just to store metadata key/values | MySQL 8.0.45 (already in use) has full native JSON column + JSON path query/sort support — adding another datastore or a normalized EAV schema is unjustified complexity for a flat key/value map on one model | MySQL `json` column on `users` + native `->`/`whereJsonContains`/`JSON_EXTRACT` queries |
| Freeform metadata keys with no server-side validation against a catalog | The milestone explicitly requires a *predefined, superadmin-managed* catalog, not freeform — validating only in the UI (e.g. a Livewire `Select` populated once) without a hard server-side check against `metadata_keys` allows any actor with direct model/API access to write unvalidated keys | Validate every write (Form Request / Livewire form `rules()`) against `MetadataKey::where('is_active', true)->pluck('key')` before merging into the `metadata` array |
| Plain `orderBy('metadata->biaticos', $direction)` when the catalog key's type is numeric | JSON scalar values are stored/compared as strings by default; without a numeric cast, `"9000"` sorts after `"50000"` lexicographically (wrong for currency/quantity values like biaticos) | `orderByRaw('CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.key")) AS UNSIGNED) ' . $direction)` for numeric-typed catalog keys |
| Interpolating a raw user-supplied string directly into a JSON path inside `orderByRaw`/`whereRaw` | Laravel's query builder explicitly warns that PDO cannot bind column/path names — user input must never dictate order-by/JSON-path column references, or it opens a SQL injection surface | Only interpolate `$key` after validating it exists in the `metadata_keys` catalog table (trusted source, not raw request input) |
| MySQL generated/virtual columns per catalog key at this milestone's scale | Requires a schema migration every time a superadmin adds/removes a catalog key, which conflicts with the catalog being a runtime-managed feature, and the perf gain isn't needed yet at current campaign user-table sizes | Direct `JSON_EXTRACT`/`->` queries now; revisit virtual+indexed columns only if a specific key becomes a proven hot path at much larger scale |

## Stack Patterns by Variant

**If the metadata catalog stays small (a handful of keys, as currently scoped — `biaticos`, `almuerzo`, `incentivo`):**
- Build filters/sort per-key (either hand-written or generated via a `map()` over active catalog rows inside `->filters()`/`->columns()`)
- Because the closures needed are simple and the existing codebase already has this exact `sortable(query: ...)` / custom-`query()` filter pattern established (e.g. `leaders_count` sort in `CoordinatorsTable`), consistent with project conventions

**If the catalog later grows large (dozens+ keys, or per-campaign custom keys):**
- Reassess: at that point a dynamic filter-generation approach (still native, built as a small in-house helper that maps `MetadataKey` rows to `SelectFilter`/`sortable` definitions) is more scalable than one hardcoded filter per key
- Consider virtual/indexed generated columns for the specific keys proven to be filtered/sorted most often, once `users` row counts justify it

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|-----------------|-------|
| MySQL 8.0.45 | Laravel 12.x JSON where/order clauses | Laravel's JSON where-clause docs require MySQL 8.0+ specifically (MariaDB 10.3+ is a separate compatibility line) — confirmed the project's actual DB version via `SELECT VERSION()`, well above the minimum |
| Filament v4.x `TextColumn::sortable(query: Closure)` | Laravel 12.x `Illuminate\Database\Eloquent\Builder` | Same closure signature (`Builder $query, string $direction`) already used elsewhere in this codebase's Filament resources — no version friction |
| `array` Eloquent cast on a `json` column | MySQL `json` column type | Standard, no additional package; avoid `AsArrayObject`/`AsCollection` unless dot-notation mutation ergonomics are specifically needed — not required for this flat key/value use case |

## Sources

- [Laravel 12.x Query Builder docs — JSON Where Clauses, Updating JSON Columns, Ordering](https://laravel.com/docs/12.x/queries) — HIGH confidence, official docs, verified `->` operator syntax, `whereJsonContains`/`whereJsonDoesntContain`, MySQL 8.0+ minimum version requirement, and the PDO column-binding warning for raw order-by input
- Direct project verification: `SELECT VERSION()` against the connected MySQL DB returned `8.0.45` — HIGH confidence, confirms the project's actual DB meets Laravel's JSON-query minimum version
- Direct project inspection: `app/Filament/Resources/Coordinators/Tables/CoordinatorsTable.php`, `app/Filament/Resources/Messages/Tables/MessagesTable.php` — HIGH confidence, confirms existing `sortable(query: ...)` and `SelectFilter` conventions already in use in this codebase, which the JSON metadata filter/sort should follow
- [Kirschbaum — Optimizing, sorting, and filtering JSON Columns in Laravel with Indexed Virtual Columns](https://kirschbaumdevelopment.com/insights/optimizing-json-columns-in-laravel) — MEDIUM confidence (community source, cross-checked against Laravel migration `virtualAs()`/`storedAs()` API which is documented framework behavior), used to evaluate and explicitly defer the generated-column optimization
- [Packagist — spatie/laravel-schemaless-attributes](https://packagist.org/packages/spatie/laravel-schemaless-attributes) — MEDIUM confidence (package registry, confirms active maintenance as of 2026-04-21 and MySQL 5.7+ requirement), used to evaluate and explicitly reject as unnecessary for this milestone's scope
- [Filament — Advanced/Filters "Add custom query to a filter" community thread](https://www.answeroverflow.com/m/1143034555700359178) — MEDIUM confidence (community-verified pattern, consistent with official Filament filter `query()` closure API), used to confirm `SelectFilter`/`Filter` custom `query()` closures are the correct extension point

---
*Stack research for: JSON metadata column + controlled key catalog + Filament v4 filter/sort (MySQL)*
*Researched: 2026-08-10*
