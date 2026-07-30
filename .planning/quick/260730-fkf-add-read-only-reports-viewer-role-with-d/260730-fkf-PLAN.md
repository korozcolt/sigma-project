---
phase: quick
plan: 260730-fkf
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Enums/UserRole.php
  - app/Policies/VoterPolicy.php
  - app/Providers/AuthServiceProvider.php
  - tests/Feature/RolePermissionTest.php
  - tests/Feature/Policies/VoterPolicyTest.php
  - app/Providers/Filament/ReportsPanelProvider.php
  - bootstrap/providers.php
  - app/Filament/Resources/Voters/VoterResource.php
  - database/seeders/RoleUsersSeeder.php
  - app/Filament/Widgets/ApoyosLideresCoordinadoresTable.php
  - app/Filament/Widgets/DuplicatesReportTable.php
  - app/Filament/Widgets/JurisdictionReportTable.php
  - app/Filament/Widgets/RejectionsReportTable.php
  - app/Filament/Widgets/TopCoordinatorsTable.php
  - app/Filament/Widgets/TopLeadersTable.php
  - app/Filament/Widgets/TopPollingPlacesTable.php
  - app/Filament/Resources/Voters/Tables/VotersTable.php
  - app/Filament/Resources/Voters/Pages/ListVoters.php
  - tests/Feature/Filament/ReportsPanelTest.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "A user with the new reports_viewer role can log in and land on a dedicated /reports panel showing every included report widget for their single active campaign"
    - "Clicking an existing drill-through (FollowUpBacklogOverview, FallbackSourceOverview, TopLeadersTable) navigates into the Voters list scoped to the /reports panel, not /admin"
    - "On that Voters list, a reports_viewer user can open a record to view it, but sees no Create, Edit, Delete, bulk-delete, export, or other action button anywhere in the reports panel"
    - "A reports_viewer user hitting /reports/voters/create or /reports/voters/{id}/edit directly gets a 403, even though the routes exist"
    - "A user without the reports_viewer role cannot access /reports at all (403)"
    - "REVIEWER's existing call/validation workflow and every other role's existing Voter create/edit/delete ability is completely unchanged"
    - "reports_viewer's data is scoped to exactly one active campaign via the existing CampaignContext mechanism, same as leader/coordinator (no all-campaigns mixing)"
  artifacts:
    - path: "app/Enums/UserRole.php"
      provides: "REPORTS_VIEWER case with label/color/icon/description"
    - path: "app/Policies/VoterPolicy.php"
      provides: "Role-based denial of create/update/delete/deleteAny/restore/restoreAny/forceDelete/forceDeleteAny/replicate/reorder for reports_viewer only; true for every other role"
    - path: "app/Providers/Filament/ReportsPanelProvider.php"
      provides: "Dedicated 'reports' panel: VoterResource registered, all included report widgets, EnsureUserHasRole:reports_viewer auth gate"
    - path: "app/Filament/Resources/Voters/VoterResource.php"
      provides: "shouldRegisterNavigation() hides the Voters nav item specifically inside the reports panel (drill-through only, no persistent nav entry)"
  key_links:
    - from: "app/Providers/Filament/ReportsPanelProvider.php"
      to: "app/Filament/Resources/Voters/VoterResource.php"
      via: "->resources([VoterResource::class]) on the 'reports' panel"
    - from: "app/Filament/Widgets/FollowUpBacklogOverview.php, FallbackSourceOverview.php, TopLeadersTable.php"
      to: "VoterResource::getUrl('index', ...)"
      via: "Filament::getCurrentOrDefaultPanel() resolves to 'reports' panel routes when the widget renders inside it (no code change needed in these 3 files)"
    - from: "app/Policies/VoterPolicy.php"
      to: "Filament Resource Concerns HasAuthorization (canCreate/canEdit/canDelete/...)"
      via: "Laravel policy auto-resolution registered in app/Providers/AuthServiceProvider.php's $policies map"
    - from: "app/Providers/Filament/ReportsPanelProvider.php"
      to: "app/Http/Middleware/EnsureUserHasRole.php"
      via: "authMiddleware([Authenticate::class, EnsureUserHasRole::class + ':' + UserRole::REPORTS_VIEWER->value])"
---

