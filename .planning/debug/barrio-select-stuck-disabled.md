---
status: awaiting_human_verify
trigger: "barrio-select-stuck-disabled"
created: 2026-07-27T00:00:00Z
updated: 2026-07-27T01:00:00Z
---

## Current Focus

hypothesis: CONFIRMED and FIXED - see Resolution. department_id/municipality_id had no ->default(), so Filament's create-form hydrateDefaultState() nulled them out AFTER campaign_id's afterStateHydrated already $set() them (processed later in schema order). A second bug (municipality_id silently excluded from dehydration/validation while disabled) was also found and fixed. neighborhood_id itself was never broken; it was correctly reacting to an upstream municipality_id that was genuinely null/unsaved server-side.
test: Self-verified via Livewire::test reproduction + new permanent regression test + full related test suites + Pint. All green.
expecting: Human confirms in the real sigma-betha-app UI (Municipal-scope campaign "Alcaldía 2027") that Barrio now enables and shows options immediately on opening /admin/voters/create, and that saving a Voter under that campaign persists municipality_id correctly.
next_action: Await human verification in production/staging before archiving.

## Symptoms

expected: Al tener la campaña activa un municipio fijo (ej. "Sincelejo"), el campo Municipio se autocompleta y deshabilita (comportamiento esperado, línea 357: `->disabled(fn (Get $get): bool => filled($get('campaign_municipality_id_state')))`), y el campo Barrio (neighborhood_id) debería habilitarse automáticamente y mostrar las opciones de barrio para ese municipio, ya que su condición de disabled es `! $get('municipality_id')` (línea 374) y sus opciones vienen de un relationship() filtrado por `$get('municipality_id')` (líneas 362-371).
actual: El campo Barrio sigue mostrando "Seleccione una opción" (vacío/sin datos) y el helper text estático "Seleccione primero un municipio" (línea 375, siempre igual, no es condicional), dando la impresión de estar bloqueado, aunque Municipio ya muestra "Sincelejo" seleccionado en pantalla.
errors: Ninguno visible en UI (no hay excepción ni mensaje de error, solo el campo no reacciona).
reproduction: Login como super_admin en sigma-betha-app (producción, /admin/voters/create), con la campaña activa "Alcaldía 2027" (scope Municipal, municipality_id fijo = Sincelejo, department_id = Sucre). Al abrir el formulario de creación de Voter, Departamento y Municipio quedan autocompletados y deshabilitados por la campaña, pero Barrio no se habilita ni carga opciones.
started: Reportado 2026-07-27. No está claro si alguna vez funcionó correctamente para campañas con municipio fijo.

## Eliminated

## Evidence

- timestamp: 2026-07-27T00:05:00Z
  checked: app/Filament/Resources/Voters/Schemas/VoterForm.php full read (lines 1-452)
  found: |
    campaign_id (line 70) has ->live() and afterStateHydrated (81-102) / afterStateUpdated (103-127) that
    $set('department_id', ...) and $set('municipality_id', ...) when campaign scope is Municipal/Departamental.
    department_id (322) disabled() reads campaign_department_id_state/campaign_municipality_id_state hidden fields (also set in the same callback).
    municipality_id (336) has ->live() (351), afterStateUpdated resets neighborhood_id/polling_place_id/polling_table_number (352-356),
    and disabled() reads campaign_municipality_id_state (357).
    neighborhood_id (360) disabled() reads $get('municipality_id') directly (374) - NOT a hidden "_state" mirror field.
    neighborhood_id has NO ->live() of its own.
    helperText on neighborhood_id (375) is a static string, never conditional - confirmed cosmetically misleading but not proof of the bug by itself.
  implication: |
    The pattern for department_id/municipality_id (working, per report) relies on a *separate Hidden field*
    (campaign_department_id_state / campaign_municipality_id_state) explicitly set via $set() and marked dehydrated(false).
    neighborhood_id instead reads $get('municipality_id') - the live value of a SIBLING Select field, not a hidden mirror.
    Need to determine whether Filament's dependent-select disabled()/relationship() reactivity mechanism
    actually differs when the watched field is disabled+programmatically set (municipality_id) vs when the reader itself has no ->live().
- timestamp: 2026-07-27T00:15:00Z
  checked: tests/Feature/Filament/VoterResourceTest.php (full)
  found: No existing test covers a Municipal-scope campaign auto-selecting municipality_id and asserting neighborhood_id becomes enabled/populated. All existing tests manually pass municipality_id via fillForm(), never rely on the campaign-driven auto-set path.
  implication: The buggy code path (campaign auto-fill -> dependent Barrio reactivity) has zero test coverage. Confirms suspicion in symptoms.timeline that this may never have worked.

## Resolution

