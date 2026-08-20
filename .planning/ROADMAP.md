# Roadmap: SIGMA - Sistema Integral de Gestion y Analisis Electoral

## Milestones

- ✅ **v1.0 MVP Hardening** — Phases 1-5.1 (shipped 2026-07-24)
- ✅ **v1.1 Consulta de Puesto de Votación Resiliente** — Phases 6-11 (shipped 2026-08-10)
- ✅ **v1.2 Articuladores + Metadata de Usuario** — Phases 12-19 (shipped 2026-08-12)
- 🚧 **v1.3 Visualización de Datos MonoCharts** — Phases 20-24 (in progress)

## Phases

<details>
<summary>✅ v1.0 MVP Hardening (Phases 1-5.1) — SHIPPED 2026-07-24</summary>

- [x] Phase 1: Campaign Safety & Role Boundaries — completed 2026-07-24 (via 02.1 + incidental work + 05.1 gap closure)
- [x] Phase 2: Voter Spine Hardening — completed 2026-07-24 (via 02.1 + incidental work + 05.1 gap closure)
- [x] Phase 02.1: Apoyos - Reglas Core y Segmentacion (INSERTED) — 11/11 plans — completed 2026-07-24
- [x] Phase 3: Outreach & Follow-up Reliability — completed 2026-07-24 (via incidental work + 05.1 gap closure)
- [x] Phase 4: Trusted Reporting & Control Surfaces — completed 2026-07-24 (via 04.1 + 05.1 gap closure)
- [x] Phase 04.1: Reportes Avanzados de Apoyos (INSERTED) — 5/5 plans — completed 2026-07-24
- [x] Phase 5: Day D Readiness & Trust Safeguards — completed 2026-07-24 (via incidental work + 05.1 gap closure)
- [x] Phase 05.1: Cross-Phase Hardening & Trust Safeguards Closure (INSERTED) — 9/9 plans — completed 2026-07-24

Full phase details, success criteria, and per-plan history: `.planning/milestones/v1.0-ROADMAP.md`
Requirements traceability: `.planning/milestones/v1.0-REQUIREMENTS.md`
Shipped summary: `.planning/MILESTONES.md`

</details>

<details>
<summary>✅ v1.1 Consulta de Puesto de Votación Resiliente (Phases 6-11) — SHIPPED 2026-08-10</summary>

- [x] Phase 6: National Census Snapshot Import — completed 2026-07-24
- [x] Phase 7: Source-Flag Schema & Resolution Audit Trail — completed 2026-07-24
- [x] Phase 8: Resilient PollingPlaceResolver Service — completed 2026-07-25
- [x] Phase 9: Live-Source Feasibility Spike — completed 2026-07-25 (Verdict: GO)
- [x] Phase 10: Operator Provenance & Fallback Controls — completed 2026-07-26
- [x] Phase 11: Scheduled Reconciliation Job — completed 2026-07-26

Full phase details, success criteria, and per-plan history: `.planning/milestones/v1.1-ROADMAP.md`
Requirements traceability: `.planning/milestones/v1.1-REQUIREMENTS.md`
Shipped summary: `.planning/MILESTONES.md`

</details>

<details>
<summary>✅ v1.2 Articuladores + Metadata de Usuario (Phases 12-19) — SHIPPED 2026-08-12</summary>

- [x] Phase 12: Hierarchy & Metadata Schema Foundation — completed 2026-08-10
- [x] Phase 13: Hierarchy Authorization & Call-Site Audit — completed 2026-08-10
- [x] Phase 14: Articulador Admin Resource & Hierarchy Wiring — completed 2026-08-10
- [x] Phase 15: Articulador Self-Service Panel — completed 2026-08-10 (UAT closed via Phase 19)
- [x] Phase 16: Metadata Catalog UI & Assignment — completed 2026-08-11
- [x] Phase 17: Filter/Sort/Export Surfaces — completed 2026-08-12
- [x] Phase 18: Articulador Líder-Export Reachability (INSERTED — audit gap closure) — completed 2026-08-12
- [x] Phase 19: Articulador Panel Human-UAT Closure (INSERTED — audit gap closure) — completed 2026-08-12