<objective>
Add a new, distinct `reports_viewer` role (Spanish label "Analista de Reportes") with its own dedicated Filament panel at `/reports`. The panel's only screen is the built-in Dashboard rendering every existing report/overview widget (16 of the 23 files in `app/Filament/Widgets/`, enumerated below); the 3 widgets that already drill through (`FollowUpBacklogOverview`, `FallbackSourceOverview`, `TopLeadersTable`) keep working unmodified because `VoterResource` gets registered on the new panel too, so their existing `VoterResource::getUrl()` calls resolve to `/reports/voters/...` automatically. That landing list is made fully read-only for this one role via a new `VoterPolicy` (view/viewAny only) plus a handful of existing non-policy-gated action buttons (`export`, `validateCensus`, `exportCurrent`, `duplicatesReport`) explicitly hidden for the role, mirroring the codebase's existing `hasAnyRole()` gating precedent (`actualizar_registraduria`, `revalidateLeaderVoters`).

Purpose: give campaign staff a safe, read-only way to browse every report without any risk of mutating data or of a future Admin-panel change silently exposing an action button to them.
Output: new enum case, new Policy, new PanelProvider, gated action buttons on 9 files, seeded test user, Pest tests.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/quick/260730-fkf-add-read-only-reports-viewer-role-with-d/260730-fkf-CONTEXT.md
@app/Enums/UserRole.php
@app/Providers/Filament/CoordinatorPanelProvider.php
@app/Providers/Filament/LeaderPanelProvider.php
@app/Providers/AuthServiceProvider.php
@app/Policies/InvitationPolicy.php
@app/Http/Middleware/EnsureUserHasRole.php
@app/Filament/Resources/Voters/VoterResource.php
@app/Filament/Resources/Voters/Tables/VotersTable.php
@app/Filament/Resources/Voters/Pages/ListVoters.php
@tests/Feature/WidgetDrillThroughTest.php
@tests/Feature/RolePermissionTest.php
@tests/Feature/Filament/RevalidateLeaderVotersActionTest.php
@database/seeders/RoleUsersSeeder.php
</context>

<report_widget_inventory>
Authoritative classification of all 23 files in `app/Filament/Widgets/`, produced by reading every file (not assumed). This exact list drives Task 2's `ReportsPanelProvider::widgets()` array and Task 3's gating.

