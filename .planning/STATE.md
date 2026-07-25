---
gsd_state_version: 1.0
milestone: v1.1
milestone_name: Consulta de Puesto de Votación Resiliente
status: Ready to plan
stopped_at: Completed 08-03-PLAN.md
last_updated: "2026-07-25T12:36:15.053Z"
progress:
  total_phases: 6
  completed_phases: 3
  total_plans: 5
  completed_plans: 5
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-07-24)

**Core value:** Campaign teams can run critical voter and field operations from one place with trustworthy, campaign-safe data and clear operational traceability.
**Current focus:** Phase 08 — resilient-pollingplaceresolver-service

## Current Position

Phase: 9
Plan: Not started

## v1.1 Phase Map

| Phase | Requirements | Depends on |
|-------|--------------|------------|
| 6. National Census Snapshot Import | CENSO-02, CENSO-03 | none (parallel w/ 7) |
| 7. Source-Flag Schema & Audit Trail | SRC-03 | none (parallel w/ 6) |
| 8. Resilient PollingPlaceResolver Service | CENSO-01, SRC-02, LIVE-01, LIVE-03 | 6 + 7 |
| 9. Live-Source Feasibility Spike | LIVE-02 | none (non-blocking; before 11) |
| 10. Operator Provenance & Fallback Controls | SRC-01, SRC-04, SRC-05 | 8 |
| 11. Scheduled Reconciliation Job | RECON-01..06 | 7 + 8 (informed by 9) |

## Performance Metrics

Reset for v1.1. Historical v1.0 velocity data archived in `.planning/milestones/v1.0-ROADMAP.md` and phase SUMMARY.md files under `.planning/phases/`.

| Phase-Plan | Duration | Tasks | Files |
|------------|----------|-------|-------|
| 08-01 | 15min | 2 | 8 |
| Phase 08 P02 | 12min | 3 tasks | 3 files |
| Phase 08 P03 | 12min | 2 tasks | 3 files |

## Accumulated Context

### Decisions

Full v1.0 decision log archived in `.planning/PROJECT.md` Key Decisions table and `.planning/milestones/v1.0-ROADMAP.md`. Cleared here for the next milestone.

v1.1 roadmap decisions:

- CENSO-01 (resolve-from-snapshot-on-outage) mapped to the resolver phase (8), not the import phase — the observable "resolves from snapshot when live is down" only becomes TRUE once the cascade exists.
- SRC-01 (visibly shows source) mapped to the operator-controls phase (10), not the resolver — the criterion becomes TRUE when the badge renders to a human.
- Live-source spike (LIVE-02) is a standalone non-blocking Phase 9 so the deterministic snapshot/flag/resolver/reconcile core is never gated on the captcha unknown.

Phase 06 Plan 01 decisions:

- Divipol codes on `national_census_records` stored as `unsignedSmallInteger` (not `string`) to match `polling_places`' join-key column types exactly, per the plan's explicit refinement over ARCHITECTURE.md's original suggestion.
- ISO-8859-1 -> UTF-8 conversion done per-line with `mb_convert_encoding` during the streaming read (simpler than a one-time `iconv` pre-pass).
- Fixture CSV (`tests/fixtures/census/national-sample.csv`) marked `-text` in `.gitattributes` so the repo's `text=auto eol=lf` rule never strips its required CRLF terminators on checkout.

Phase 07 Plan 01 decisions:

- No default value set on `voters.polling_place_source` — no existing voter has ever had a source, so nullable with no backfill is correct (per 07-CONTEXT.md discretion).
- `resolved_by` on `polling_place_resolutions` is nullable + `nullOnDelete` (D-05) — the one place this schema diverges from `ValidationHistory.validated_by`, tolerating Phase 11's headless reconciliation writes.
- `polling_place_id` + `table_number` on `polling_place_resolutions` are value snapshots (nullable, `nullOnDelete`) capturing which specific place a resolution produced, not just the source label (D-06).
- `resolved_via` is a plain required string (D-08), not a backed enum, matching `ValidationHistory.validation_type`'s precedent — new values addable without a migration.
- [Phase 08]: isReachable() uses withoutRedirecting() before checking redirect() — Guzzle's default redirect-following would otherwise chase a self-referential Location header into a false negative (auto-fixed, Rule 1)
- [Phase 08]: persist() treats voter===null as a pure pass-through (no persistence, no audit row) — supports Filament CreateVoter flow before first save
- [Phase 08]: resolveOrCreatePollingPlace() duplicates HasRegistraduriaPolling's firstOrCreate enrichment for the headless resolveAutomated() path — Plan 08-03 will refactor the interactive trait to call the resolver instead of duplicating a third time
- [Phase 08]: forceRefreshFromRegistraduria() still respects REGISTRADURIA_LIVE_ENABLED even though D-10 exempts it from the no-downgrade guard - if live is globally disabled there is nothing to force-refresh to

### Blockers/Concerns

