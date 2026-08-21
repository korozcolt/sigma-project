# Milestones

## v1.3 Visualización de Datos MonoCharts (Shipped: 2026-08-21)

**Phases completed:** 5 phases, 21 plans, 50 tasks

**Key accomplishments:**

- Vite build pipeline extended with React 19 + Recharts 3 + Motion, plus a reusable `reactChartBridge` Alpine.data bridge (mount-once/update-via-render/unmount-on-destroy-and-navigate) and a theme-flexible `ChartCard` component — no PHP/Blade wiring yet.
- Wired the 20-01 Alpine/React bridge into a real Filament ChartWidget, registered the Vite chart entry across all 5 panels, and proved the poll→dispatch→render cycle with a real Pest 4 Browser test — fixing a genuine Alpine-reactivity crash discovered along the way.
- Human-verified in a real browser across all 5 panels (Admin, Coordinator, AreaCoordinator, Leader, Reports): the React island renders, updates live on wire:poll ticks, unmounts cleanly on Livewire SPA navigation with no leaked root, and does not disrupt other pre-existing Livewire widgets.
- Chart.js-to-Recharts data adapter, D-03 monochrome palette, es-CO formatter, and 4 real Recharts kind components (line/bar/pie/sparkline) dispatched via a new `ChartRouter`, with zero PHP-side reshaping.
- Rewrote ChartCard.jsx from a PoC-only hardcoded bar chart into the shared MonoCharts chrome shell (error/empty/entrance-animation states wrapping ChartRouter) and generalized react-chart.blade.php to read each widget's real Filament heading/description with new data-chart-kind/data-question-id test attributes.
- Repointed the 2 simplest existing Chart.js widgets (fixed chart kind, no dynamic-type branching) onto the Phase 20/21 React island pipeline with zero changes to either widget's `getData()` body, backed by 2 new real-browser Pest tests.
- SurveyResultsWidget migrated onto the React/Recharts pipeline and mounted for the first time (EditSurvey footer, one per question), preserving its dynamic pie-for-YES_NO/bar-for-other-types switching exactly, with a real Pest 4 Browser test proving both chart kinds render on the real survey edit page.
- Two dedicated Recharts-backed sparkline `ChartWidget`s (`CampaignVotersSparklineWidget`, `SurveyResponsesSparklineWidget`) reuse their parents' unchanged chart-data methods and are registered across the Admin/Reports/Coordinator/AreaCoordinator/Leader panels per MIGR-02's D-01.
- Third and final MIGR-02 sparkline (`CallCenterCallsSparklineWidget`) wired onto `ListVerificationCalls` alongside its previously-unregistered parent `CallCenterStatsWidget`, both fixed for page-scoped Livewire component resolution.
- Removed Phase 20's throwaway ReactIslandPocWidget end-to-end, fixed a real hardcoded-light-theme bug the human checkpoint surfaced, and closed Phase 21 with the user's explicit browser sign-off on all 6 migrated/new chart widgets.
- 4 new Recharts chart-kind components (stacked-bar, funnel, gauge, histogram) registered in ChartRouter, plus an order-preserving row adapter and 5 new Spanish empty-state copy keys — the shared JS contract every Phase 22 PHP widget plan will consume with zero further JS changes.
- Two new Admin-dashboard ChartWidgets — a 12-state VoterStatus donut and a per-coordinator-team validado/rechazado/registrado stacked-bar — both campaign-scoped, both registered panel-globally, both covered by real Pest 4 Browser tests against a genuine Chromium session.
- Two new Filament ChartWidgets (funnel kind) exposing call-attempt contactability and message read/click data, both previously invisible, registered page-scoped with full wire:poll safety.
- Two new `ChartWidget`s (gauge + histogram) reading `SurveyMetricsCalculator`'s precomputed `average_value`/`distribution` columns directly, wired one-of-each per SCALE question into `EditSurvey`'s footer alongside the existing bar-chart `SurveyResultsWidget`.
- Built 4 new React/Recharts chart-kind components (sankey, treemap, heatmap, stacked-area), wired them into ChartRouter.jsx, and fixed a real empty-state bug in isChartDataEmpty() that would have made every sankey/treemap/heatmap widget always show "Sin datos" regardless of real data.
- Admin-only campaign-wide funnel showing cumulative-subset counts through the Voter lifecycle's happy path (PENDING_REVIEW → VERIFIED_CENSUS → CONFIRMED → VOTED), with a separate StatsOverviewWidget counter row for the 6 branch/terminal VoterStatus states, both reusing the existing `funnel` chart kind and `VoterStatus` enum metadata with zero new frontend code.
- Admin-only Sankey of curated ValidationHistory state transitions (top-8 + per-source Otros collapse, synthetic Nuevo node) and a weekly 4-series stacked-area of rejection reasons, both wired into AdminPanelProvider alongside 23-02's widgets
- Replaced TerritorialDistributionChart's flat top-10-municipios bar list with a 3-level drill-down treemap (Departamento -> Municipio -> Barrio) in the exact same widget slot, using a LEFT JOIN aggregation that buckets voters with no assigned neighborhood into an explicit "Sin barrio" leaf instead of dropping them.
- Admin-only heatmap of call-center caller x business-hour contact-rate %, built on the shared HeatmapChart.jsx component from 23-01, registered on the Admin dashboard after 23-03's Sankey/StackedArea entries.
- Fixed VoterHappyPathFunnelChart's real stage-label word-wrap bug in FunnelChart.jsx via an unclamped custom LabelList content renderer, after confirming in a real browser that the plan's originally-proposed margin.right fix did not actually work
- Cached, campaign+event-scoped hourly-cumulative Recharts line chart of Día D voting progress, polling every 30s without re-running its aggregation query on every tick.