INCLUDED as reports (16 total — registered on the new panel's widgets array):
- CampaignStatsOverview — KPI overview (apoyos/confirmados/lideres/validacion), informational, no actions.
- FollowUpBacklogOverview — stat cards, existing url() drill-through to VoterResource (pending review). Keep as-is.
- FallbackSourceOverview — stat card, existing url() drill-through to VoterResource (fallback source, SRC-05). Keep as-is.
- ValidationProgressChart — 30-day trend chart, informational, no actions.
- TerritorialDistributionChart — top-10 municipios chart, informational, no actions.
- TopLeadersTable — ranking table, existing recordUrl() drill-through to VoterResource per leader; has an export headerAction, gate in Task 3.
- TopCoordinatorsTable — coverage/ranking table; has an export headerAction, gate in Task 3. No drill-through link today (data is inline).
- TerritorialOwnershipTable — ownership table, no actions.
- TopPollingPlacesTable — ranking table; has an export headerAction, gate in Task 3.
- RejectionsReportTable — rejections table; has an export headerAction, gate in Task 3.
- DuplicatesReportTable — duplicates table (D-06 cross-campaign exception, unrelated to this task, leave untouched); has an export headerAction, gate in Task 3.
- JurisdictionReportTable — jurisdiction table with existing canView() Nacional-scope gate (leave as-is, applies to this role too); has an export headerAction, gate in Task 3.
- ApoyosLideresCoordinadoresTable — flat CSV preview table; has an export headerAction, gate in Task 3.
- DiaDStatsOverview — Dia D participation stats, informational, no actions.
- DiaDTerritorialProgressTable — Dia D participation-by-municipio table, informational, no actions.
- SurveyStatsOverview — global survey stats (registered on AdminPanelProvider with no $surveyId, so it renders campaign-wide totals), informational, no actions.

EXCLUDED as operational (7 total — do NOT add to the new panel):
- CallQueueTable — has a mutating register_call recordAction (writes VerificationCall, updates CallAssignment). This is the exact "action queue, not a report" example from CONTEXT.md.
- CallCenterStatsOverview — personal performance stats scoped to Auth::id() of the acting reviewer ("Mi Rendimiento Hoy"); meaningless/empty for a non-reviewer and not a campaign report.
- CallCenterStatsWidget — same personal call-center KPI category, rendered on the CallCenter page for the acting reviewer; not a campaign report.
- CallHistoryTable — "Historial de Llamadas" filtered to caller_id = Auth::id(); a personal work log, not a report.
- BirthdayWidget — has a mutating send_message recordAction (links into message-compose); operational communication trigger, not a passive report.
- RevalidationProgressWidget — ephemeral wire:poll banner reading the latest RevalidationRun for a background job; a system-status indicator with no underlying filtered list to browse, not a data report.
- SurveyResultsWidget — orphaned: grep confirms it is not registered on any panel, page, or resource anywhere in the codebase (requires an externally-supplied $surveyId/$questionId prop that nothing currently provides). No live report exists to surface.
</report_widget_inventory>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: REPORTS_VIEWER enum case + VoterPolicy (read-only enforcement contract)</name>
  <files>
    app/Enums/UserRole.php,
    app/Policies/VoterPolicy.php,
    app/Providers/AuthServiceProvider.php,
    tests/Feature/RolePermissionTest.php,
    tests/Feature/Policies/VoterPolicyTest.php
  </files>
  <read_first>
    app/Enums/UserRole.php (all 4 match() methods - every one throws UnhandledMatchError if a case is missing an arm),
    app/Policies/InvitationPolicy.php (naming/style precedent for a plain Policy),
    app/Providers/AuthServiceProvider.php (existing $policies map AND the global Gate::before() closure - it already denies cross-campaign access for any Model with a campaign_id before any Policy method runs; your new Policy adds the role check on top, no conflict),
    tests/Feature/RolePermissionTest.php (the toHaveCount(5) assertion that WILL break once the enum grows to 6 cases)
  </read_first>
  <behavior>
    - Test: RolePermissionTest - role count is now 6 and reports_viewer exists in the roles table after RoleSeeder.
    - Test: VoterPolicyTest - a user with reports_viewer gets false from Gate::allows('create'|'update'|'delete'|'deleteAny'|'restore'|'forceDelete', Voter::class or a Voter instance).
    - Test: VoterPolicyTest - a user with reports_viewer gets true from Gate::allows('viewAny') and Gate::allows('view', $voter).
    - Test: VoterPolicyTest - a user with EVERY OTHER role (super_admin, admin_campaign, coordinator, leader, reviewer) still gets true for create/update/delete on Voter (no regression - parametrize with UserRole::cases() excluding REPORTS_VIEWER).
  </behavior>
  <action>
Add `case REPORTS_VIEWER = 'reports_viewer';` to UserRole (after REVIEWER), and add a matching arm to ALL FOUR match($this) blocks (getLabel, getColor, getIcon, getDescription) - missing any one throws UnhandledMatchError at runtime the first time that method is called for this case:
- getLabel: self::REPORTS_VIEWER => 'Analista de Reportes'
- getColor: self::REPORTS_VIEWER => 'gray'
- getIcon: self::REPORTS_VIEWER => 'heroicon-m-chart-bar'
- getDescription: self::REPORTS_VIEWER => 'Consulta reportes y listados de la campaña activa; sin permisos de creación, edición o eliminación.'

Create app/Policies/VoterPolicy.php (`php artisan make:policy VoterPolicy --model=Voter --no-interaction`, then replace the body), mirroring InvitationPolicy's style: every method returns true EXCEPT the mutating ones which return `! $user->hasRole(UserRole::REPORTS_VIEWER->value)` (true for every other role, unchanged behavior; false only for this new role). Implement these methods, all with the same one-line body pattern except viewAny/view which always return true:
viewAny(User $user): bool -> true
view(User $user, Voter $voter): bool -> true
create(User $user): bool -> ! $user->hasRole(UserRole::REPORTS_VIEWER->value)
update(User $user, Voter $voter): bool -> ! $user->hasRole(UserRole::REPORTS_VIEWER->value)
delete(User $user, Voter $voter): bool -> ! $user->hasRole(UserRole::REPORTS_VIEWER->value)
deleteAny(User $user): bool -> ! $user->hasRole(UserRole::REPORTS_VIEWER->value)
restore(User $user, Voter $voter): bool -> ! $user->hasRole(UserRole::REPORTS_VIEWER->value)
restoreAny(User $user): bool -> ! $user->hasRole(UserRole::REPORTS_VIEWER->value)
forceDelete(User $user, Voter $voter): bool -> ! $user->hasRole(UserRole::REPORTS_VIEWER->value)
forceDeleteAny(User $user): bool -> ! $user->hasRole(UserRole::REPORTS_VIEWER->value)
replicate(User $user, Voter $voter): bool -> ! $user->hasRole(UserRole::REPORTS_VIEWER->value)
reorder(User $user): bool -> ! $user->hasRole(UserRole::REPORTS_VIEWER->value)

These ability names match exactly what Filament calls via Filament\Resources\Resource\Concerns\HasAuthorization (canCreate->create, canEdit->update, canDelete->delete, canDeleteAny->deleteAny, etc.) - this is what makes Create/Edit/Delete/bulk-delete buttons disappear automatically for this role on any resource that gets this policy, and blocks direct navigation to /reports/voters/create and /reports/voters/{id}/edit with a 403 (Filament's CreateRecord/EditRecord pages call abort_unless(canCreate()/canEdit(), 403) in mount()).

Register it in app/Providers/AuthServiceProvider.php's $policies array (add `use App\Models\Voter;` and `use App\Policies\VoterPolicy;`, then `Voter::class => VoterPolicy::class,` alongside the existing `Invitation::class => InvitationPolicy::class,` entry) - this codebase registers policies explicitly rather than relying on convention-only auto-discovery.

Fix tests/Feature/RolePermissionTest.php: change `expect($roles)->toHaveCount(5);` to `expect($roles)->toHaveCount(6);` in the 'creates all roles from UserRole enum' test - this is the only role-count assertion in the suite (confirmed via grep; no other test hardcodes an exact UserRole::cases() count).

Create tests/Feature/Policies/VoterPolicyTest.php covering the behavior block above. Seed roles via `collect(UserRole::values())->each(fn ($role) => \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));` in beforeEach, matching the convention in every sibling test file. Use `Pest\Laravel\actingAs` + `$user->can('create', Voter::class)` / `$user->can('update', $voter)` etc (or `Gate::forUser($user)->allows(...)`) - a real Voter created via factory for the instance-based checks. Note: AuthServiceProvider's Gate::before() short-circuits campaign-mismatched records to deny before your Policy even runs - either don't set a campaign_id mismatch (leave CampaignContext unset / use `Session::put('campaign_context.mode', 'all')` with a super_admin acting user for the non-reports_viewer parametrized cases so the campaign check defers), or create the Voter under the acting user's own active campaign for the reports_viewer/other-role positive/negative assertions.

Run `vendor/bin/pint --dirty`.
  </action>
  <verify>
    <automated>php artisan test tests/Feature/RolePermissionTest.php tests/Feature/Policies/VoterPolicyTest.php --stop-on-failure</automated>
  </verify>
  <done>UserRole has a 6th case with all 4 match arms populated (no UnhandledMatchError), VoterPolicy denies every mutating ability for reports_viewer only and allows it for every other role, registered in AuthServiceProvider, and both test files pass.</done>
</task>

<task type="auto">
  <name>Task 2: ReportsPanelProvider - dedicated panel, VoterResource registration, widget list, nav hide, seeded user</name>
  <files>
    app/Providers/Filament/ReportsPanelProvider.php,
    bootstrap/providers.php,
    app/Filament/Resources/Voters/VoterResource.php,
    database/seeders/RoleUsersSeeder.php
  </files>
  <read_first>
    app/Providers/Filament/CoordinatorPanelProvider.php (exact structure/colors/middleware to mirror),
    app/Providers/Filament/LeaderPanelProvider.php,
    bootstrap/providers.php,
    app/Filament/Resources/Voters/VoterResource.php (no existing can*/navigation overrides - you are adding the first one),
    vendor/filament/filament/src/Resources/Resource/Concerns/HasRoutes.php getRouteBaseName() (resolves via Filament::getCurrentOrDefaultPanel() when no explicit panel is passed - this is WHY FollowUpBacklogOverview/FallbackSourceOverview/TopLeadersTable need zero code changes once VoterResource is registered on this new panel too),
    database/seeders/RoleUsersSeeder.php (pattern for each of the 5 existing seeded role users)
  </read_first>
  <action>
Create app/Providers/Filament/ReportsPanelProvider.php, copying CoordinatorPanelProvider's structure exactly (same brand/colors/middleware stack) with these differences:
- `use App\Http\Middleware\EnsureUserHasRole;` explicit import (per this repo's CLAUDE.md import rule - do NOT use the sibling panels' inline `\App\Http\Middleware\EnsureUserHasRole::class` pattern for this new file).
- `->id('reports')->path('reports')`.
- `->resources([VoterResource::class])` (import `use App\Filament\Resources\Voters\VoterResource;`) - this is what makes VoterResource::getUrl() resolve to /reports/voters/... when called from a widget rendered inside this panel, and gives the panel its own isolated /reports/voters routes (separate from /admin/voters) without touching AdminPanelProvider.
- `->pages([Dashboard::class])` - the built-in Filament\Pages\Dashboard, same class Admin/Coordinator/Leader already use; it IS the reports-home screen once populated with the widget list below. No new custom Page class needed.
- `->widgets([...])` - exactly the 16 classes from the report_widget_inventory INCLUDED list above, each with a `use App\Filament\Widgets\{Name};` import, in this order: CampaignStatsOverview, FollowUpBacklogOverview, FallbackSourceOverview, ValidationProgressChart, TerritorialDistributionChart, TopLeadersTable, TopCoordinatorsTable, TerritorialOwnershipTable, TopPollingPlacesTable, RejectionsReportTable, DuplicatesReportTable, JurisdictionReportTable, ApoyosLideresCoordinadoresTable, SurveyStatsOverview, DiaDStatsOverview, DiaDTerritorialProgressTable.
- `->authMiddleware([Authenticate::class, EnsureUserHasRole::class.':'.UserRole::REPORTS_VIEWER->value])`.
- No PanelsRenderHook::TOPBAR_END campaign-switcher render hook (Coordinator/Leader don't have one either) - CampaignContext::currentCampaignId() already auto-resolves a non-super-admin user's single assigned campaign via the campaigns() relationship, exactly like leader/coordinator today. Keep the PanelsRenderHook::BODY_END motion-init hook for visual consistency.

Register the provider in bootstrap/providers.php: add `App\Providers\Filament\ReportsPanelProvider::class,` after `App\Providers\Filament\LeaderPanelProvider::class,`.

In app/Filament/Resources/Voters/VoterResource.php, add (with `use Filament\Facades\Filament;`) a new static method:
```
public static function shouldRegisterNavigation(): bool
{
    return Filament::getCurrentPanel()?->getId() !== 'reports';
}
```
This hides the "Apoyos" nav item specifically when the resource is being rendered inside the reports panel (drill-through only, per CONTEXT.md's "nothing else from Admin is reachable") while leaving the Admin panel's own sidebar completely unaffected (there getCurrentPanel()->getId() is 'admin', so this returns true as before - Resource::shouldRegisterNavigation() defaults to true when unset, so this is behavior-preserving for Admin, Coordinator, Leader).

In database/seeders/RoleUsersSeeder.php: add 'reports_viewer' to the top-of-method $roles array (around line 24), and add a 6th seeded user block mirroring the existing 5 (place after the Reviewer block, before the closing summary):
```
// 6. Analista de Reportes (solo lectura)
$reportsViewer = User::firstOrCreate(
    ['email' => 'ing.korozco+analista@gmail.com'],
    [
        'name' => 'Analista de Reportes',
        'password' => $password,
        'email_verified_at' => now(),
    ]
);
$reportsViewer->syncRoles(['reports_viewer']);
$reportsViewer->campaigns()->syncWithoutDetaching([$campaign->id]);

$this->command->info('✓ Analista de Reportes creado: ing.korozco+analista@gmail.com');
```
Also add the corresponding line to the final printed summary block ('Analista:        ing.korozco+analista@gmail.com').

Run `vendor/bin/pint --dirty`.
  </action>
  <verify>
    <automated>php artisan route:list --no-interaction | grep "filament.reports" | head -5</automated>
  </verify>
  <done>/reports panel exists with VoterResource + all 16 report widgets registered, requires the reports_viewer role, VoterResource hides its nav entry only inside this panel, and RoleUsersSeeder creates a 6th test user for the role.</done>
</task>

<task type="auto">
  <name>Task 3: Gate the export headerAction on the 7 report widgets that have one</name>
  <files>
    app/Filament/Widgets/ApoyosLideresCoordinadoresTable.php,
    app/Filament/Widgets/DuplicatesReportTable.php,
    app/Filament/Widgets/JurisdictionReportTable.php,
    app/Filament/Widgets/RejectionsReportTable.php,
    app/Filament/Widgets/TopCoordinatorsTable.php,
    app/Filament/Widgets/TopLeadersTable.php,
    app/Filament/Widgets/TopPollingPlacesTable.php
  </files>
  <read_first>
    Each of the 7 files above (each has exactly one Action::make('export') in a ->headerActions([...]) block),
    app/Filament/Resources/Voters/Tables/VotersTable.php around line 245-253 (revalidateLeaderVoters's existing `->visible(fn (): bool => auth()->user()?->hasAnyRole([...]) ?? false)` - this is the exact precedent to mirror, per CONTEXT.md)
  </read_first>
  <action>
On EACH of the 7 files' Action::make('export') chain, add a ->visible() call that hides the button only for the new role (mechanical, identical pattern in every file - insert it anywhere in the fluent chain, e.g. right after ->icon(...)):
```
->visible(fn (): bool => ! (auth()->user()?->hasRole(UserRole::REPORTS_VIEWER->value) ?? false))
```
This is a pure ADDITION - every other role keeps seeing the export button exactly as before (hasRole() is false for them, so !false = true = visible).

Four of the 7 files do not yet import App\Enums\UserRole - add it (alphabetically, per this repo's use-ordering convention):
- app/Filament/Widgets/DuplicatesReportTable.php: insert `use App\Enums\UserRole;` as the new first use line (before App\Exports\DuplicatesExport).
- app/Filament/Widgets/JurisdictionReportTable.php: insert `use App\Enums\UserRole;` right after `use App\Enums\CampaignScope;`.
- app/Filament/Widgets/RejectionsReportTable.php: insert `use App\Enums\UserRole;` between `use App\Enums\CallResult;` and `use App\Enums\VoterStatus;`.
- app/Filament/Widgets/TopPollingPlacesTable.php: insert `use App\Enums\UserRole;` as the new first use line (before App\Enums\VoterStatus).

The other 3 files (ApoyosLideresCoordinadoresTable.php, TopCoordinatorsTable.php, TopLeadersTable.php) already import App\Enums\UserRole - no import change needed there, just the ->visible() addition.

Run `vendor/bin/pint --dirty`.
  </action>
  <verify>
    <automated>grep -rl "REPORTS_VIEWER->value" app/Filament/Widgets/ApoyosLideresCoordinadoresTable.php app/Filament/Widgets/DuplicatesReportTable.php app/Filament/Widgets/JurisdictionReportTable.php app/Filament/Widgets/RejectionsReportTable.php app/Filament/Widgets/TopCoordinatorsTable.php app/Filament/Widgets/TopLeadersTable.php app/Filament/Widgets/TopPollingPlacesTable.php | wc -l</automated>
  </verify>
  <done>All 7 files' export action returns 7 from the grep count above (one match per file), and vendor/bin/pint --dirty is clean.</done>
</task>

<task type="auto">
  <name>Task 4: Gate the remaining non-policy-gated action buttons on the VoterResource drill target (VotersTable + ListVoters)</name>
  <files>
    app/Filament/Resources/Voters/Tables/VotersTable.php,
    app/Filament/Resources/Voters/Pages/ListVoters.php
  </files>
  <read_first>
    app/Filament/Resources/Voters/Tables/VotersTable.php (the `validateCensus` recordAction around line 280 - it has NO role check today, only `->visible(fn (Voter $record): bool => $record->status !== VoterStatus::DUPLICATE)`; ViewAction/EditAction and the DeleteBulkAction are already Policy-gated by Task 1's VoterPolicy, no change needed to those),
    app/Filament/Resources/Voters/Pages/ListVoters.php (the `exportCurrent`, `export`, and `duplicatesReport` header actions - none have a role check today; `CreateAction` is Policy-gated already, `ImportAction` is already role-gated to ADMIN_CAMPAIGN/SUPER_ADMIN so it's already hidden for this role, no change needed to those two)
  </read_first>
  <action>
In app/Filament/Resources/Voters/Tables/VotersTable.php, extend the `validateCensus` action's existing `->visible()` closure to also exclude the new role (keep the existing DUPLICATE-status check, AND it with the role check):
```
->visible(fn (Voter $record): bool => $record->status !== VoterStatus::DUPLICATE
    && ! (auth()->user()?->hasRole(UserRole::REPORTS_VIEWER->value) ?? false))
```
`UserRole` is already imported in this file (used by `revalidateLeaderVoters`'s visible() check) - no new import needed.

In app/Filament/Resources/Voters/Pages/ListVoters.php, add a `->visible(fn (): bool => ! (auth()->user()?->hasRole(UserRole::REPORTS_VIEWER->value) ?? false))` call to each of these 3 header actions (insert anywhere in each fluent chain, e.g. right after `->color(...)`):
- `Action::make('exportCurrent')`
- `Action::make('export')`
- `Action::make('duplicatesReport')`
`UserRole` is already imported in this file (used by `ImportAction`'s visible() check) - no new import needed.

Do NOT touch `CreateAction::make()` (Policy-gated automatically by Task 1's VoterPolicy::create) or `ImportAction::make()` (already role-gated to ADMIN_CAMPAIGN/SUPER_ADMIN, which already excludes the new role).

Run `vendor/bin/pint --dirty`.
  </action>
  <verify>
    <automated>grep -c "REPORTS_VIEWER->value" app/Filament/Resources/Voters/Tables/VotersTable.php app/Filament/Resources/Voters/Pages/ListVoters.php</automated>
  </verify>
  <done>validateCensus (VotersTable) and exportCurrent/export/duplicatesReport (ListVoters) are all hidden for reports_viewer while remaining visible for every other role; grep shows 1 match in VotersTable.php and 3 matches in ListVoters.php.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 5: Feature tests - panel access, direct-URL 403s, and read-only table enforcement</name>
  <files>
    tests/Feature/Filament/ReportsPanelTest.php
  </files>
  <read_first>
    tests/Feature/Filament/RevalidateLeaderVotersActionTest.php (exact `assertTableActionVisible`/`assertTableActionHidden` API conventions used in this codebase for both header and record actions),
    tests/Feature/WidgetDrillThroughTest.php (campaign-context setup pattern: Session::put('campaign_context.campaign_id', ...) + Session::put('campaign_context.mode', 'single')),
    app/Filament/Resources/Voters/Pages/ListVoters.php, app/Filament/Resources/Voters/Tables/VotersTable.php (action names to assert: view, edit, validateCensus, exportCurrent, export, duplicatesReport; bulk action: delete)
  </read_first>
  <behavior>
    - Test: a reports_viewer user gets 200 on GET /reports (panel dashboard).
    - Test: a leader (or any other single role) gets 403 on GET /reports.
    - Test: a reports_viewer user gets 200 on GET /reports/voters (index) and on GET /reports/voters/{voter} (view), for a voter in their own active campaign.
    - Test: a reports_viewer user gets 403 on GET /reports/voters/create and on GET /reports/voters/{voter}/edit.
    - Test: within ListVoters, a reports_viewer-acting user sees `view` visible but `edit` hidden on a record, and the `delete` bulk action hidden; a super_admin-acting user still sees all of them (regression check).
    - Test: within ListVoters, a reports_viewer-acting user sees `validateCensus`, `exportCurrent`, `export`, `duplicatesReport` all hidden; a super_admin-acting user still sees them (regression check).
  </behavior>
  <action>
Create tests/Feature/Filament/ReportsPanelTest.php. In beforeEach: seed all roles via `collect(UserRole::values())->each(fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));`, create an active Campaign, and create a `reports_viewer` User with `->assignRole(UserRole::REPORTS_VIEWER->value)` and `->campaigns()->attach($campaign->id)` (or `syncWithoutDetaching`) so `CampaignContext::currentCampaignId()` resolves to that campaign automatically (same mechanism as leader/coordinator - no session campaign_id override needed for this user since they are not super_admin).

HTTP-level tests (use `Pest\Laravel\actingAs` then `$this->get('/reports')` etc.; `->assertOk()` / `->assertForbidden()`):
1. `actingAs($reportsViewer)->get('/reports')->assertOk();`
2. `actingAs($leader)->get('/reports')->assertForbidden();` (leader has no reports_viewer role - EnsureUserHasRole throws AuthorizationException -> 403)
3. Create a Voter in `$reportsViewer`'s campaign, then `actingAs($reportsViewer)->get('/reports/voters')->assertOk();` and `actingAs($reportsViewer)->get(VoterResource::getUrl('view', ['record' => $voter], panel: 'reports'))->assertOk();` (pass the explicit `panel: 'reports'` argument to `getUrl()` here since the test itself isn't rendering inside panel context the way a real request would - confirm the exact `getUrl()` signature accepts a `panel` string argument via `App\Filament\Resources\Voters\VoterResource` `use`).
4. `actingAs($reportsViewer)->get(VoterResource::getUrl('create', panel: 'reports'))->assertForbidden();` and `actingAs($reportsViewer)->get(VoterResource::getUrl('edit', ['record' => $voter], panel: 'reports'))->assertForbidden();`

Livewire-level tests (mirror `RevalidateLeaderVotersActionTest.php`'s exact API - `Livewire::test(ListVoters::class)`, `Session::put('campaign_context.mode', 'all')` for a super_admin comparison actor so campaign scoping doesn't interfere with the regression assertions, and `Session::put('campaign_context.campaign_id', ...)` + `'single'` mode for the reports_viewer actor per `WidgetDrillThroughTest.php`'s pattern):
5. `actingAs($reportsViewer)` + `Livewire::test(ListVoters::class)->assertTableActionVisible('view', record: $voter)->assertTableActionHidden('edit', record: $voter)->assertTableBulkActionHidden('delete');` (verify exact method name for bulk actions via Filament's testing trait if `assertTableBulkActionHidden` does not exist in this Filament version - search-docs or check `vendor/filament/tables/src/Testing` if the exact assertion name differs).
6. Same Livewire test, `->assertTableActionHidden('validateCensus', record: $voter)->assertTableActionHidden('exportCurrent')->assertTableActionHidden('export')->assertTableActionHidden('duplicatesReport');`
7. Repeat 5-6 `actingAs($superAdmin)` (with `Session::put('campaign_context.mode', 'all')`) asserting the same actions are `assertTableActionVisible`/`assertTableBulkActionVisible` instead - proves no regression for existing roles.

Run `vendor/bin/pint --dirty`.
  </action>
  <verify>
    <automated>php artisan test tests/Feature/Filament/ReportsPanelTest.php --stop-on-failure</automated>
  </verify>
  <done>All 7 scenarios above pass: panel access is role-gated, direct create/edit URLs 403 for reports_viewer, and every gated action/bulk-action is hidden for reports_viewer while remaining visible for super_admin.</done>
</task>

</tasks>

<verification>
- `php artisan test tests/Feature/RolePermissionTest.php tests/Feature/Policies/VoterPolicyTest.php tests/Feature/Filament/ReportsPanelTest.php` - all pass
- `php artisan test` (full suite) - no new failures beyond the pre-existing, already-logged CampaignContext test-pollution flakiness (see STATE.md Blockers/Concerns)
- `php artisan route:list --no-interaction | grep filament.reports` - shows the reports panel's dashboard + voters resource routes
- `grep -rn "REPORTS_VIEWER" app/Enums/UserRole.php` - one case + 4 match arms
- `vendor/bin/pint --dirty` - clean
</verification>

<success_criteria>
- New `reports_viewer` UserRole case exists with Spanish label/color/icon/description, all 4 match() arms populated
- Dedicated `/reports` Filament panel exists, gated to the new role only, showing exactly the 16 included report widgets
- VoterResource is the only resource reachable from this panel (registered explicitly, nav hidden), reached via the 3 existing drill-through widgets without modifying their code
- VoterPolicy makes Create/Edit/Delete/bulk-delete impossible for this role on Voters (both hidden and 403 on direct URL), with zero behavior change for every other role
- The 7 export buttons plus validateCensus/exportCurrent/export/duplicatesReport are all hidden for this role specifically, mirroring the codebase's existing hasAnyRole() gating precedent
- REVIEWER role and all other existing roles are completely untouched
- RolePermissionTest's role-count assertion updated; every change covered by a Pest test
</success_criteria>

<output>
After completion, create `.planning/quick/260730-fkf-add-read-only-reports-viewer-role-with-d/260730-fkf-SUMMARY.md`
</output>
