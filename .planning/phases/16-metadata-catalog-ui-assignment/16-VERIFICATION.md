---
phase: 16-metadata-catalog-ui-assignment
verified: 2026-08-10T23:40:00Z
status: gaps_found
score: 4/5 must-haves verified
gaps:
  - truth: "A superior assigns a metadata value from the catalog to one of their direct subordinates via a form scoped to the catalog and to their own subordinates only (META-03)"
    status: partial
    reason: "The Volt surfaces (coordinador y articulador) enforce scoping correctly. The Filament surfaces perform NO subordinate/role check at all — MetadataAssignment::section() and ::bulkAction() call MetadataAssignmentService::assign() directly without consulting canAssignTo(). D-02 makes this correct for superadmin/admin_campaign, but the admin panel is also open to the reviewer role (User::canAccessPanel allows 'reviewer' on the admin panel), and reviewer resolves to zero direct subordinates in the service. Confirmed empirically: a reviewer reaches GET /admin/coordinators (HTTP 200) and callTableBulkAction('assignMetadata', ...) wrote 2 user_metadata_values rows attributed to them."
    artifacts:
      - path: "app/Filament/Schemas/MetadataAssignment.php"
        issue: "section() headerAction (line 97) and bulkAction() (line 118) invoke assign()/assignMany() with no canAssignTo() or role gate; the service's own authorization model (directSubordinatesQuery returns 1=0 for reviewer) is bypassed at this layer"
    missing:
      - "Gate MetadataAssignment::section()'s assignMetadata action and MetadataAssignment::bulkAction() on the actor being able to assign — either a role check (super_admin/admin_campaign per D-02) or a canAssignTo()/subordinatesByIds() re-filter mirroring the Volt path"
      - "Regression test asserting a reviewer cannot assign metadata from the admin panel (individually and in bulk)"
  - truth: "The phase's own test protection holds when the suite runs end to end"
    status: failed
    reason: "The 58 Metadata tests pass in isolation but 5 fail deterministically under a full `php artisan test tests/Feature` run, and a 6th is randomly flaky. Root cause of the 5: CampaignContext keeps its campaign selection in private STATIC properties (app/Services/CampaignContext.php:22-24) that survive across test files in the same process. FilamentMetadataBulkActionTest only calls Session::put('campaign_context.mode','all') and never pins the static, so it inherits a stale override left by an earlier file; the Coordinators table then renders 0 records, Filament resolves an empty record set, and the bulk action silently writes nothing with no form errors (probe: rows_visible_in_table=0, form_errors=[], rows_written=0). FilamentMetadataSectionTest does not have this problem because it calls CampaignContext::setCampaignId() explicitly in beforeEach/afterEach. Separately, MetadataKeyFactory randomises `type` over ['numeric','text','date','select'] while always setting options=null, producing a select-typed key with no options; saving such a record through EditMetadataKey trips the options Repeater's minItems(1) and fails 'it keeps assignment history when a key is deactivated' roughly one run in four."
    artifacts:
      - path: "tests/Feature/Metadata/FilamentMetadataBulkActionTest.php"
        issue: "Does not pin CampaignContext; 5 tests fail (0 rows written) in a full-suite run"
      - path: "tests/Feature/Metadata/MetadataKeyResourceTest.php"
        issue: "'it keeps assignment history when a key is deactivated' is flaky via the random factory type"
      - path: "database/factories/MetadataKeyFactory.php"
        issue: "Can emit type='select' with options=null — a catalog row the form itself would reject as invalid"
    missing:
      - "Pin campaign context in FilamentMetadataBulkActionTest (CampaignContext::setCampaignId(...) in beforeEach and reset in afterEach), matching FilamentMetadataSectionTest"
      - "Make MetadataKeyFactory internally consistent — emit options when type is 'select' (or default type to a non-select value with a ->select() state for the select case)"
human_verification:
  - test: "As a coordinador, open a líder edit page and assign a metadata value of each type (numérico, texto, fecha, selección); then as an articulador do the same on a coordinador edit page"
    expected: "The Metadata block renders with Flux styling consistent with the rest of the page, the value field switches type with the selected key, and the Spanish confirmation appears inline without a page reload"
    why_human: "Visual/interaction correctness of the Flux panel embedded in a Volt page cannot be confirmed by grep or Livewire assertions"
  - test: "On the coordinador líderes list, tick several rows, use Seleccionar todos, then open Asignar metadata and submit"
    expected: "The selection bar shows the right count, the modal applies one key + one value to every selected líder, the selection clears, and a Spanish confirmation shows"
    why_human: "Checkbox/modal behaviour and the select-all interaction are real-time UI concerns; project guidance requires browser verification before deploying UI changes"
---

# Phase 16: Metadata Catalog UI & Assignment Verification Report

