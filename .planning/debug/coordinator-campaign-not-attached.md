---
status: awaiting_human_verify
trigger: "coordinator-campaign-not-attached"
created: 2026-07-28T14:13:41Z
updated: 2026-07-28T15:10:00Z
---

## Current Focus

hypothesis: CONFIRMED - CreateCoordinator::afterCreate() never attached the new record to any campaign via campaigns()->attach()/sync(). Same gap in EditCoordinator::afterSave(). CreateLeader/EditLeader propagated the bug by copying campaign ids from the coordinator (which had none).
test: fix implemented in all 4 pages + CampaignContext::resolveUnambiguousCampaignId() helper; covered by 7 new Pest tests (CoordinatorResourceCampaignTest, LeaderResourceCampaignTest), all passing; ran full related test groups (Coordinator/Leader/User Filament resources) with no regressions; pint clean.
expecting: awaiting user confirmation to (a) accept the code fix as-is, and (b) explicitly authorize running the `campaigns:backfill-orphan-memberships` Artisan command against production to attach the already-broken real coordinator + its leaders to "Alcaldía 2027".
next_action: present findings + backfill command instructions to user; do not run backfill against production without explicit go-ahead.

## Symptoms

expected: Al crear un coordinador con una campaña activa seleccionada en el selector superior (ej. "Alcaldía 2027"), el coordinador debe aparecer en el listado de Coordinadores al filtrar por esa campaña.
actual: El coordinador solo aparece cuando el selector superior está en modo "ver todas". Al filtrar por la campaña activa específica, desaparece. Los líderes creados bajo ese coordinador tienen el mismo problema.
errors: Ninguno visible en UI — es un problema silencioso de datos/scoping, no una excepción.
reproduction: Como super_admin, con una única campaña activa "Alcaldía 2027", crear un Coordinador vía el recurso Filament de Coordinadores. Cambiar el selector de campaña a "Alcaldía 2027" específicamente (fuera de "ver todas") y buscar el coordinador — no aparece. Crear un Líder bajo ese coordinador tiene el mismo resultado.
started: Reportado 2026-07-28. Probablemente presente desde que se implementó el recurso Coordinators (no hay evidencia de que alguna vez funcionara distinto).

## Eliminated

(none - root cause confirmed on first pass, consistent with prior read-only investigation)

## Evidence

- timestamp: 2026-07-28T14:13Z
  checked: app/Models/User.php campaigns() relation
  found: BelongsToMany via pivot `campaign_user` (model App\Models\CampaignUser), no direct campaign_id column on users table.
  implication: membership is entirely pivot-driven; nothing auto-populates it on user creation.

- timestamp: 2026-07-28T14:13Z
  checked: app/Models/Scopes/CampaignMembershipScope.php + HasCampaignMembershipScope trait
  found: global scope on User applies `whereHas('campaigns', id = CampaignContext::currentCampaignId())` whenever a specific campaign is active (not null). No-op when currentCampaignId() is null (view-all mode).
  implication: any user with zero campaign_user rows is invisible under any specific-campaign filter, visible only in view-all mode. Matches reported symptom exactly.

- timestamp: 2026-07-28T14:14Z
  checked: app/Filament/Resources/Coordinators/Pages/CreateCoordinator.php afterCreate()
  found: only calls assignRole(COORDINATOR) (+ LEADER/self-coordinator if also_leader toggle). No campaigns()->attach()/sync() call anywhere.
  implication: root cause confirmed — every coordinator created via this page gets zero campaign_user rows regardless of active campaign context.

- timestamp: 2026-07-28T14:14Z
  checked: app/Filament/Resources/Coordinators/Pages/EditCoordinator.php afterSave()
  found: only re-assigns COORDINATOR role if missing. Never touches campaigns() relation, never self-heals a coordinator with zero campaign attachments.
  implication: editing a broken coordinator does not fix it; same gap, different entry point.

