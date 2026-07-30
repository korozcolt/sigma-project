---
phase: quick-260730-hlg
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Filament/Widgets/TerritorialOwnershipTable.php
  - app/Filament/Widgets/TopCoordinatorsTable.php
  - app/Filament/Widgets/CampaignStatsOverview.php
  - app/Filament/Widgets/TopPollingPlacesTable.php
  - app/Filament/Resources/Voters/Tables/VotersTable.php
  - app/Filament/Widgets/JurisdictionReportTable.php
  - app/Filament/Widgets/RejectionsReportTable.php
  - app/Filament/Widgets/ApoyosLideresCoordinadoresTable.php
  - tests/Feature/WidgetDrillThroughTest.php
autonomous: true
requirements: [HLG-DRILL]
must_haves:
  truths:
    - "Clicking a leader row in 'Propiedad Territorial y de Equipos' navigates to that leader's registered_by-filtered Voters list"
    - "Clicking a coordinator row (in Territorial or in 'Cobertura y Ranking de Coordinadores') navigates to that coordinator's whole team's registered_by-filtered Voters list (all their leaders' ids plus the coordinator's own id)"
    - "Clicking the 'Total de Apoyos' stat navigates to the full campaign Voters list"
    - "Clicking the 'Apoyos Confirmados' stat navigates to the status=confirmed Voters list"
    - "Clicking a polling place row in 'Ranking de Puestos de Votación' navigates to that polling place's filtered Voters list"
    - "Clicking a row in Informe de Jurisdicción, Informe de Rechazos, or the flat Apoyos+Líderes+Coordinadores preview navigates to that voter's detail view page"
    - "Every drill-through resolves within the current panel (reports or admin), respects campaign isolation, and never bypasses VoterPolicy"
  artifacts:
    - path: "app/Filament/Widgets/TerritorialOwnershipTable.php"
      provides: "recordUrl branching leader vs coordinator team"
      contains: "recordUrl"
    - path: "app/Filament/Widgets/TopCoordinatorsTable.php"
      provides: "coordinator-team recordUrl"
      contains: "recordUrl"
    - path: "app/Filament/Widgets/CampaignStatsOverview.php"
      provides: "->url() on Total de Apoyos and Apoyos Confirmados stats"
      contains: "VoterResource::getUrl"
    - path: "app/Filament/Resources/Voters/Tables/VotersTable.php"
      provides: "polling_place_id SelectFilter enabling polling-place drill-through"
      contains: "polling_place_id"
    - path: "app/Filament/Widgets/TopPollingPlacesTable.php"
      provides: "polling-place recordUrl"
      contains: "recordUrl"
    - path: "app/Filament/Widgets/JurisdictionReportTable.php"
      provides: "per-voter view recordUrl"
      contains: "getUrl('view'"
    - path: "app/Filament/Widgets/RejectionsReportTable.php"
      provides: "per-voter view recordUrl"
      contains: "getUrl('view'"
    - path: "app/Filament/Widgets/ApoyosLideresCoordinadoresTable.php"
      provides: "per-voter view recordUrl"
      contains: "getUrl('view'"
    - path: "tests/Feature/WidgetDrillThroughTest.php"
      provides: "test coverage for every new drill-through"
      contains: "getRecordUrl"
  key_links:
    - from: "app/Filament/Widgets/TerritorialOwnershipTable.php"
      to: "VoterResource index (registered_by filter)"
      via: "->recordUrl() branching on record role"
      pattern: "registered_by"
    - from: "app/Filament/Widgets/TopCoordinatorsTable.php"
      to: "VoterResource index (registered_by team filter)"
      via: "->recordUrl() using leaders()->pluck('id')->push(id)"
      pattern: "registered_by"
    - from: "app/Filament/Widgets/CampaignStatsOverview.php"
      to: "VoterResource index"
      via: "Stat->url()"
      pattern: "VoterResource::getUrl"
    - from: "app/Filament/Widgets/TopPollingPlacesTable.php"
      to: "VoterResource index (polling_place_id filter)"
      via: "->recordUrl() + VotersTable polling_place_id SelectFilter"
      pattern: "polling_place_id"
    - from: "app/Filament/Widgets/JurisdictionReportTable.php"
      to: "VoterResource view"
      via: "->recordUrl()"
      pattern: "getUrl\\('view'"