**Phase Goal:** A superadmin manages a predefined metadata-key catalog, and any superior assigns auditable, atomic metadata values to their direct subordinates, individually or in bulk.
**Verified:** 2026-08-10T23:40:00Z
**Status:** gaps_found
**Re-verification:** No — initial verification

> Note on scope: per 16-CONTEXT.md D-01, líder is deliberately excluded from the assignment flow (no User-type subordinates exist for líder). Success criterion 2's "líder/coordinador/articulador/superadmin" wording is read accordingly, and the absence of a líder assignment UI is **not** treated as a gap here.

## Goal Achievement

### Observable Truths

| # | Truth (ROADMAP success criteria) | Status | Evidence |
| --- | --- | --- | --- |
| 1 | Superadmin creates/edits/deactivates catalog keys from a dedicated Filament resource; no freeform key entry anywhere else (META-01, META-02) | ✓ VERIFIED | `MetadataKeyResource::canAccess()` returns `CampaignContext::isSuperAdmin()`; `MetadataKeyForm` supplies key/label/type/options-repeater/is_active with the repeater gated on `type === 'select'`; every assignment surface binds a `Select` of catalog ids, never a text key input (test `never exposes a free text key input`) |
| 2 | A superior assigns a catalog value to a direct subordinate via a form scoped to the catalog and to their own subordinates only (META-03) | ✗ FAILED | Volt surfaces correct (`MetadataAssignmentPanel::assign()` re-runs `canAssignTo()` with `abort_unless`, line 64). Filament surfaces apply **no** subordinate or role gate; a `reviewer` — permitted on the admin panel by `User::canAccessPanel()` — reached `/admin/coordinators` (HTTP 200) and wrote 2 assignment rows in a probe |
| 3 | A superior selects multiple subordinates and assigns the same value in one bulk action (META-04) | ✓ VERIFIED | `MetadataAssignment::bulkAction()` on all four admin tables; Volt row selection + modal on both list pages. Volt paths re-filter client ids through `subordinatesByIds()` before writing (leaders.blade.php:95, coordinators.blade.php:88) |
| 4 | Every assignment records who/to whom/what/when and is visible in an audit trail (META-05) | ✓ VERIFIED | `UserMetadataValue::create()` persists user_id, metadata_key_id, value, assigned_by, assigned_at; both UI surfaces render assigner name + timestamp (`metadata-current-values.blade.php:11-12`, `metadata-assignment-panel.blade.php:26-27`) |
| 5 | Concurrent assignments to different keys never clobber each other; atomic per key, race-condition test (META-06) | ✓ VERIFIED (with note) | Single write path is a pure INSERT — zero `updateOrCreate`/`firstOrNew`/`firstOrCreate`/`upsert` anywhere in the metadata path. Current value resolves via `orderByDesc('assigned_at')->orderByDesc('id')`. Note: the guard test is a sequential-interleaving regression test, not a true concurrent-process test — the test comment states this explicitly |

