---
phase: quick
plan: 260731-odu
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php
  - tests/Feature/Filament/AuditLogResourceTest.php
  - app/Providers/Filament/AdminPanelProvider.php
  - tests/Feature/Filament/AdminPanelProviderTest.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "Viewing an audit log record renders old_values/new_values as a readable key-value table instead of a raw JSON string"
    - "Viewing an audit log record with null old_values/new_values still renders without error, showing a friendly empty state"
    - "The admin panel's sidebar navigation groups include a 'Sistema' group, positioned last, after 'Configuración'"
  artifacts:
    - path: "app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php"
      provides: "KeyValueEntry rendering for old_values/new_values"
      contains: "KeyValueEntry::make"
    - path: "app/Providers/Filament/AdminPanelProvider.php"
      provides: "Sistema navigation group registration"
      contains: "->label('Sistema')"
  key_links:
    - from: "app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php"
      to: "App\\Models\\AuditLog old_values/new_values array casts"
      via: "KeyValueEntry's default state resolution reading the model's array-cast attribute"
      pattern: "KeyValueEntry::make\\('(old|new)_values'\\)"
    - from: "app/Providers/Filament/AdminPanelProvider.php"
      to: "Filament::getPanel('admin')->getNavigationGroups()"
      via: "->navigationGroups([...]) array, last entry"
      pattern: "label\\('Sistema'\\)"
---

<objective>
Fix two small, unrelated defects surfaced during review of the AuditLogResource and the admin panel's navigation configuration:

1. `ViewAuditLog`'s `old_values`/`new_values` infolist entries currently render as a raw pretty-printed JSON string (via `->state()` + `json_encode()` + `FontFamily::Mono`, from quick task 260731-o5i). This is functionally correct but hard to read. Replace with Filament's native `KeyValueEntry`, which renders array-cast attributes as a proper key/value table.
2. `AdminPanelProvider`'s `->navigationGroups([...])` array is missing a `'Sistema'` group — needed so upcoming/undiscovered system-level Filament resources (like `AuditLogResource`, which currently has no explicit `->navigationGroup()` call and therefore falls into Filament's default "Other" bucket) have a proper home in the sidebar. Add it as the last group, after `'Configuración'`, without touching the existing five.

Purpose: Improve audit log readability for super admins reviewing change history, and give system-level resources a dedicated, discoverable nav group.
Output: Updated `ViewAuditLog.php` infolist + updated regression test; updated `AdminPanelProvider.php` navigation groups + new regression test.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php
@app/Models/AuditLog.php
@app/Providers/Filament/AdminPanelProvider.php
@tests/Feature/Filament/AuditLogResourceTest.php
</context>

<interfaces>
<!-- KeyValueEntry, extracted from vendor/filament/infolists/src/Components/KeyValueEntry.php -->
<!-- Extends Entry, implements HasEmbeddedView. Ships with filament/filament — no new dependency. -->

```php
namespace Filament\Infolists\Components;

class KeyValueEntry extends Entry implements HasEmbeddedView
{
    // setUp() already calls $this->placeholder(__('filament-infolists::components.entries.key_value.placeholder'));
    // i.e. it has its OWN built-in empty-state placeholder row — no ->visible() gating needed.

    public function keyLabel(string | Closure | null $label): static;
    public function valueLabel(string | Closure | null $label): static;
    public function getKeyLabel(): string;
    public function getValueLabel(): string;

    // toEmbeddedHtml() reads $this->getState() — for an entry named 'old_values'/'new_values',
    // this resolves to $record->old_values / $record->new_values directly (the model's array cast),
    // NOT via formatStateUsing() per-element. This is why it sidesteps the 260731-o5i bug:
    // KeyValueEntry never calls formatStateUsing() at all, so there's no per-array-element iteration.
    // When state is null/empty (empty(...) check), it renders a single placeholder row instead of crashing.
}
```

