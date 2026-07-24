# Deferred Items

## From 02.1-07 (Rename Votante->Apoyo in Leader/Coordinator/Campaign-admin/Calls/Landing views)

- **Pre-existing Pint style issues** (`single_import_per_statement`, `unary_operator_spaces`, `class_attributes_separation`, etc.) found via `vendor/bin/pint --test` across nearly all touched Volt files (e.g. `use function Livewire\Volt\{layout, with};` grouped-import style). Verified via `git stash` that these issues predate this plan's edits — they are an existing codebase convention, not introduced by this plan. Out of scope per SCOPE BOUNDARY rule; not fixed. `vendor/bin/pint --dirty` (the plan's actual verification command) reports 0 issues for all files this plan touched, since these files were not left dirty with new style violations.