- **`gsd-tools.cjs` root-resolution bug when a git worktree owns its own `.planning/`:** `findProjectRoot()` (in `lib/core.cjs`) walks up from `cwd` and, upon finding an *ancestor* directory that also has `.planning/` plus a `.git` heuristic match, redirects `cwd` there — even when the original `cwd` already has its own valid, independent `.planning/`. In this session's worktree (`worktree-agent-ae9f012d50fef4e54`, which owns its own `.planning/`), every `gsd-tools state|roadmap|requirements` subcommand silently redirected reads/writes to the **main checkout's** `.planning/` instead of the worktree's. This was caught before real damage (the only accidental write to the main repo's `STATE.md` was reverted), but it means **`gsd-tools` CLI commands cannot be trusted to target a worktree's own `.planning/` in this repo layout** — STATE.md/ROADMAP.md/REQUIREMENTS.md updates for Phase 06 Plan 01 were made by hand-editing the worktree copies directly instead. Worth a fix in `gsd-tools` (short-circuit `findProjectRoot` when `startDir` itself already has `.planning/`) or at minimum a documented workaround for future phases executed in this worktree.
- **Same `gsd-tools` root-resolution bug recurred during Phase 07 Plan 01 execution** (worktree `agent-ae0adbb8ac28629ba`, also stale — see below): `state advance-plan`/`update-progress` partially wrote to this worktree's own STATE.md (with an incorrect recalculation, e.g. `total_plans` dropped from 2 to 1), while `state record-metric`/`record-session` silently redirected to and modified the **main checkout's** `.planning/STATE.md` instead — mixed per-command routing, not consistently one or the other. The main checkout's accidental write was caught and reverted to its exact prior (dirty, uncommitted) content before this session ended. All STATE.md/ROADMAP.md/REQUIREMENTS.md updates for this plan were redone by hand-editing the worktree copies directly. This bug is confirmed to still be present and should be fixed in `gsd-tools` before the next phase relies on its CLI for state mutation inside a worktree.
- **This worktree (`agent-ae0adbb8ac28629ba`) was also stale at session start**, same class of issue as Phase 06: checked out at commit `78c1f69` (pre-dating Phase 6/7 entirely), missing `vendor/`, `.env`, and the `.planning/phases/07-*` directory. Resolved the same way: `git merge --ff-only` to main's HEAD (a fast-forward descendant), then `composer install` and copying `.env` from the main checkout. Worth checking whether stale/locked worktrees can be refreshed automatically before an execute-phase agent is spawned into one.

- **System-actor decision for reconciliation's audit trail (RECON-03) must be made before Phase 11 is planned in detail** — either a seeded `system`/bot user passed as the `validated_by`-equivalent, or a nullable FK + `resolution_type='auto_reconciliation'`. Flagged in research (Pitfall #3). Not yet decided.
- **Interactive cascade ordering (live-first vs cost-last):** the requirement says "live first, fall back to snapshot," but the existing interactive path is deliberately cost-*last* (live = paid). Confirm interactive ordering with the client during Phase 8 planning; reconciliation (Phase 11) is unambiguously live-first.
- **`local_infile` availability:** if `LOAD DATA LOCAL INFILE` can't be enabled in the target env, Phase 6 falls back to the streaming `LazyCollection` + chunked `upsert()` path (both documented in ARCHITECTURE.md).
- Production `sigma-registraduria` container on `korserver` (Dokploy) still runs OLD hardcoded-2captcha-key code — needs a Dokploy redeploy to pick up commit `ac1dd5a` + the `TWO_CAPTCHA_KEY` env var. Not urgent (not in production use).
- **Registraduría election-lookup endpoints currently decommissioned** (found 2026-07-23): both `eleccionescolombia.registraduria.gov.co` and `apiweb-eleccionescolombia.infovotantes.com` have no DNS record — likely temporary election-season teardown. Existing live lookup buttons fail until reactivated. Directly motivates the Phase 6-8 snapshot fallback and the Phase 9 wsp spike.
- Intermittent flake in `Tests/Feature/Filament/UserResourceTest > can update user campaigns` (~1/3 of full-suite runs); pre-existing, logged in 04.1 deferred-items.md.
- Pre-existing test files (`IsElectionDayMiddlewareTest`, `Filament/UserResourceTest`, `tests/E2E/ChromeDevTools/*`) call `CampaignContext::setCampaignId()` without resetting the static override — latent test-pollution risk. Found/scoped during Phase 05.1 Plan 01.

### Pending Todos

Tracked in Blockers/Concerns above.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 260428-o0k | Create public API GET /api/cumpleanos birthday endpoint | 2026-04-28 | 1e71eeb | [260428-o0k-create-a-public-api-endpoint-get-api-cum](.planning/quick/260428-o0k-create-a-public-api-endpoint-get-api-cum/) |
| 260508-w9c | Integrar consulta de puesto de votación (Registraduría) | 2026-05-08 | 029345a | [260508-w9c-integrar-consulta-de-puesto-de-votacion-](.planning/quick/260508-w9c-integrar-consulta-de-puesto-de-votacion-/) |
| 260508-wze | Registraduría headless proxy screenshots modal SIGMA VPS | 2026-05-09 | 030e091 | [260508-wze-registraduria-headless-proxy-screenshots](.planning/quick/260508-wze-registraduria-headless-proxy-screenshots/) |
| 260514-mng | Birthday webhook automation — BirthdayWebhookService + DispatchBirthdayWebhooks command | 2026-05-14 | 12262d1 | [260514-mng-implementar-automatizaci-n-de-webhook-de](.planning/quick/260514-mng-implementar-automatizaci-n-de-webhook-de/) |
| 260723-f26 | Botón secundario "Actualizar datos desde Registraduría" + intento de E2E | 2026-07-23 | bb45b56 | [260723-f26-agregar-boton-secundario-de-actualizar-d](.planning/quick/260723-f26-agregar-boton-secundario-de-actualizar-d/) |

## Session Continuity

Last session: 2026-07-25T12:32:33.723Z
Stopped at: Completed 08-03-PLAN.md
Resume file: None