**Score:** 4/5 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `app/Services/MetadataAssignmentService.php` | Shared assignment logic (min 100 lines) | ✓ VERIFIED | 151 lines; consumed by both Filament and Volt layers |
| `app/Filament/Schemas/MetadataAssignment.php` | Reusable Section + BulkAction builders (min 90) | ⚠️ WIRED, UNGATED | 133 lines, imported by all 8 admin files; missing actor authorization (gap 1) |
| `resources/views/filament/components/metadata-current-values.blade.php` | Read-only current values (min 12) | ✓ VERIFIED | 19 lines; uses `x-filament::badge`, no Flux markup |
| `app/Filament/Resources/MetadataKeys/MetadataKeyResource.php` | Superadmin-gated catalog resource | ✓ VERIFIED | `canAccess()` → `CampaignContext::isSuperAdmin()`; Configuración nav group |
| `app/Filament/Resources/MetadataKeys/Schemas/MetadataKeyForm.php` | key/label/type/options/is_active, conditional repeater | ✓ VERIFIED | `Repeater::make('options')->simple(...)->visible(type === 'select')` |
| `app/Filament/Resources/MetadataKeys/Tables/MetadataKeysTable.php` | Listing with usage count | ✓ VERIFIED | 68 lines; `values_count` asserted by test |
| 4× `*Form.php` (Coordinator/Leader/AreaCoordinator/User) | Metadata section on edit forms | ✓ VERIFIED | `MetadataAssignment::section()` present in all four; `visibleOn('edit')` |
| 4× `*Table.php` (Coordinators/Leaders/AreaCoordinators/Users) | Bulk action on admin tables | ✓ VERIFIED | `MetadataAssignment::bulkAction()` inside `->toolbarActions([...])` in all four |
| `app/Livewire/MetadataAssignmentPanel.php` | Flux assignment panel (min 70) | ✓ VERIFIED | 98 lines; ownership re-checked on write |
| `resources/views/livewire/metadata-assignment-panel.blade.php` | Flux markup (min 50) | ✓ VERIFIED | 66 lines; type-conditional value field |
| `resources/views/livewire/coordinator/leaders.blade.php` | Selection + bulk modal | ✓ VERIFIED | checkboxes, Seleccionar todos, selection bar, modal, `assignBulkMetadata` |
| `resources/views/livewire/articulador/coordinators.blade.php` | Selection + bulk modal | ✓ VERIFIED | same structure with `selectedCoordinatorIds` |
| 6× `tests/Feature/Metadata/*.php` | Phase coverage | ⚠️ UNSTABLE | 58 tests, all pass in isolation; 5 fail + 1 flaky under a full-suite run (gap 2) |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| `MetadataAssignmentService` | `user_metadata_values` | `UserMetadataValue::create()` only | ✓ WIRED | Sole write in the codebase (line 101); no update-style call anywhere |
| `MetadataAssignmentService` | deterministic current value | `orderByDesc('assigned_at')->orderByDesc('id')` | ✓ WIRED | Present in both `currentValues()` and `currentValueFor()` |
| `MetadataAssignment` (Filament) | `MetadataAssignmentService` | `app(MetadataAssignmentService::class)` | ✓ WIRED | Used for options, type resolution, and both write actions |
| 4× Filament Forms | `MetadataAssignment::section()` | components array | ✓ WIRED | Confirmed in all four form classes |
| 4× Filament Tables | `MetadataAssignment::bulkAction()` | `toolbarActions()` | ✓ WIRED | Confirmed in all four table classes |
| `MetadataAssignmentPanel` | ownership enforcement | `canAssignTo()` re-check in `assign()` | ✓ WIRED | `abort_unless(...)` before validation |
| `edit-leader.blade.php` / `edit-coordinator.blade.php` | `MetadataAssignmentPanel` | `<livewire:metadata-assignment-panel>` | ✓ WIRED | Both with `wire:key` |
| Volt lists | `subordinatesByIds()` | server-side re-filter of client ids | ✓ WIRED | Both pages re-filter before any write; tampering tests pass |
| Filament actions | actor authorization | (none) | ✗ NOT_WIRED | No `canAssignTo`/role gate — see gap 1 |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
| --- | --- | --- | --- | --- |
| `metadata-assignment-panel.blade.php` | `$this->currentValues` | `MetadataAssignmentService::currentValues()` (DB query, eager-loads metadataKey + assignedByUser) | Yes | ✓ FLOWING |
| `metadata-current-values.blade.php` | `currentValues` viewData | same service call via `View::make()->viewData()` | Yes | ✓ FLOWING |
| `MetadataAssignment::modalSchema()` key select | `activeKeyOptions()` | `MetadataKey::where('is_active', true)` | Yes | ✓ FLOWING |
| Volt bulk modal key select | `$this->metadataKeys` | `activeKeys()` | Yes | ✓ FLOWING |
| Filament table bulk `$records` | Filament table query | resource `getEloquentQuery()` | Yes in normal use | ⚠️ Resolves empty under leaked campaign context (see gap 2); action still reports success |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
| --- | --- | --- | --- |
| Phase test suite in isolation | `php artisan test tests/Feature/Metadata` | 58 passed (267 assertions) | ✓ PASS |
| Phase test suite in full run | `php artisan test tests/Feature` | 5 Metadata tests fail; 1 more flaky | ✗ FAIL |
| Append-only invariant | `grep -rn "updateOrCreate\|firstOrNew\|firstOrCreate\|upsert" app/ resources/views/livewire/` on metadata path | zero matches | ✓ PASS |
| Filament/Flux boundary | `grep -rn "flux:" app/Filament/ resources/views/filament/` | zero matches | ✓ PASS |
| Flux/Filament boundary | `grep -rn "use Filament\\" app/Livewire/ resources/views/livewire/` | only pre-existing `SaldosBadge.php` (not a phase-16 file) | ✓ PASS |
| Reviewer cannot assign via admin panel | Probe: reviewer + `callTableBulkAction('assignMetadata', ...)` | 2 rows written | ✗ FAIL |
| Reviewer blocked from coordinadores list | Probe: `GET /admin/coordinators` as reviewer | HTTP 200 | ✗ FAIL (pre-existing panel posture) |
| Pint on phase-16 files | `vendor/bin/pint --test <41 phase files>` | 2 issues, both in `ListCoordinators.php`/`ListLeaders.php` — files not modified by this phase | ✓ PASS |

### Requirements Coverage