- timestamp: 2026-07-28T14:14Z
  checked: app/Filament/Resources/Coordinators/Schemas/CoordinatorForm.php
  found: no campaign-related field at all (unlike UserForm's `campaignAssignments` Repeater).
  implication: confirms this resource was built with the (incorrect) assumption that campaign membership doesn't need explicit handling for coordinators.

- timestamp: 2026-07-28T14:15Z
  checked: app/Filament/Resources/Leaders/Pages/CreateLeader.php afterCreate()
  found: computes `$campaignIds` by reading `$coordinator->campaigns()->pluck('campaigns.id')` and does `$this->record->campaigns()->sync($campaignIds)`. If coordinator has 0 campaigns (the bug), leader gets `sync([])` → 0 campaigns too.
  implication: bug propagates one level down to every leader created under a broken coordinator, exactly as reported.

- timestamp: 2026-07-28T14:16Z
  checked: app/Services/CampaignContext.php currentCampaignId()
  found: for super_admin, if `allowsAllCampaigns()` (view-all mode) returns null immediately; otherwise resolves session campaign id or falls back to first active campaign. For non-super-admin, resolves from user's own campaigns() or session.
  implication: in the reported repro (single active campaign selected, not view-all), currentCampaignId() reliably returns the campaign id — so the fix has a clear, unambiguous value to attach on create. The only ambiguous case is explicit "view all" mode with no session campaign, which needs a fallback decision.

- timestamp: 2026-07-28T14:17Z
  checked: database/migrations/2025_11_03_125701_create_campaign_user_table.php + app/Models/CampaignUser.php
  found: pivot columns are `campaign_id`, `user_id`, `role_id` (nullable, FK to roles), `assigned_at` (nullable), `assigned_by` (nullable, FK users). Unique constraint on (campaign_id, user_id).
  implication: attach() calls should pass role_id/assigned_at/assigned_by for data completeness, matching the pattern used by UserForm's campaignAssignments repeater.

- timestamp: 2026-07-28T14:18Z
  checked: app/Filament/Resources/Users/Schemas/UserForm.php (Repeater campaignAssignments) + CreateUser/EditUser pages
  found: Users resource has a full manual Repeater for selecting campaign + role_id per assignment, saved automatically via `relationship('campaignAssignments')` on the HasMany campaignAssignments() (CampaignUser rows). Coordinators/Leaders resources have no equivalent UI.
  implication: reference pattern exists but is heavier than needed here — Coordinators/Leaders are meant to auto-attach to the currently active campaign (mirrors how territorial fields like municipality_id are auto-derived from CampaignContext in CoordinatorForm already).

- timestamp: 2026-07-28T14:19Z
  checked: vendor/filament/filament/src/Resources/Pages/CreateRecord.php create() lifecycle
  found: wraps whole flow in a DB transaction; `mutateFormDataBeforeCreate()` runs before `beforeCreate()`/record persistence; throwing `Filament\Support\Exceptions\Halt` inside any hook before commit safely rolls back the transaction and stops without an unhandled exception.
  implication: safe place to block creation entirely when no active campaign is selected (view-all mode), using a Notification + Halt rather than throwing OperationalDenialException (which renders a raw 422 error page, worse UX for a Livewire form flow).

- timestamp: 2026-07-28T14:20Z
  checked: no existing Pest tests reference CreateCoordinator/EditCoordinator/CoordinatorResource; tests/Feature/Filament/UserResourceTest.php is the closest analog and tests/Feature/CoordinatorLeaderRelationshipTest.php covers coordinator/leader relation but not campaign attachment.
  implication: need new test coverage from scratch for this resource; follow UserResourceTest's actingAs/CampaignContext::setCampaignId conventions.

- timestamp: 2026-07-28T14:45Z
  checked: reachability of a zero-campaign coordinator/leader via the Edit route (CoordinatorResource::getEloquentQuery()/LeaderResource::getEloquentQuery() apply role() + the User model's own CampaignMembershipScope global scope)
  found: a record with zero campaign_user rows is only resolvable via the Edit route while browsing in "view all" mode (CampaignContext::currentCampaignId() === null) — under any specific-campaign filter the route itself 404s (ModelNotFoundException), since the scope applies to route-model binding too, not just the list table.
  implication: a plain "attach if currentCampaignId() present" self-heal in EditCoordinator/EditLeader is unreachable for the exact reported scenario (record is only visible in view-all mode, where currentCampaignId() is null for super_admin). Added CampaignContext::resolveUnambiguousCampaignId() — falls back to the single ACTIVE campaign system-wide when in view-all mode — so self-heal actually fires for the real-world single-active-campaign scenario described by the user.

- timestamp: 2026-07-28T14:50Z
  checked: LeaderForm's coordinator_user_id Select (::relationship() with modifyQueryUsing scoped by role + implicit User global scope)
  found: submitting a coordinator_user_id value that isn't visible under the current campaign scope produces a Filament "exists" validation error on that field (confirmed via a failing test attempt) — an additional integrity guard, separate from the reported bug, that already prevents assigning a leader to an out-of-scope coordinator.
  implication: no additional fix needed here; documented as supporting evidence that the relationship-select layer is already consistent once the underlying attachment bug is fixed.

- timestamp: 2026-07-28T15:00Z
  checked: full Pest suite baseline comparison (git stash before/after) — `php artisan test` on unmodified main: 15 failed / 1034 passed; same run with this fix applied: 10 failed / 1039 passed.
  found: the pre-existing failures (TopCoordinatorsTableTest, TopPollingPlacesTableTest, UserResourceTest::can update user campaigns, VoterResourceTest) are caused by CampaignContext's static overrides ($overrideCampaignId/$overrideMode) leaking across test files within the same PHP process — a latent, order-dependent test-isolation issue that exists on main independent of this fix (reproduced by running tests/Feature/Filament/UserResourceTest.php + TopCoordinatorsTableTest.php together on unmodified main).
  implication: out of scope for this hotfix; not introduced by this change (failure count is actually lower with the fix applied, 10 vs 15, due to nondeterministic Faker/test ordering). Flagged for a separate follow-up ticket, not fixed here to avoid scope creep on an urgent hotfix.

## Resolution

root_cause: >
  CreateCoordinator::afterCreate() (and EditCoordinator::afterSave()) never call
  $record->campaigns()->attach()/sync(), so newly created Coordinators end up with
  zero rows in the campaign_user pivot table regardless of which campaign is active
  in the top switcher. The global scope CampaignMembershipScope (applied to the User
  model) filters any listing by "whereHas('campaigns', id = active campaign)" whenever
  a specific campaign is selected, so a coordinator with no campaign_user rows is
  invisible under any specific-campaign filter and only visible in "view all" mode.
  CreateLeader::afterCreate() then propagates the same emptiness to Leaders created
  under such a coordinator, because it derives the leader's campaigns purely from the
  coordinator's (empty) campaigns() list.

fix: >
  1. Added CampaignContext::resolveUnambiguousCampaignId(): returns currentCampaignId()
     when a specific campaign is active; otherwise, while in "view all" mode, falls back
     to the single ACTIVE campaign system-wide if there's exactly one (returns null when
     ambiguous - zero or 2+ active campaigns).
  2. CreateCoordinator: blocks creation (Halt + Notification) if resolveUnambiguousCampaignId()
     is null — forces explicit campaign selection when genuinely ambiguous. On successful
     create, attaches the record to the resolved campaign with role_id = coordinator role,
     assigned_at = now(), assigned_by = auth()->id().
  3. EditCoordinator: self-heals — if resolveUnambiguousCampaignId() resolves and the record
     isn't attached to it yet, attaches it (same pivot fields). Never detaches existing
     attachments, only fills the gap. This is what makes the already-broken production
     coordinator fixable simply by opening it in "view all" mode and hitting Save (since
     there's exactly one active campaign in production today).
  4. CreateLeader / EditLeader: defensive fallback — if the coordinator has zero campaigns
     (stale data from before this fix), fall back to resolveUnambiguousCampaignId() instead
     of syncing an empty array.
  5. Added 7 Pest tests across CoordinatorResourceCampaignTest.php and
     LeaderResourceCampaignTest.php covering: create attaches active campaign, create is
     blocked when ambiguous, list visibility under campaign filter, edit self-heals a
     zero-campaign record (view-all + single active campaign), and the leader-side
     inherit/fallback behavior for both create and edit.
  6. Backfill: prepared (not executed) `campaigns:backfill-orphan-memberships {campaign}
     [--apply]` Artisan command to attach any existing orphaned coordinators/leaders to a
     given campaign. Dry-run by default (shows a table of affected users), requires
     --apply + interactive confirmation to persist. Requires explicit user go-ahead before
     running against production.

files_changed:
  - app/Services/CampaignContext.php
  - app/Filament/Resources/Coordinators/Pages/CreateCoordinator.php
  - app/Filament/Resources/Coordinators/Pages/EditCoordinator.php
  - app/Filament/Resources/Leaders/Pages/CreateLeader.php
  - app/Filament/Resources/Leaders/Pages/EditLeader.php
  - tests/Feature/Filament/CoordinatorResourceCampaignTest.php (new)
  - tests/Feature/Filament/LeaderResourceCampaignTest.php (new)
  - app/Console/Commands/BackfillCoordinatorCampaign.php (new, not yet run against production)

verification: >
  All 7 new tests pass in isolation and combined with the full Coordinator/Leader/User
  Filament test groups (37 tests, 147 assertions, no regressions). vendor/bin/pint --dirty
  clean. Full suite baseline comparison (git stash) confirms this fix does not introduce
  new failures - a pre-existing CampaignContext static-state test-isolation issue affects
  4 unrelated widget/table tests on both main and this branch (15 failed on main vs 10
  failed with this fix, out of ~1044 tests), tracked separately, not caused by this change.
  Awaiting human confirmation before running the backfill command against production and
  before archiving this session.