Full phase details, success criteria, and per-plan history: `.planning/milestones/v1.2-ROADMAP.md`
Requirements traceability: `.planning/milestones/v1.2-REQUIREMENTS.md`
Milestone audit: `.planning/milestones/v1.2-MILESTONE-AUDIT.md`
Shipped summary: `.planning/MILESTONES.md`

</details>

### 🚧 v1.3 Visualización de Datos MonoCharts (In Progress)

**Milestone Goal:** Dotar al panel Filament de gráficas ricas con la composición visual real de MonoCharts (no solo paleta), portando componentes React/Recharts como isla aislada sobre Livewire, para exponer insights operativos hoy invisibles.

- [ ] **Phase 20: React Island Infrastructure** - Chart components mount as an isolated, poll-safe, navigation-safe React island inside Filament
- [ ] **Phase 21: Migrate Existing Charts to React/Recharts** - The 3 existing ChartWidgets + 3 sparklines move to the new pipeline with zero data-query changes
- [ ] **Phase 22: Table-Stakes New Visualizations** - Admin gains 5 new direct-primitive charts (donut, stacked-bar, 2 funnels, gauge+histogram)
- [ ] **Phase 23: Differentiator Visualizations** - Admin gains 5 curated/modeled charts (happy-path funnel, Sankey, drill-down treemap, heatmap, stacked-area)
- [ ] **Phase 24: Día D Live Voting Visualization** - Admin/operator sees a cached, live-updating Día D voting line chart

## Phase Details

### Phase 20: React Island Infrastructure
**Goal**: Developers can build React+Recharts+Motion chart components that mount as isolated islands inside Filament widgets, safely surviving Livewire's polling and SPA navigation.
**Depends on**: Nothing (first phase of v1.3)
**Requirements**: INFRA-01, INFRA-02, INFRA-03, INFRA-04
**Success Criteria** (what must be TRUE):
  1. A throwaway React chart widget renders inside a Filament panel without disrupting other Livewire widgets' DOM diffing/polling behavior
  2. The mounted chart's content updates automatically on `wire:poll` ticks (verified across a full poll cycle in a real browser) without ever being remounted or reading stale re-rendered DOM
  3. Navigating away from a panel via Livewire's SPA navigation cleanly unmounts the React root with no leaked root, verified on all 5 panels that register chart widgets (Admin, Coordinator, AreaCoordinator, Leader, Reports)
  4. A Pest 4 Browser test exists verifying the throwaway widget's real rendered chart content, establishing the per-shipped-widget browser-test convention every later phase follows
**Plans**: 3 plans
Plans:
- [x] 20-01-PLAN.md — Vite/Recharts/Motion build pipeline + React<->Alpine bridge core (mount/update/unmount) + theme-flexible ChartCard component — completed 2026-08-20
- [x] 20-02-PLAN.md — Throwaway ReactIslandPocWidget + shared react-chart.blade.php view, registered on all 5 PanelProviders, plus Pest 4 Browser test — completed 2026-08-20
- [x] 20-03-PLAN.md — Human browser checkpoint (D-04): poll cycle, cross-panel navigation, no leaked root — completed 2026-08-20
**UI hint**: yes

### Phase 21: Migrate Existing Charts to React/Recharts
**Goal**: The 3 existing `ChartWidget`s and 3 embedded sparklines render through the new React/Recharts pipeline instead of Chart.js, with zero changes to their underlying data queries.
**Depends on**: Phase 20
**Requirements**: MIGR-01, MIGR-02
**Success Criteria** (what must be TRUE):
  1. `ValidationProgressChart`, `TerritorialDistributionChart`, and `SurveyResultsWidget` render via Recharts and show the same data as before, with `getData()` queries unchanged
  2. The 3 embedded sparklines (`CampaignStatsOverview`, `CallCenterStatsWidget`, `SurveyStatsOverview`) render through the new pipeline
  3. Each migrated widget has a Pest 4 Browser test verifying real rendered chart content (per the Phase 20 convention)