---

<objective>
Make the remaining, currently-static report-panel widgets clickable so each result drills through to the detailed Voters list (or voter detail) behind it — matching the three widgets that already do this (FollowUpBacklogOverview, FallbackSourceOverview, TopLeadersTable).

Purpose: Client feedback — the reports panel looks fine but "practically everything on it should be clickable" and lead to the underlying detail. Aggregate rows (leaders, coordinators, polling places) and headline stats should open the filtered Voters list; per-voter report rows should open that voter's detail view.

Output: recordUrl/url drill-throughs added to 7 widgets + a new polling_place_id filter on VotersTable, all covered by new cases in the existing WidgetDrillThroughTest.php. DuplicatesReportTable is a deliberate, documented skip (see below).

Drill-through target rules applied:
- Aggregate rows (a User or PollingPlace representing a GROUP of voters) -> filtered Voters LIST.
- Per-voter rows (a single Voter) -> that voter's DETAIL VIEW page (always accurate, read-only-safe, campaign-safe).

Deliberate skip — DuplicatesReportTable ("Informe de Duplicados"): NO drill-through added. It is the project's one intentional cross-campaign-isolation exception (queries with `withoutGlobalScopes()` + `withTrashed()`; sibling rows can legitimately belong to a DIFFERENT campaign — see the file's own header docblock). A recordUrl to the campaign-scoped VoterResource would either 404 those cross-campaign siblings under CampaignContextScope or, if scope were bypassed, leak cross-campaign data into a navigable detail — violating the hard campaign-isolation constraint. There is also no document_number SelectFilter on VotersTable to land a duplicate-group list on. Task 4 asserts this widget has a null recordUrl to lock the skip in.

Deliberate skip on stats — "Líderes Activos" (a user headcount, not a voter list) and "Progreso de Validación" (derived from call_verified_at, for which VotersTable has no matching filter) get NO url: no natural single filtered-list target, and the accuracy constraint forbids a misleading filter.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

# The established, working drill-through pattern to copy — do NOT modify these:
@app/Filament/Widgets/TopLeadersTable.php
@app/Filament/Widgets/FollowUpBacklogOverview.php

# Reference test file — add all new cases here, following its exact conventions:
@tests/Feature/WidgetDrillThroughTest.php

# The list resource the drill-throughs land on (filter keys + view page):
@app/Filament/Resources/Voters/Tables/VotersTable.php

<interfaces>
<!-- Verified facts the executor should rely on directly — no re-exploration needed. -->

VoterResource (App\Filament\Resources\Voters\VoterResource) pages:
  'index' => ListVoters   ->  VoterResource::getUrl('index', ['tableFilters' => [...]])
  'view'  => ViewVoter    ->  VoterResource::getUrl('view', ['record' => $voter])
  getUrl() resolves against Filament::getCurrentOrDefaultPanel(), so the same call
  yields the /reports route on the reports panel and /admin route on the admin panel
  (this is exactly why the existing 3 drill-throughs work unchanged on both panels).

VotersTable filter keys that ALREADY exist (Filament SelectFilter, all ->multiple()):
  'registered_by'  (relationship registeredBy) — value shape: ['values' => [id, ...]]
  'status'         (VoterStatus options)       — value shape: ['values' => ['confirmed', ...]]
  'municipality_id'(relationship municipality)
  'campaign_id', 'polling_place_source', 'neighborhood_id'
  NO polling_place_id filter yet (Task 3 adds it). NO document_number / call_result filter.