| Requirement | Source Plan | Description | REQUIREMENTS.md now | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| META-01 | 16-02 | Superadmin CRUD del catálogo (nombre + tipo) | `[x]` / Done | ✓ SATISFIED | `MetadataKeyResource` + form + 8 passing tests |
| META-02 | 16-01, 16-02, 16-05 | Llaves no creables fuera del catálogo | `[x]` / Done | ✓ SATISFIED | Every assignment surface uses a catalog `Select`; `findActiveKey()` is the only lookup; test asserts no free-text key input |
| META-03 | 16-01, 16-03, 16-05, 16-06 | Asignar valor a un subordinado directo | `[ ]` / Pending | ⚠️ PARTIAL | Volt paths fully scoped; Filament path ungated (gap 1) — **not safe to close yet** |
| META-04 | 16-04, 16-06 | Asignación masiva | `[ ]` / Pending | ⚠️ PARTIAL | Mechanism works on all six surfaces; inherits gap 1 on the Filament tables — **not safe to close yet** |
| META-05 | 16-01, 16-03, 16-05 | Auditoría de cada asignación | `[ ]` / Pending | ✓ SATISFIED | assigned_by/assigned_at written and surfaced in both UIs — **safe to close** |
| META-06 | 16-01 | Escrituras atómicas por llave | `[ ]` / Pending | ✓ SATISFIED | Pure-INSERT write path + id tiebreak — **safe to close**, with the noted caveat that the guard is a sequential test, not a concurrent one |

No orphaned requirements: REQUIREMENTS.md maps exactly META-01..META-06 to Phase 16, and all six are claimed by plans.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| --- | --- | --- | --- | --- |
| `app/Filament/Schemas/MetadataAssignment.php` | 97, 118 | Write action with no actor authorization | 🛑 Blocker | Reviewer can write audited metadata rows; contradicts the service's own scoping model |
| `tests/Feature/Metadata/FilamentMetadataBulkActionTest.php` | beforeEach | Relies on session-only campaign mode | ⚠️ Warning | 5 tests fail in a full-suite run |
| `database/factories/MetadataKeyFactory.php` | 17-18 | Random `type` incl. `select` with `options => null` | ⚠️ Warning | Emits an invalid catalog row; makes a catalog test flaky |
| `app/Filament/Schemas/MetadataAssignment.php` | 126-129 | Success notification emitted even when 0 records resolve | ℹ️ Info | "Metadata asignada a 0 usuario(s)" reads as success on a no-op |
| `resources/views/livewire/metadata-assignment-panel.blade.php` | 6-7 | `@if (! $this->canAssign) @else` with an empty then-branch | ℹ️ Info | Harmless; inverting the condition would read better |

Pre-existing and out of scope for this phase: `tests/Feature/Filament` fails 18 tests on its own (reports/jurisdiction/voter suites), unrelated to metadata.

### Human Verification Required

See the `human_verification` block in the frontmatter — two browser checks covering the Flux panel on both Volt edit pages and the row-selection/bulk-modal interaction on both Volt list pages. Project guidance requires clicking through UI changes in a real browser before deploying, and neither the Livewire nor the Filament assertions cover visual/interaction correctness.

### Gaps Summary

The feature is substantially built and structurally sound. The service layer is the single write path, it is genuinely append-only (a pure `UserMetadataValue::create()`, with zero update-style calls anywhere in the codebase), current-value resolution tie-breaks on `id` as designed, and the audit fields are both persisted and surfaced in two UIs. The Filament/Flux rendering boundary is respected in both directions. The Volt bulk paths do exactly what was asked during planning: they re-derive targets from `subordinatesByIds()` server-side and never trust client-posted ids, and there are passing tampering tests on both lists.

Two things block a clean pass.

First, the Filament assignment surfaces perform no actor authorization at all. That is intentional and correct for superadmin/admin_campaign under D-02, but the admin panel is also open to `reviewer`, and the service's own model gives reviewer zero direct subordinates. A probe confirmed a reviewer can load the coordinadores list and bulk-write assignment rows attributed to themselves. The fix is small — gate `section()`'s action and `bulkAction()` on a role check or a `canAssignTo()`/`subordinatesByIds()` re-filter, mirroring what the Volt path already does — but until then success criterion 2's "their own subordinates only" is not enforced on half the surfaces, so META-03 and META-04 should stay open.

Second, the phase's test protection does not survive a full-suite run. Five bulk-action tests fail deterministically because `CampaignContext` stores its campaign selection in private statics that leak across test files; `FilamentMetadataBulkActionTest` never pins that state, so the admin table renders zero records and the bulk action silently writes nothing. `FilamentMetadataSectionTest` avoids this precisely because it calls `CampaignContext::setCampaignId()` — that is the template for the fix. A sixth test is independently flaky because `MetadataKeyFactory` can produce a `select`-typed key with no options, a shape the form itself rejects.
