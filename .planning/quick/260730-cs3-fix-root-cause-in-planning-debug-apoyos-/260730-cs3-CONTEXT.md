# Quick Task 260730-cs3: Fix census validation using orphaned per-campaign table - Context

**Gathered:** 2026-07-30
**Status:** Ready for planning

<domain>
## Task Boundary

Root cause documented in `.planning/debug/apoyos-mass-rejected-census-betha.md`:
`VoterValidationService` validates Apoyos against the orphaned per-campaign `census_records`
table (45 incidental rows on sigma-betha campaign_id=1, populated one row at a time as a
side-effect of live Registraduría lookups, never via a real bulk import). This caused 148/188
voters on sigma-betha to be incorrectly marked `REJECTED_CENSUS`, and that status is a dead end
(the reconciliation job never re-checks it).

Separately, a fully-built, working automated resolution engine already exists
(`PollingPlaceResolver::resolveAutomated()` + permanent `registraduria_lookups` cache + Phase 11
scheduled reconciliation job) but was built only for polling-place resolution, never wired to the
census-validation/REJECTED_CENSUS flow.

</domain>

<decisions>
## Implementation Decisions

### Fuente de verdad para validación de censo
- `VoterValidationService`'s census check (used by "Validar contra Censo" record action,
  "Revalidar apoyos de un líder" bulk action, and `register-voter.blade.php`'s
  `documentExistsInCensus()`) stops comparing against the orphaned `census_records` table.
- It delegates instead to the SAME cascade `PollingPlaceResolver::resolveAutomated()` already
  uses: permanent `registraduria_lookups` cache (free, tier 0) → live Registraduría adapters
  (wsp/infovotantes) → national census snapshot (`national_census_records`) / national identity
  records (`national_identity_records`) as fallback. One engine for both census validation and
  polling-place resolution — do not build a third parallel path.
- Every successful live check must persist into `registraduria_lookups` (already the resolver's
  behavior) so a document is never re-queried live twice — no repeated captcha/API cost.
- `REJECTED_CENSUS` stops being a dead end: it must re-enter the same
  retry/reconciliation cycle as `PENDING_REVIEW`/`CENSUS_NOT_FOUND` (or the flow should no longer
  produce a hard `REJECTED_CENSUS` until the full cascade — cache + live + national snapshot —
  has been exhausted, not just "not in 45 rows").

### Remediación de datos (sigma-betha únicamente)
- The 148 already-misrejected voters on sigma-betha (campaign_id=1, "Alcaldía 2027") get reverted
  to `pending_review`, with a new `ValidationHistory` row documenting the correction (previous
  status, new status, note explaining this was a data-correction, not a normal validation event).
- Do NOT touch Aldemar's database/voters — sigma-betha only.

### Indicador de progreso en pantalla (no bloqueante)
- When "Revalidar apoyos de un líder" (or the equivalent revalidation trigger) is pressed, show a
  **non-blocking** status indicator on the Apoyos screen: start time, "in progress" state while
  the background job runs, and on completion show end time + summary (how many Apoyos were
  checked, how many changed status).
- Must not block the UI — user keeps using the screen while it runs in the background.
- Reuses the existing background job infrastructure (`DispatchCensusRevalidation` /
  `ValidateVoterAgainstCensus`) but needs a persisted progress record (e.g. a small table or
  cache entry keyed per revalidation run: started_at, finished_at, total, processed, changed)
  that a polling widget/banner can read without blocking the request.

### Claude's Discretion
- Fate of `CensusImporter` service and the legacy `census_records` table/model: no longer the
  source of truth for validation once this ships. Do not delete the table, model, or
  `HasRegistraduriaPolling.php`'s incidental `CensusRecord::updateOrCreate()` write (harmless,
  out of scope) — mark `CensusImporter` as unused/deprecated in scope notes only, no forced
  removal in this quick task.
- Exact UI placement/component for the progress indicator (e.g. Filament widget with
  `wire:poll`, header banner, notification-based) — pick whatever fits existing Filament v4
  patterns in this codebase with least new surface area.

</decisions>

<specifics>
## Specific Ideas

- Precedent for "permanent cache, avoid repeat live-lookup cost" already exists and must be
  reused, not reinvented: `registraduria_lookups` (quick task 260726-jao).
- Precedent for background job + attempt tracking already exists: Phase 11's reconciliation job,
  `reconciliation_attempts` counter on `Voter`.

</specifics>

<canonical_refs>
## Canonical References

- `.planning/debug/apoyos-mass-rejected-census-betha.md` — full root-cause writeup and evidence
- `app/Services/PollingPlaceResolver.php` — existing automated resolution cascade to reuse
- `app/Services/VoterValidationService.php` — current orphaned-table validation logic to replace
- `app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php` — incidental
  `CensusRecord::updateOrCreate()` write, leave alone
- `app/Jobs/DispatchCensusRevalidation.php`, `app/Jobs/ValidateVoterAgainstCensus.php` — existing
  background job pattern to extend/reuse for the progress-tracked revalidation run

</canonical_refs>