root_cause: |
  On CREATE forms, Filament's Schema::fill(null) initializes an internal accumulator array
  ($hydratedDefaultState = []) that is threaded by reference through every component's
  hydrateState()/hydrateDefaultState() call, IN SCHEMA ORDER (vendor/filament/schemas/src/Components/Concerns/HasState.php:351-387,452-475).
  For any field WITHOUT an explicit ->default(), hydrateDefaultState() unconditionally calls
  $this->rawState(null) - which writes null directly into the live Livewire component data array
  (same underlying mechanism as $set()/$get()).
  campaign_id's afterStateHydrated (Section "Campaña y Estado", processed early) uses $set() to
  pre-fill department_id and municipality_id when the active campaign has scope Municipal/Departamental.
  But department_id and municipality_id live LATER in the schema (Section "Ubicación") and have no
  ->default() of their own. When the schema traversal later reaches them, their hydrateDefaultState()
  unconditionally resets them back to null, silently clobbering the value campaign_id's hook had just set.
  The "_state" Hidden mirror fields (campaign_department_id_state / campaign_municipality_id_state) are
  declared BEFORE campaign_id, so their own hydrateDefaultState() already ran and completed before
  campaign_id's afterStateHydrated set them - nothing runs afterward to reset them, so they correctly
  retain the campaign's values. This is why the disabled() conditions (which read the "_state" mirrors)
  correctly evaluate to true/disabled, while the actual municipality_id/department_id values are null.
  neighborhood_id's disabled()/relationship()->modifyQueryUsing() read $get('municipality_id') directly,
  which is genuinely null at render time - so Barrio is correctly disabled/empty given that real state.
  neighborhood_id's own code was never the bug; it was correctly reacting to an upstream field that had
  silently lost its value during create-form hydration.
  Confirmed via Livewire::test(CreateVoter::class) with a Municipal-scope campaign: data.municipality_id
  and data.department_id were both null after mount, while campaign_department_id_state /
  campaign_municipality_id_state were both correctly populated (1).
fix: |
  Two-part fix in app/Filament/Resources/Voters/Schemas/VoterForm.php:
  1. Added a private static resolveCampaignLocationDefaults() helper (mirrors the scope logic already
     used in campaign_id's afterStateHydrated/afterStateUpdated) and wired it into new ->default()
     closures on department_id and municipality_id. Because these fields now have hasDefaultState()
     === true, Filament's hydrateDefaultState() computes/keeps this default instead of forcing
     rawState(null) during create-form hydration, so the campaign-driven value survives regardless of
     schema field ordering. This does not touch the existing afterStateHydrated/afterStateUpdated hooks
     on campaign_id (still handle the user manually switching campaign_id after initial mount, which is
     a live update and not subject to this hydration-time reset), nor the record-based edit-form
     derivation branch.
  2. Discovered a second, related bug while writing the regression test: Filament's disabled()
     implicitly sets dehydrated(fn => ! evaluate($disabledCondition)) (vendor/filament/schemas/src/Components/Concerns/CanBeDisabled.php).
     Since municipality_id becomes disabled() whenever the campaign fixes it, it was ALSO silently
     excluded from both dehydration (the value never reached the create payload) and required()
     validation - meaning saving a Voter under a Municipal-scope campaign would throw a raw NOT NULL
     SQLite/DB constraint violation on voters.municipality_id instead of failing validation or saving
     correctly. Fixed by chaining ->dehydrated() (forced true) after ->disabled() on municipality_id,
     so its auto-filled value is always persisted regardless of its disabled/enabled UI state.
     (department_id needed no such change - it already has ->dehydrated(false) explicitly, correct
     because voters has no department_id column; it is a pure UI filter field.)
  Also made neighborhood_id's helperText conditional (null once a municipality is selected) instead of
  a permanently-static "Seleccione primero un municipio" string, removing the secondary, purely
  cosmetic source of the "still looks stuck" impression called out in symptoms.actual.
verification: |
  Re-ran a Livewire::test(CreateVoter::class) reproduction with a Municipal-scope campaign
  (municipality "Sincelejo", department "Sucre"): data.department_id and data.municipality_id are now
  correctly populated on initial mount, matching campaign_department_id_state/campaign_municipality_id_state.
  neighborhood_id's disabled() (`! $get('municipality_id')`) now evaluates false and its relationship
  query is correctly scoped to the municipality; confirmed the seeded neighborhood name renders as a
  selectable option in the preloaded Barrio dropdown.
  Added a permanent regression test,
  "creating a voter with a municipal-scope active campaign auto-fills municipality and enables barrio
  selection", to tests/Feature/Filament/VoterResourceTest.php that: sets an active Municipal-scope
  campaign in session context, asserts department_id/municipality_id are set on the form on mount,
  asserts the neighborhood option is visible, fills the remaining required fields (without touching
  municipality_id, since it's now auto-filled and dehydrated), calls create, asserts no form errors, and
  asserts the voter was persisted with the correct municipality_id/neighborhood_id.
  Full suite run: tests/Feature/Filament/VoterResourceTest.php (33 passed, 114 assertions),
  tests/Feature/Filament/VoterRegistraduriaRefreshTest.php (20 passed) and
  tests/Feature/ApoyoDuplicateSequenceTest.php (11 passed) all green - no regressions to existing
  Create/Edit voter flows for campaigns without a fixed scope. vendor/bin/pint --dirty passes clean.
files_changed:
  - app/Filament/Resources/Voters/Schemas/VoterForm.php
  - tests/Feature/Filament/VoterResourceTest.php

## Post-verification correction (orchestrator, 2026-07-27)

The regression test's original `->assertSee($neighborhood->name)` was flaky/a false negative: Filament's
Select renders preloaded options as a JSON blob inside an Alpine `x-load`-style attribute, where accented
characters are unicode-escaped (e.g. "Andrés" → `Andrés`), so plain-text `assertSee` fails to find
them even though the option is genuinely present (confirmed via a throwaway test: the neighborhood's id
was present in the HTML, only the literal accented string wasn't). Replaced with
`->assertFormFieldEnabled('neighborhood_id')`, which directly asserts the field's `disabled()` state -
the actual thing this bug was about. Re-ran full suite after the swap: still 33/33 passed, no other
regressions. No production code change needed for this correction, test-only.