Relationships (verified):
  User::leaders()  = hasMany(User::class, 'coordinator_user_id')   // a coordinator's leaders
  User::coordinator() = belongsTo(User::class, 'coordinator_user_id')
  Voter::pollingPlace() = belongsTo(PollingPlace::class, 'polling_place_id')

VoterPolicy::view() returns true for everyone (reports_viewer included) — linking a
per-voter row to the 'view' page is read-only and policy-safe.

Coordinator "whole team" registered_by value (used in Task 1 + Task 2):
  $teamIds = $coordinator->leaders()->pluck('id')->push($coordinator->id)->all();
  (all the coordinator's leaders PLUS the coordinator's own id, since a coordinator can
   be a voter's registered_by directly — precedent: ApoyosLideresCoordinadoresTable's
   'coordinador' column logic.)

Stat url: Filament\Widgets\StatsOverviewWidget\Stat supports ->url(string $url); same
mechanism FollowUpBacklogOverview already uses (asserted via $stat->getUrl() in tests).
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Coordinator/leader team drill-through on TerritorialOwnershipTable + TopCoordinatorsTable</name>
  <files>app/Filament/Widgets/TerritorialOwnershipTable.php, app/Filament/Widgets/TopCoordinatorsTable.php, tests/Feature/WidgetDrillThroughTest.php</files>
  <behavior>
    - TopCoordinatorsTable: getTable()->getRecordUrl($coordinator) equals VoterResource::getUrl('index', ['tableFilters' => ['registered_by' => ['values' => $teamIds]]]) where $teamIds = the coordinator's leaders' ids plus the coordinator's own id.
    - TerritorialOwnershipTable, coordinator record: same team url as above.
    - TerritorialOwnershipTable, leader record: VoterResource::getUrl('index', ['tableFilters' => ['registered_by' => ['values' => [$leader->id]]]]) (identical to TopLeadersTable's existing pattern).
  </behavior>
  <action>
    Add `use App\Filament\Resources\Voters\VoterResource;` to both files (explicit use, alphabetical order per Pint/CLAUDE.md — no aliases, no inline paths).

    TopCoordinatorsTable::table(): chain `->recordUrl(fn (User $record) => VoterResource::getUrl('index', ['tableFilters' => ['registered_by' => ['values' => $record->leaders()->pluck('id')->push($record->id)->all()]]]))` onto the returned $table (its rows are always coordinators). `User` is already imported.

    TerritorialOwnershipTable::table(): chain a `->recordUrl(function (User $record): string { ... })` that branches — because its rows are BOTH coordinators and leaders. Branch coordinator-first (a user could hold both roles; the team view is the superset):
      - if $record->hasRole(UserRole::COORDINATOR->value): return the team url (leaders()->pluck('id')->push($record->id)->all()).
      - else (leader): return VoterResource::getUrl('index', ['tableFilters' => ['registered_by' => ['values' => [$record->id]]]]).
    `UserRole` and `User` are already imported. Use curly braces (always, per CLAUDE.md) and an explicit `: string` return type on the closure.

    Do NOT touch TopLeadersTable (already correct). Do NOT change either widget's query, columns, or headerActions.
  </action>
  <verify>
    <automated>php artisan test --filter=WidgetDrillThroughTest tests/Feature/WidgetDrillThroughTest.php</automated>
  </verify>
  <done>Both widgets expose a recordUrl; new Pest cases assert the leader url and the coordinator-team url (team = leaders' ids + coordinator's own id) for both TerritorialOwnershipTable and TopCoordinatorsTable, and pass.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Total de Apoyos + Apoyos Confirmados stat drill-through on CampaignStatsOverview</name>
  <files>app/Filament/Widgets/CampaignStatsOverview.php, tests/Feature/WidgetDrillThroughTest.php</files>
  <behavior>
    - The 'Total de Apoyos' Stat (getTotalVotersStat, active-campaign branch) has getUrl() == VoterResource::getUrl('index') (no extra tableFilters — VoterResource's list is already campaign-scoped via the Voter CampaignContextScope global scope, consistent with how the stat itself is scoped).
    - The 'Apoyos Confirmados' Stat (getConfirmedVotersStat, active-campaign branch) has getUrl() == VoterResource::getUrl('index', ['tableFilters' => ['status' => ['values' => [VoterStatus::CONFIRMED->value]]]]).
    - The no-active-campaign early-return stats keep getUrl() === null (unchanged).
  </behavior>
  <action>
    Add `use App\Filament\Resources\Voters\VoterResource;` (alphabetical). `VoterStatus` is already imported.

    getTotalVotersStat(): on the active-campaign return path only (the one that already calls ->chart(...)), add `->url(VoterResource::getUrl('index'))`. Leave the `if (! $activeCampaign)` warning branch untouched (no url).

    getConfirmedVotersStat(): on the active-campaign return path only, add `->url(VoterResource::getUrl('index', ['tableFilters' => ['status' => ['values' => [VoterStatus::CONFIRMED->value]]]]))`. Leave the `if (! $activeCampaign)` branch untouched.

    Do NOT add a url to getActiveLeadersStat ('Líderes Activos' — a user headcount, no voter-list target) or getValidationProgressStat ('Progreso de Validación' — call_verified_at based, no matching VotersTable filter). Leave both, and all chart helpers, unchanged.
  </action>
  <verify>
    <automated>php artisan test --filter=WidgetDrillThroughTest tests/Feature/WidgetDrillThroughTest.php</automated>
  </verify>
  <done>New Pest cases (using the ReflectionMethod getStats() pattern from the existing follow-up-backlog test) assert stats[0] (Total) url == index url and the Confirmados stat url == status=confirmed url; both pass. Líderes Activos / Progreso stats assert null url.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Polling-place drill-through — add polling_place_id filter to VotersTable + recordUrl on TopPollingPlacesTable</name>
  <files>app/Filament/Resources/Voters/Tables/VotersTable.php, app/Filament/Widgets/TopPollingPlacesTable.php, tests/Feature/WidgetDrillThroughTest.php</files>
  <behavior>
    - VotersTable exposes a 'polling_place_id' SelectFilter (multiple, on the pollingPlace relationship) so the drill-through filter actually applies.
    - TopPollingPlacesTable getTable()->getRecordUrl($pollingPlace) == VoterResource::getUrl('index', ['tableFilters' => ['polling_place_id' => ['values' => [$pollingPlace->id]]]]).
  </behavior>
  <action>
    VotersTable::configure(): add a new SelectFilter to the ->filters([...]) array, immediately after the existing `municipality_id` filter, mirroring its style exactly:
      `SelectFilter::make('polling_place_id')->label('Puesto de Votación')->relationship('pollingPlace', 'name')->searchable()->preload()->multiple(),`
    (`SelectFilter` is already imported.) Do not reorder or alter the other filters.

    TopPollingPlacesTable::table(): add `use App\Filament\Resources\Voters\VoterResource;` (alphabetical) and `use App\Models\PollingPlace;` is already imported. Chain `->recordUrl(fn (PollingPlace $record) => VoterResource::getUrl('index', ['tableFilters' => ['polling_place_id' => ['values' => [$record->id]]]]))` onto the returned $table. Do not change its query/columns/headerActions.
  </action>
  <verify>
    <automated>php artisan test --filter=WidgetDrillThroughTest tests/Feature/WidgetDrillThroughTest.php</automated>
  </verify>
  <done>VotersTable has a polling_place_id SelectFilter; TopPollingPlacesTable rows link to the polling_place_id-filtered Voters list; a new Pest case creates a PollingPlace with campaign voters, asserts the recordUrl equals the expected filtered url, and passes.</done>
</task>

<task type="auto" tdd="true">
  <name>Task 4: Per-voter view drill-through on Jurisdiction/Rejections/ApoyosLideres; lock Duplicates skip</name>
  <files>app/Filament/Widgets/JurisdictionReportTable.php, app/Filament/Widgets/RejectionsReportTable.php, app/Filament/Widgets/ApoyosLideresCoordinadoresTable.php, tests/Feature/WidgetDrillThroughTest.php</files>
  <behavior>
    - JurisdictionReportTable, RejectionsReportTable, ApoyosLideresCoordinadoresTable: getTable()->getRecordUrl($voter) == VoterResource::getUrl('view', ['record' => $voter->id]) for each.
    - DuplicatesReportTable: getTable()->getRecordUrl($voter) === null (deliberate skip — cross-campaign isolation exception).
  </behavior>
  <action>
    These three widgets each render one row per Voter (flat detail / export-preview tables), so the faithful "detail behind that result" is the voter's own record. Link each row to its voter view page — always accurate, read-only, campaign-safe (VoterPolicy::view() == true).

    In each of JurisdictionReportTable, RejectionsReportTable, ApoyosLideresCoordinadoresTable:
      - Add `use App\Filament\Resources\Voters\VoterResource;` (alphabetical). `Voter` is already imported in each.
      - Chain `->recordUrl(fn (Voter $record) => VoterResource::getUrl('view', ['record' => $record]))` onto the returned $table. Do not change queries, columns, canView(), getTableDescription(), or headerActions.

    Do NOT add anything to DuplicatesReportTable — it stays static by design (cross-campaign siblings + no document_number filter; see plan objective). Add a Pest case asserting its recordUrl is null so a future edit can't silently add a leaking drill-through.
  </action>
  <verify>
    <automated>php artisan test --filter=WidgetDrillThroughTest tests/Feature/WidgetDrillThroughTest.php</automated>
  </verify>
  <done>Jurisdiction/Rejections/ApoyosLideres rows link to the voter view page; Duplicates recordUrl asserted null; all new Pest cases pass.</done>
</task>

</tasks>

<verification>
- `php artisan test --filter=WidgetDrillThroughTest` — all pre-existing and new cases green (leader, coordinator-team, Total, Confirmados, polling place, jurisdiction/rejections/apoyos view links, duplicates null).
- `vendor/bin/pint --dirty` clean (explicit alphabetical `use` imports, curly braces, no aliases/inline namespace paths per CLAUDE.md).
- Spot-check: `grep -rn "recordUrl\|VoterResource::getUrl" app/Filament/Widgets/TerritorialOwnershipTable.php app/Filament/Widgets/TopCoordinatorsTable.php app/Filament/Widgets/CampaignStatsOverview.php app/Filament/Widgets/TopPollingPlacesTable.php app/Filament/Widgets/JurisdictionReportTable.php app/Filament/Widgets/RejectionsReportTable.php app/Filament/Widgets/ApoyosLideresCoordinadoresTable.php` shows each has its drill-through; DuplicatesReportTable shows none.
- Manual browser check (per user's browser-verify-before-prod rule, do before any deploy): on the /reports panel and /admin dashboard, click a leader, a coordinator, the Total de Apoyos stat, a polling place, and a jurisdiction/rejection row — each lands on the correct filtered list or voter detail, still scoped to the active campaign, with no write actions exposed for reports_viewer.
</verification>

<success_criteria>
- Every aggregate widget row (leader, coordinator, polling place) and the Total/Confirmados stats drill through to the correct filtered Voters list.
- Every per-voter report row (jurisdiction, rejections, apoyos-plana) drills through to that voter's detail view.
- DuplicatesReportTable remains intentionally non-clickable, asserted by test.
- All drill-throughs resolve within the current panel, respect campaign isolation, and never bypass VoterPolicy.
- New Pest cases cover every added drill-through and the Duplicates skip; suite + Pint clean.
</success_criteria>

<output>
After completion, create `.planning/quick/260730-hlg-hacer-clickeables-drill-through-todos-lo/260730-hlg-SUMMARY.md`.
</output>