**Plans**: TBD
**UI hint**: yes

### Phase 22: Table-Stakes New Visualizations
**Goal**: Admins gain 5 new, currently-missing operational visualizations covering voter-status distribution, coordinator team comparison, call/message funnels, and survey score distribution.
**Depends on**: Phase 21
**Requirements**: VIZ-01, VIZ-02, VIZ-03, VIZ-04, VIZ-05
**Success Criteria** (what must be TRUE):
  1. Admin sees a donut chart of the 12 `VoterStatus` state distribution for the active campaign
  2. Admin sees a stacked-bar comparison of registered/validated/rejected apoyos per coordinator/team
  3. Admin sees a funnel of call contactability by attempt number (intento 1 → 2 → 3+ → contactado)
  4. Admin sees a funnel of message delivery (enviado → entregado → leído → clic) for `MessageBatch`/`Message`, previously invisible despite being already computed on the model
  5. Admin sees a gauge of the average SCALE-type survey response score alongside a histogram of the full response distribution
**Plans**: TBD
**UI hint**: yes

### Phase 23: Differentiator Visualizations
**Goal**: Admins gain deeper structural insight into validation-history transitions, territorial hierarchy, caller effectiveness, and rejection trends — data that requires curated modeling decisions, not just component swaps.
**Depends on**: Phase 22
**Requirements**: VIZ-06, VIZ-07, VIZ-08, VIZ-09, VIZ-10
**Success Criteria** (what must be TRUE):
  1. Admin sees a funnel of a defined "happy path" Voter lifecycle subset (e.g. PENDING_REVIEW→VERIFIED_CENSUS→CONFIRMED→VOTED), with rejected/duplicate terminal states shown separately rather than forced into the funnel shape
  2. Admin sees a Sankey diagram of `ValidationHistory` state transitions, curated to a defined meaningful/top-N transition set rather than an unfiltered dump
  3. Admin sees a drill-down treemap of territorial distribution (Departamento → Municipio → Barrio, one level at a time) instead of the current flat top-10 bar list
  4. Admin sees a heatmap of call-center caller × hour effectiveness, with a real positioned tooltip (not the browser's native `title` attribute) and a defined strategy for handling more callers than fit on screen
  5. Admin sees a stacked-area chart of rejection reasons over time, broken down by rejection status rather than a single aggregate counter
**Plans**: TBD
**UI hint**: yes

### Phase 24: Día D Live Voting Visualization
**Goal**: Admins/operators can watch live Día D voting progress update in real time without imposing expensive per-tick query load on the campaign database.
**Depends on**: Phase 21 (proven poll→dispatch→render pipeline)
**Requirements**: DAYD-05
**Success Criteria** (what must be TRUE):
  1. Admin/operator sees a live-updating line chart of Día D voting progress (`VoteRecord.voted_at` accumulated hourly)
  2. The chart's data is backed by a cached/pre-aggregated campaign-scoped endpoint rather than an expensive per-tick `COUNT` query
  3. The chart continues updating correctly under concurrent polling load without visible performance degradation
**Plans**: TBD
**UI hint**: yes

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1-5.1. v1.0 MVP Hardening | 25/25 | Complete | 2026-07-24 |
| 6-11. v1.1 Consulta de Puesto de Votación Resiliente | 15/15 | Complete | 2026-08-10 |
| 12-19. v1.2 Articuladores + Metadata de Usuario | 29/29 | Complete | 2026-08-12 |
| 20. React Island Infrastructure | 2/3 | In progress | - |
| 21. Migrate Existing Charts to React/Recharts | 0/TBD | Not started | - |
| 22. Table-Stakes New Visualizations | 0/TBD | Not started | - |
| 23. Differentiator Visualizations | 0/TBD | Not started | - |
| 24. Día D Live Voting Visualization | 0/TBD | Not started | - |

---

*v1.0 shipped 2026-07-24. v1.1 shipped 2026-08-10. v1.2 shipped 2026-08-12. v1.3 (Phases 20-24) roadmapped 2026-08-20, awaiting plan-phase.*