From `app/Models/AuditLog.php` (casts() method):
```php
'old_values' => 'array',
'new_values' => 'array',
```

This confirms KeyValueEntry::make('old_values') / ::make('new_values') can be used directly with NO custom ->state() closure — the base Entry class's default state resolution reads the array-cast model attribute automatically, same mechanism TextEntry used before o5i's workaround was needed.

From `Filament\Panel\Concerns\HasNavigation` (vendor/filament/filament/src/Panel/Concerns/HasNavigation.php):
```php
public function getNavigationGroups(): array; // returns the raw array passed to ->navigationGroups([...]), in order
```

From `Filament\Navigation\NavigationGroup`:
```php
public function label(string | Closure | null $label): static;
public function getLabel(): ?string;
```
</interfaces>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Replace old_values/new_values TextEntry with KeyValueEntry in ViewAuditLog</name>
  <files>app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php, tests/Feature/Filament/AuditLogResourceTest.php</files>
  <behavior>
    - Existing test "super admin can view an audit log with mixed int/string new_values without a 500 error" must keep passing (200 status, still no crash on mixed int/string array values) — this is the 260731-o5i regression guard and must NOT be weakened to a no-op.
    - Extend that same test (or add a new one) to assert the KeyValueEntry rendering surfaces individual key/value pairs from new_values (e.g. assertSee('email') as a key and assertSee('jane@example.com') as a value), not a raw JSON blob — proving the switch away from json_encode() actually took effect.
    - New test: viewing an audit log record whose old_values AND new_values are both null (e.g. a login/logout audit entry) returns 200 with no error — proves KeyValueEntry's built-in empty-state placeholder handles null gracefully, no crash, no need for manual ->visible() gating.
  </behavior>
  <action>
    In `app/Filament/Resources/AuditLogs/Pages/ViewAuditLog.php`:
    - Replace the two `Components\TextEntry::make('old_values')` / `Components\TextEntry::make('new_values')` blocks (currently using `->state(fn (AuditLog $record): string => ... json_encode(...) ...)->fontFamily(FontFamily::Mono)`) with `Components\KeyValueEntry::make('old_values')` / `Components\KeyValueEntry::make('new_values')`.
    - Do NOT add a custom `->state()` closure — KeyValueEntry's default state resolution reads the model's array-cast attribute directly (see `<interfaces>`), which is what avoids the original o5i bug (formatStateUsing() being invoked per array element never happens here because KeyValueEntry doesn't call formatStateUsing() at all).
    - Keep `->label('Valores Anteriores')` / `->label('Valores Nuevos')` and `->columnSpanFull()` on both.
    - Set `->placeholder('—')` on both (matching this file's existing convention for other nullable entries — created_at, auditable_type, etc.) to override KeyValueEntry's default translated placeholder string. No `->visible()` call needed — KeyValueEntry renders its own empty-state row when state is null/empty, which is simpler and matches the "handle null/empty case" requirement without extra conditional logic.
    - Remove the now-unused `use Filament\Support\Enums\FontFamily;` import — it is no longer referenced anywhere in this file after the switch (verify with grep before removing).
    - `use App\Models\AuditLog;` import may also become unused if no other closure in the file references it — check before removing; keep it if `auditable_type`'s formatStateUsing or any other closure still needs it (it doesn't currently, so this import likely also becomes removable — verify via grep, don't guess).

    In `tests/Feature/Filament/AuditLogResourceTest.php`:
    - Update the existing test `'super admin can view an audit log with mixed int/string new_values without a 500 error'`: keep the 200-status assertion, and strengthen the assertions per `<behavior>` above — assert both a key label and a value from the new_values array appear in the rendered response (e.g. `assertSee('email')` and `assertSee('jane@example.com')`), in addition to the existing `assertSee('Jane Doe')`.
    - Add a new test asserting a record with `old_values: null, new_values: null` (e.g. mimicking a login/logout-style audit entry with no field diff) still returns 200 via `AuditLogResource::getUrl('view', ...)`.
  </behavior>
  <verify>
    <automated>cd "/Volumes/NAS(MAC)/Data/Herd/sigma-project" && php artisan test --filter=AuditLogResourceTest</automated>
  </verify>
  <done>All AuditLogResourceTest tests pass, including the strengthened mixed-value regression test and the new null-values test; ViewAuditLog.php has no unused imports; old_values/new_values render as KeyValueEntry tables in the admin UI.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Register 'Sistema' navigation group in AdminPanelProvider</name>
  <files>app/Providers/Filament/AdminPanelProvider.php, tests/Feature/Filament/AdminPanelProviderTest.php</files>
  <behavior>
    - New test: the admin panel's registered navigation groups, read via `Filament::getPanel('admin')->getNavigationGroups()`, map to labels `['Gestión', 'Call Center', 'Mensajería', 'Jornada Electoral', 'Configuración', 'Sistema']` in that exact order — proves 'Sistema' was appended last without disturbing the existing five.
  </behavior>
  <action>
    In `app/Providers/Filament/AdminPanelProvider.php`'s `->navigationGroups([...])` array (currently ending with the `'Configuración'` `NavigationGroup::make()` entry), add one more entry as the LAST element:
    ```php
    NavigationGroup::make()
        ->label('Sistema')
        ->collapsed(false),
    ```
    Do not reorder, rename, or otherwise modify the existing five `NavigationGroup::make()` entries (Gestión, Call Center, Mensajería, Jornada Electoral, Configuración) — only append.

    In `tests/Feature/Filament/AdminPanelProviderTest.php` (new file):
    - Write a Pest test that resolves the admin panel (`\Filament\Facades\Filament::getPanel('admin')`), calls `->getNavigationGroups()`, maps each `NavigationGroup` instance to `->getLabel()`, and asserts the resulting array equals `['Gestión', 'Call Center', 'Mensajería', 'Jornada Electoral', 'Configuración', 'Sistema']` exactly (order matters — use `toBe()`, not an unordered/contains assertion).
  </action>
  <verify>
    <automated>cd "/Volumes/NAS(MAC)/Data/Herd/sigma-project" && php artisan test --filter=AdminPanelProviderTest</automated>
  </verify>
  <done>New AdminPanelProviderTest passes, confirming 'Sistema' is registered as the sixth and last navigation group with the first five untouched.</done>
</task>

</tasks>

<verification>
Run the full targeted regression sweep for both touched areas:
```bash
cd "/Volumes/NAS(MAC)/Data/Herd/sigma-project" && php artisan test --filter=AuditLogResourceTest && php artisan test --filter=AdminPanelProviderTest
```
Then run `vendor/bin/pint --dirty` to ensure PSR-12 formatting on the two modified/created PHP files, per CLAUDE.md.

Manual sanity check (browser-verify per user preference before considering this shippable, though not required to close this quick task): visit `/admin/audit-logs/{id}` as a super_admin and confirm old_values/new_values render as tables, and check the sidebar shows a "Sistema" group after "Configuración".
</verification>

<success_criteria>
- `ViewAuditLog.php` uses `KeyValueEntry::make('old_values')` / `KeyValueEntry::make('new_values')` with no custom `->state()` closure, no unused imports.
- `AdminPanelProvider.php`'s `->navigationGroups([...])` array has exactly 6 entries in order: Gestión, Call Center, Mensajería, Jornada Electoral, Configuración, Sistema.
- All AuditLogResourceTest and AdminPanelProviderTest tests pass.
- No new Composer dependency added.
- `vendor/bin/pint --dirty` reports no violations on touched files.
</success_criteria>

<output>
After completion, create `.planning/quick/260731-odu-fix-auditlogresource-keyvalueentry-para-/260731-odu-SUMMARY.md`
</output>
