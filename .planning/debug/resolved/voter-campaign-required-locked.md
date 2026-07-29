---
status: resolved
trigger: "voter-campaign-required-locked"
created: 2026-07-28T00:00:00Z
updated: 2026-07-28T18:15:00Z
---

## Current Focus

hypothesis: CONFIRMED and FIXED. Root cause exactly as prior read-only investigation found. Fix implemented: campaign_id's default()/disabled() now both go through a shared resolveSuperAdminCampaignId() priority chain (session active campaign -> selected leader's coordinator's single campaign -> single ACTIVE campaign system-wide), and registered_by got ->live() + afterStateUpdated() to derive/re-set campaign_id reactively when a Líder is picked after mount. disabled() now reflects "was a value actually resolved", not raw Campaign::count().
test: 3 new Pest tests (tests/Feature/Filament/VoterCampaignResolutionTest.php) covering the exact broken scenario (single active campaign, view-all mode, no leader picked yet), the leader-derives-campaign scenario, and the stays-enabled-when-ambiguous scenario. All pass. Full VoterResourceTest.php (33), VoterRegistraduriaRefreshTest.php (20), ApoyoDuplicateSequenceTest.php (11), CoordinatorResourceCampaignTest.php (4), LeaderResourceCampaignTest.php (3), and the 3 Leader/RegisterVoter Volt suites (15) all still green - no regressions. Pint clean.
expecting: human confirms in real production UI (Aldemar and/or sigma-betha-app) that creating a Voter as super_admin in "view all" mode now either auto-resolves+locks Campaña or leaves it enabled for manual pick, and the record saves successfully.
next_action: >
  Fix committed to main (commit 3839129, "fix(voters): unlock super_admin
  campaign field when unresolved"). Owner approved deploying to both
  production instances (Aldemar - sigma-app-kb2mdl - and sigma-betha-app -
  sigma-betha-app-pw6k9q). Awaiting orchestrator to deploy via SSH (same
  pattern as today's coordinator-campaign-not-attached and
  NeighborhoodsImport hotfixes) and verify in the real production UI
  before this session is archived to resolved/.

## Symptoms

expected: Al crear un Apoyo como super_admin, el campo Campaña debe permitir guardarse correctamente — ya sea porque se resuelve automáticamente (desde la campaña activa en sesión, o derivada del coordinador del líder seleccionado, o porque hay una única campaña en el sistema) o porque el campo queda habilitado para elegirla manualmente si no puede resolverse sin ambigüedad.
actual: El formulario falla la validación con "el campo Campaña es requerido", pero el Select de Campaña aparece deshabilitado (no se puede hacer click ni elegir un valor), dejando al usuario sin forma de completar el formulario.
errors: Ninguna excepción de servidor — es un error de validación de formulario (Filament), no aparece en storage/logs/laravel.log (confirmado revisando logs recientes de ambas instancias de producción, sin coincidencias de "campaign"/"campaña" ni relacionadas a CreateVoter).
reproduction: Login como super_admin en cualquiera de las dos instancias de producción (sigma-app-kb2mdl "Aldemar" o sigma-betha-app-pw6k9q "betha-app"), con el selector de campaña superior en modo "ver todas" (sin campaña activa específica seleccionada), ir a crear un Apoyo (Voter) vía el panel Filament (/admin/voters/create), y guardar.
started: Reportado hoy. Ambas instancias de producción tienen actualmente exactamente 1 campaña activa cada una.

## Eliminated

## Evidence

- timestamp: 2026-07-28T00:00Z
  checked: app/Filament/Resources/Voters/Schemas/VoterForm.php lines 111-169 (campaign_id Select)
  found: |
    ->default(fn () => CampaignContext::currentCampaignId()) - returns null for super_admin in "view all" mode.
    ->disabled(fn (): bool => Campaign::count() <= 1) - disables based on TOTAL campaign count in system, unrelated
    to whether a value was actually resolved. Both production instances have exactly 1 campaign total -> always disabled.
    ->required() still active regardless of disabled state.
    ->dehydrated() forced true (correctly avoids the Filament disabled-excludes-from-dehydration bug documented in
    barrio-select-stuck-disabled.md), but this only matters once a value CAN be set - here default() never resolves one.
  implication: Confirms prior investigation. In "view all" mode with <=1 total campaigns in system, field is null,
    disabled, and required simultaneously -> validation blocks with no way for the user to fix it via UI.

- timestamp: 2026-07-28T00:01Z
  checked: app/Services/CampaignContext.php currentCampaignId() and resolveUnambiguousCampaignId()
  found: |
    currentCampaignId() returns null for super_admin when allowsAllCampaigns() (view-all mode) is true, regardless
    of how many campaigns exist. resolveUnambiguousCampaignId() (added in coordinator-campaign-not-attached fix)
    falls back to currentCampaignId(), then to the single ACTIVE campaign system-wide if exactly one exists,
    else null.
  implication: resolveUnambiguousCampaignId() is the correct existing primitive for fallback (c) in fix
    requirements, already battle-tested by the coordinator fix from earlier today.

- timestamp: 2026-07-28T00:10Z
  checked: app/Filament/Resources/Voters/Pages/CreateVoter.php
  found: mutateFormDataBeforeCreate only auto-fills registered_by for LEADER role. No campaign_id handling
    at the page level. app/Models/Concerns/HasCampaignContext.php (used by Voter) enforces campaign_id =
    CampaignContext::currentCampaignId() on creating() for non-super-admins, but explicitly no-ops for
    super_admin when currentCampaignId() is null - meaning the model layer relies entirely on the form
    field's dehydrated value for super_admin in view-all mode, confirming the form-layer fix is the correct
    and only place to fix this (no model-layer safety net exists or should be added for super_admin).
  implication: Fix must live in VoterForm.php's campaign_id field logic; no other layer intervenes for
    super_admin.

- timestamp: 2026-07-28T00:12Z
  checked: resources/views/livewire/leader/register-voter.blade.php (operational Volt flow) lines 50-264
  found: campaign_id is resolved from `auth()->user()->campaigns()->first()` (the logged-in Líder's own
    campaign membership), entirely independent of CampaignContext/super_admin logic. Only leaders use this
    flow (route-guarded), so this path was never affected by the reported bug.
  implication: No changes needed in the Volt register-voter flow per fix_requirements item 4 - confirmed,
    not just assumed.

- timestamp: 2026-07-28T00:15Z
  checked: vendor/filament/schemas/src/Concerns/InteractsWithSchemas.php fillFormDataForTesting()/
    updatedInteractsWithSchemas()
  found: Livewire::test(...)->fillForm() iterates each key via Arr::dot() and calls
    updatingInteractsWithSchemas()/updatedInteractsWithSchemas() per key, which DOES fire each field's
    afterStateUpdated() hook (unless explicitly disabled for testing) - confirms a reactive
    registered_by->afterStateUpdated() that $set()s campaign_id is both realistic (matches live browser
    behavior) and testable via fillForm(['registered_by' => ...]) without needing to pass campaign_id.
  implication: Safe to implement fix (b) as a live reactive hook on registered_by rather than only inside
    campaign_id's one-shot default().

## Resolution

root_cause: >
  app/Filament/Resources/Voters/Schemas/VoterForm.php's campaign_id Select combined three conditions that
  are individually reasonable but jointly broken for super_admin in "view all" mode with <=1 total campaign
  in the system (both production instances today): ->default() resolved to null (CampaignContext::
  currentCampaignId() returns null whenever a super_admin is in "view all" mode, regardless of how many
  campaigns exist), ->disabled() was true (Campaign::count() <= 1 - based on the RAW total campaign count
  system-wide, not on whether a value could actually be resolved), and ->required() was still enforced.
  The field ended up null, locked (un-clickable), and required simultaneously, so validation failed with no
  way for the user to fix it from the UI. Same class of bug (a disabled()/required()/default() condition
  mismatch) fixed twice already today for coordinator-campaign-not-attached and barrio-select-stuck-disabled,
  same file.

fix: >
  In app/Filament/Resources/Voters/Schemas/VoterForm.php:
  1. Added resolveSuperAdminCampaignId(?int $registeredById): ?int - a single source of truth used by both
     ->default() and ->disabled() on campaign_id, resolving in priority order: (a) CampaignContext::
     currentCampaignId() (session-active campaign, unchanged/untouched), (b) resolveCampaignIdFromLeader()
     - the selected Líder's Coordinador's App\Models\User::campaigns() membership, when the coordinator
     belongs to exactly one campaign, (c) CampaignContext::resolveUnambiguousCampaignId() - the single
     ACTIVE campaign system-wide (the primitive added earlier today for coordinator-campaign-not-attached).
  2. ->disabled() now reads `filled(self::resolveSuperAdminCampaignId($get('registered_by')))` instead of
     `Campaign::count() <= 1` - the field locks only when a value was actually, unambiguously resolved, and
     stays enabled for manual selection whenever it wasn't (fix_requirements 2d).
  3. Extracted the existing "_state" mirror + campaign-fixed department/municipality side effects (from
     campaign_id's own afterStateUpdated) into a shared applyCampaignDerivedState() helper.
  4. Added ->live() + ->afterStateUpdated() on registered_by: when a super_admin picks a Líder, it re-runs
     resolveSuperAdminCampaignId($state) and, if it now resolves, $set()s campaign_id and re-applies
     applyCampaignDerivedState() - this is what makes fix_requirements 2b (derive from the selected leader's
     coordinator) work reactively after mount, not just at initial default().
  Verified via vendor/filament/schemas InteractsWithSchemas that Filament's own disabled()-excludes-
  dehydration/validation footgun (fixed twice already today in this file/project) does NOT reappear here:
  campaign_id already had ->dehydrated() forced unconditionally before this fix and that call was left
  untouched.
  Confirmed the Volt leader-facing register-voter flow (resources/views/livewire/leader/register-voter.blade.php)
  resolves campaign_id independently from the logged-in Líder's own campaigns() and needed no changes.

verification: >
  3 new Pest tests in tests/Feature/Filament/VoterCampaignResolutionTest.php:
  (a) single active campaign + view-all mode + no leader picked yet -> campaign_id auto-resolves to that
      campaign and is locked (assertFormFieldDisabled), voter saves successfully - the exact originally
      broken production scenario, now fixed;
  (b) two active campaigns (ambiguous) + view-all mode -> campaign_id starts null/enabled, then resolves to
      the selected leader's coordinator's single campaign and locks once registered_by is picked, voter
      saves successfully;
  (c) two active campaigns + no leader picked -> campaign_id stays enabled/null, user can still manually
      pick a campaign and save successfully.
  All 3 pass. Regression run: tests/Feature/Filament/VoterResourceTest.php (33/33), VoterRegistraduriaRefreshTest.php
  (20/20), ApoyoDuplicateSequenceTest.php (11/11), CoordinatorResourceCampaignTest.php (4/4),
  LeaderResourceCampaignTest.php (3/3), tests/Feature/Leader/RegisterVoter*Test.php (15/15) - all green, no
  regressions. vendor/bin/pint --dirty: PASS (2 files, no changes needed).

files_changed:
  - app/Filament/Resources/Voters/Schemas/VoterForm.php
  - tests/Feature/Filament/VoterCampaignResolutionTest.php (new)

production_deploy: >
  Commits 3839129 (fix) + ed71966 (docs) pushed to origin/main after owner approval. Dokploy
  auto-deployed to both sigma-app-kb2mdl (Aldemar) and sigma-betha-app-pw6k9q (betha-app);
  both containers confirmed healthy post-redeploy (docker ps status). No migrations required
  (form-only logic change). Verified directly in each production database via tinker:
  Campaign::where('status','active')->count() === 1 and
  CampaignContext::resolveUnambiguousCampaignId() (called with no session, mimicking a
  super_admin in "view all" mode) correctly returns campaign id 1 in both instances -
  confirming the exact previously-broken resolution path now resolves successfully in real
  production data. Full UI click-through (actually saving a Voter in the browser) was not
  performed by the orchestrator; that final confirmation is left to the project owner, who
  reported this bug while trying to register a real support (cédula 1102834619, ROSA
  CANDELARIA DIAZ GARRIDO per the identity directory) - the same session also confirmed via
  a direct Registraduría microservice call that the polling-place lookup for that cédula
  works correctly (Sincelejo, IE Santa Rosa de Lima, mesa 25), so once the owner retries
  creating that Voter it should now save successfully end-to-end.
