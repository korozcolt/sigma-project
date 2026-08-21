# Requirements: SIGMA v1.3 Visualización de Datos MonoCharts

**Defined:** 2026-08-20
**Core Value:** Campaign teams can run critical voter and field operations from one place with trustworthy, campaign-safe data and clear operational traceability.

## v1 Requirements

Requirements for milestone v1.3. Each maps to roadmap phases.

### Infraestructura de isla React (INFRA)

- [x] **INFRA-01**: A developer can build React+Recharts+Motion components that mount into Filament widgets as an isolated island (dedicated Vite entry, `wire:ignore` boundary) without affecting Livewire's existing DOM diffing/polling behavior on any other widget
- [x] **INFRA-02**: A mounted chart receives fresh data on every `wire:poll` tick via Filament's existing `dispatch()`/checksum channel (never remounted, never read off stale re-rendered DOM), verified across at least one full poll cycle in a real browser
- [x] **INFRA-03**: A mounted React chart's root is cleanly unmounted (no leaked root) when the user navigates away from the panel via Livewire's SPA navigation, verified on all 5 panels that register chart widgets (Admin, Coordinator, AreaCoordinator, Leader, Reports)
- [x] **INFRA-04**: A Pest 4 Browser (Playwright) test exists per shipped chart widget verifying real rendered chart content, distinct from and in addition to any Livewire Feature test that only verifies the PHP-side data contract

### Migración de charts existentes (MIGR)

- [x] **MIGR-01**: `ValidationProgressChart`, `TerritorialDistributionChart`, and `SurveyResultsWidget` render through the new React/Recharts pipeline instead of Chart.js, with their existing campaign/role-scoped `getData()` queries unchanged
- [x] **MIGR-02**: The 3 embedded sparklines (`CampaignStatsOverview`, `CallCenterStatsWidget`, `SurveyStatsOverview`) render through the new pipeline

### Visualizaciones nuevas — table stakes (VIZ)

- [x] **VIZ-01**: An admin sees a donut chart of the 12 `VoterStatus` state distribution for the active campaign
- [x] **VIZ-02**: An admin sees a stacked-bar comparison of registered/validated/rejected apoyos per coordinator/team
- [x] **VIZ-03**: An admin sees a funnel of call contactability by attempt number (intento 1 → 2 → 3+ → contactado)
- [x] **VIZ-04**: An admin sees a funnel of message delivery (enviado → entregado → leído → clic) for `MessageBatch`/`Message`, a metric with zero visualization today despite being already computed on the model
- [x] **VIZ-05**: An admin sees a gauge showing the average SCALE-type survey response score alongside a histogram of the full response distribution

### Visualizaciones nuevas — diferenciadores (VIZ)

- [x] **VIZ-06**: An admin sees a funnel of a defined "happy path" subset of the Voter lifecycle (a genuinely linear sequence of `VoterStatus` states, e.g. PENDING_REVIEW→VERIFIED_CENSUS→CONFIRMED→VOTED), with rejected/duplicate terminal states shown separately rather than forced into the funnel shape
- [x] **VIZ-07**: An admin sees a Sankey diagram of `ValidationHistory` state transitions, curated to a defined meaningful/top-N transition set rather than an unfiltered dump of every recorded transition pair
- [ ] **VIZ-08**: An admin sees a drill-down treemap of territorial distribution (Departamento → Municipio → Barrio, one level at a time) instead of the current flat top-10 bar list
- [ ] **VIZ-09**: An admin sees a heatmap of call-center caller × hour effectiveness, with a real positioned tooltip (not the browser's native `title` attribute) and a defined strategy for handling more callers than fit on screen
- [x] **VIZ-10**: An admin sees a stacked-area chart of rejection reasons over time (broken down by rejection status, not a single aggregate counter)

### Día D en vivo (DAYD)

- [ ] **DAYD-05**: An admin/operator sees a live-updating line chart of Día D voting progress (`VoteRecord.voted_at` accumulated hourly), backed by a cached/pre-aggregated campaign-scoped endpoint that avoids expensive per-tick `COUNT` queries under concurrent election-day load

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Visualización avanzada (VIZ)

- **VIZ-11**: True symmetric ThemeRiver-style streamgraph (silhouette/wiggle baseline) for rejection reasons, if VIZ-10's standard stacked-area is judged insufficient after real usage — Recharts has no native support, needs custom `d3-shape` work
- **VIZ-12**: Full literal trapezoid funnel of all 12 `VoterStatus` states (superseding VIZ-06's happy-path subset), if a complete product definition of every branch's funnel semantics is ever produced

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Forcing all 12 `VoterStatus` states into one literal trapezoid `Funnel` | `VoterStatus` is not a linear pipeline — `REJECTED_CENSUS`, `REJECTED_OUT_OF_SCOPE`, `DUPLICATE` are terminal side-branches, not narrowing stages; a trapezoid visually implies monotonic narrowing that doesn't exist in the data, conflicting with the project's "inaccurate operational numbers are unacceptable" constraint. Resolved instead via VIZ-06's happy-path subset. |
| True symmetric ThemeRiver streamgraph | Recharts has no native silhouette/wiggle baseline; achieving it needs custom `d3-shape` offset work for a purely aesthetic gain over VIZ-10's standard stacked-area. Deferred to v2 (VIZ-11). |
| Rendering the full raw `ValidationHistory` transition graph unfiltered as Sankey | With 12 states and real-world back-edges/cycles, an unfiltered Sankey becomes a dense, crossing-heavy diagram harder to read than the table it replaces. VIZ-07 requires curation instead. |
| Flat (non-drill-down) treemap of all barrios simultaneously | Recharts' squarified algorithm degrades badly with dozens–hundreds of same-level leaves (unreadable slivers) at real campaign scale. VIZ-08 requires nest-mode drill-down instead. |
| Adopting React as the app's primary frontend framework | This milestone is scoped to a narrow, isolated chart island over the existing Livewire/Filament/Blade stack — not a framework migration. Maintaining Laravel/Filament/Livewire/Eloquent as the architectural base is a standing project constraint. |
| Upgrading Vite, `laravel-vite-plugin`, or `@vitejs/plugin-react` past versions compatible with the pinned `vite@^7.0.4` | The latest majors of `laravel-vite-plugin`/`@vitejs/plugin-react` force a Vite 8 bump, which is out of scope for this milestone — the island must be purely additive with zero forced upgrades to existing build tooling. |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| INFRA-01 | Phase 20 | Done |
| INFRA-02 | Phase 20 | Done |
| INFRA-03 | Phase 20 | Done |
| INFRA-04 | Phase 20 | Done |
| MIGR-01 | Phase 21 | Complete |
| MIGR-02 | Phase 21 | Complete |
| VIZ-01 | Phase 22 | Done |
| VIZ-02 | Phase 22 | Done |
| VIZ-03 | Phase 22 | Done |
| VIZ-04 | Phase 22 | Done |
| VIZ-05 | Phase 22 | Done |
| VIZ-06 | Phase 23 | Done |
| VIZ-07 | Phase 23 | Done |
| VIZ-08 | Phase 23 | Pending |
| VIZ-09 | Phase 23 | Pending |
| VIZ-10 | Phase 23 | Done |
| DAYD-05 | Phase 24 | Pending |

**Coverage:**
- v1 requirements: 17 total
- Mapped to phases: 17/17 ✓
- Unmapped: 0 ✓

---
*Requirements defined: 2026-08-20*
*Last updated: 2026-08-20 after v1.3 roadmap creation (Phases 20-24)*