---

## v1.2 Articuladores + Metadata de Usuario (Shipped: 2026-08-12)

**Scope:** 8 roadmap phases (12-19, includes 2 milestone-audit gap-closure phases), 29 plans, 67 tasks.
**Timeline:** 2026-08-10 → 2026-08-12 (~2 days), 87 PHP files changed (+6,516/-62 lines).
**Requirements:** 17/17 v1.2 requirements Done (ARTIC-01..05, AUTHZ-01..03, META-01..06, FILT-01..03).

**Key accomplishments:**

- New `articulador` (`area_coordinator`) hierarchy tier — a dedicated `area_coordinator_user_id` self-referencing FK (structurally independent of `coordinator_user_id`, no backend-enforced cap), a Filament admin resource for superadmin/admin_campaign management, and a full self-service panel (`AreaCoordinatorPanelProvider` at `/articulador`) mirroring the existing coordinador experience — create/edit coordinadores, own-team scoping, no OTP (Phases 12, 14, 15).
- Existing coordinador-scoped surfaces (`TopLeadersTable`, `TopLeadersExport`, `LeadersExportController`, and later `CampaignStatsOverview`/`TerritorialDistributionChart`) correctly resolve an articulador's full transitive team via a centralized `User::teamCoordinatorUserIds()` helper, with a new `CoordinatorPolicy` denying cross-boundary view/edit access with a named 403 reason (Phase 13, closed end-to-end in Phase 18).
- Superadmin-managed, typed metadata-key catalog (`MetadataKeyResource`: numeric/text/date/select) with atomic, append-only, fully audited per-subordinate value assignment — individual and bulk — reachable from both the Filament admin panel and the Volt coordinador/articulador panels, gated so a `reviewer` role can no longer write metadata rows with zero authorization check (Phase 16).
- All four Filament admin tables (Usuarios, Coordinadores, Líderes, Articuladores) filter and sort by any assigned metadata value with correct numeric ordering (not alphabetical), and the four matching CSV/xlsx exports gained the same metadata columns — one shared SQL-scale `withCurrentValueSelects()`/`applyMetadataFilter()` mechanism, zero N+1 queries (Phase 17).
- A post-ship milestone audit found and closed two real gaps with dedicated phases: an unreachable export route for articuladores (`LeadersExportController`'s route excluded `area_coordinator`, fixed with a narrowly-scoped route split — Phase 18), and a genuine cross-articulador dashboard data leak where `CampaignStatsOverview`/`TerritorialDistributionChart` showed full-campaign totals instead of the articulador's own team (Phase 19).
- Phase 15's 3 manual-only UAT items (dashboard widget scoping, cédula autofill lock/unlock, sidebar navigation) were replaced with real Pest v4 Browser test coverage against a genuine Chromium session — which itself caught and fixed 2 more live bugs along the way: a 403 on Día D navigation (`DiaD::canAccess()` missing `area_coordinator`) and a dashboard-crashing `RouteNotFoundException` from `VoterResource::getUrl()` called outside the admin panel (Phase 19).

---

## v1.1 Consulta de Puesto de Votación Resiliente (Shipped: 2026-08-10)

**Scope:** 6 roadmap phases (6-11), 15 plans, 29 tasks.
**Timeline:** 2026-07-24 → 2026-07-26 (~2 days), 94 commits, 100 files changed (+229,065/-272 lines — includes the one-time 216K-row national census CSV data import).
**Requirements:** 17/17 v1.1 requirements Done (CENSO-01/02/03, SRC-01..05, LIVE-01..03, RECON-01..06).

**Key accomplishments:**

- Imported the 216K-row national census snapshot into a cédula-indexed, divipol-enriched `national_census_records` table via `census:import-national`, reporting unmatched-divipol percentage instead of aborting on bad rows (Phase 6).
- Made a voter's polling-place source a first-class, auditable attribute: `polling_place_source`/`polling_place_resolved_at` on `voters` plus an append-only `polling_place_resolutions` audit trail tolerating a nullable headless actor for automated writes (Phase 7).
- Built the single `PollingPlaceResolver` service expressing SIGMA's entire fallback cascade (campaign-DB → national snapshot → bounded live attempt) with a no-downgrade guard so a live-verified result can never be silently overwritten by staler data, fully covered by 17 Pest tests (Phase 8).
- Validated `wsp.registraduria.gov.co` (reCAPTCHA Enterprise) as a live-source adapter end-to-end — 29/30 real 2captcha-solved attempts succeeded across 3 test cédulas — documenting a **Verdict: GO** before the system was allowed to rely on it (Phase 9).
- Shipped operator-facing provenance controls: a source badge on the voter edit form, a three-role gate on the paid force-refresh action, and a campaign-scoped fallback-voters dashboard widget — all human-verified live in the running Filament admin panel (Phase 10).
- Delivered an unattended hourly `census:reconcile-live` job that safely re-attempts live lookup for fallback-sourced voters, bounded and circuit-breaker-gated so a prolonged outage can't self-flood, with a defined terminal/exhaustion state and a lock that can't be silently frozen — covering RECON-01 through RECON-06 (Phase 11).

---

## v1.0 MVP Hardening (Shipped: 2026-07-24)

**Scope:** 8 roadmap phases (5 core + 3 urgent insertions: 02.1, 04.1, 05.1), 25 formally-planned plans across the 3 insertion phases (66 tasks), core Phases 1-5 delivered via those insertions plus incidental work and closed out by Phase 05.1's gap audit.
**Timeline:** 2026-03-25 → 2026-07-24 (~121 days), 226 commits, 342 files changed (+35,589/-1,485 lines).
**Requirements:** 30/30 v1 requirements Done (verified via Phase 05.1 goal-backward check, 22/22 must-haves, 892/892 tests green).

**Key accomplishments:**

- Renamed the product's core entity from "Votante" to "Apoyo" everywhere (admin/leader/coordinator panels, exports, public registration), replaced the hard duplicate-cédula block with an auditable suffix + admin-only ownership-reassignment action, added leader/coordinator exclusion rules and Gremio/Subcategoría classification, and shipped an admin-only CSV bulk importer with partial-success rejection reporting (Phase 02.1).
- Shipped six trustworthy Apoyo reporting surfaces as dashboard widgets with CSV export — leader/coordinator/polling-place rankings, a rejections report, a duplicates report (the one deliberate cross-campaign isolation exception), and a jurisdiction dentro/fuera report — all excluding duplicate-status Apoyos except where intentional (Phase 04.1).
- Closed a full-codebase audit's remaining gaps across campaign safety, permissions, voter operations, outreach, and reporting: authorization denials now name the specific reason (campaign/role/territory), job/queue contexts are verified campaign-safe, census validation is UI-triggerable with a visible source and next-action guidance, and Coordinator/Leader dashboards now scope to their own team instead of the whole campaign (Phase 05.1).
- Delivered two client-requested features with live human-verified checkpoints: OTP-gated leader-account creation over Hablame SMS, and a Super Admin maintenance kill switch with automatic self-bypass — the checkpoint process caught and fixed a real self-lockout bug before sign-off (Phase 05.1).
- Hardened Day D execution: a DB-level unique constraint plus a defined conflict rule now prevent duplicate/conflicting vote records, participation stats break down per-territory instead of campaign-only, and `FinalizeElectionEvent` runs on the real queue with structured logging and a dedicated duplicate-prevention test instead of `dispatchSync()` silently swallowing failures (Phase 05.1).

---
