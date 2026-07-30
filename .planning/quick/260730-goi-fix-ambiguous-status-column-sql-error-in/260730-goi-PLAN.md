---
phase: quick
plan: 260730-goi
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Filament/Widgets/CallQueueTable.php
  - app/Models/CallAssignment.php
  - tests/Feature/Filament/CallQueueTableTest.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "Sorting CallQueueTable by a joined relation column (e.g. voter.municipality.name) no longer throws an ambiguous-column SQL error"
  artifacts:
    - path: "app/Filament/Widgets/CallQueueTable.php"
      provides: "status column qualified as call_assignments.status in the query() whereIn"
    - path: "app/Models/CallAssignment.php"
      provides: "pending()/inProgress()/completed() scopes qualify status as call_assignments.status (same latent bug class, defensive fix)"
  key_links: []
---

<objective>
Fix a live SQL error: `SQLSTATE[23000]: Integrity constraint violation: 1052 Column 'status' in where clause is ambiguous`, reproduced by sorting CallQueueTable's "Municipio" column (a `voter.municipality.name` relation sort), which makes Filament LEFT JOIN `voters` and `municipalities` into the query. `call_assignments.status` and `voters.status` both exist, so the unqualified `->whereIn('status', [...])` in `CallQueueTable::table()` becomes ambiguous the instant that join is present. Same root class of bug as `CallAssignment`'s `pending()`/`inProgress()`/`completed()` local scopes, which also reference unqualified `status` and would break identically if ever combined with a join.
</objective>

<context>
@app/Filament/Widgets/CallQueueTable.php
@app/Models/CallAssignment.php
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Qualify all unqualified `status` column references on CallAssignment queries</name>
  <files>
    app/Filament/Widgets/CallQueueTable.php,
    app/Models/CallAssignment.php,
    tests/Feature/Filament/CallQueueTableTest.php
  </files>
  <read_first>
    app/Filament/Widgets/CallQueueTable.php (line ~41, the whereIn('status', ...) in query()),
    app/Models/CallAssignment.php (scopePending/scopeInProgress/scopeCompleted, ~lines 65-78 — confirm the actual method names via grep "function scope" before editing)
  </read_first>
  <behavior>
    - Test: sorting CallQueueTable's table by a column that triggers a join to `voters`/`municipalities` (e.g. call `->sortColumn('voter.municipality.name')`-equivalent via the Filament table test helper, or directly assert the underlying query()'s SQL/results when ordered by that nested column) does not throw and returns the expected assignment row(s).
    - Test (regression guard): the existing unsorted/default query still filters to pending/in_progress assignments only (a completed assignment must not appear).
  </behavior>
  <action>
In `app/Filament/Widgets/CallQueueTable.php`, change line ~41:
```php
->whereIn('status', ['pending', 'in_progress'])
```
to:
```php
->whereIn('call_assignments.status', ['pending', 'in_progress'])
```

In `app/Models/CallAssignment.php`, qualify the same unqualified `status` references inside the local scopes (confirm exact method names first via read_first — expected `scopePending`, `scopeInProgress`, `scopeCompleted` or similar):
```php
$query->where('status', 'pending');      // -> $query->where('call_assignments.status', 'pending');
$query->where('status', 'in_progress');  // -> $query->where('call_assignments.status', 'in_progress');
$query->where('status', 'completed');    // -> $query->where('call_assignments.status', 'completed');
```
These are the same latent-ambiguity bug class (unqualified `status` on a model whose queries are frequently joined to `voters`, which also has a `status` column) — fixing them now prevents the identical SQL error the next time any of these scopes is used in a joined query context. Do not change any other scope/column in this file (`campaign_id`, `assigned_to`, `priority` are all unambiguous — no other table in this app has those exact column names in a way that collides here).

Run `vendor/bin/pint --dirty`.
  </action>
  <verify>
    <automated>php artisan test tests/Feature/Filament/CallQueueTableTest.php --stop-on-failure</automated>
  </verify>
  <done>New/updated test proves sorting by a joined relation column no longer throws the ambiguous-column SQL error; all 3 CallAssignment status scopes and CallQueueTable's own whereIn are fully qualified.</done>
</task>

</tasks>

<verification>
- `php artisan test tests/Feature/Filament/CallQueueTableTest.php` passes
- `grep -n "'call_assignments.status'" app/Filament/Widgets/CallQueueTable.php app/Models/CallAssignment.php` shows 4 matches total
- `vendor/bin/pint --dirty` clean
</verification>

<success_criteria>
- Sorting the Call Center queue table by any joined column (Municipio, Barrio) no longer 500s
</success_criteria>

<output>
After completion, create `.planning/quick/260730-goi-fix-ambiguous-status-column-sql-error-in/260730-goi-SUMMARY.md`
</output>
