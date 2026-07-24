# Requirements: SIGMA - Sistema Integral de Gestion y Analisis Electoral

**Defined:** 2026-07-24
**Core Value:** Campaign teams can run critical voter and field operations from one place with trustworthy, campaign-safe data and clear operational traceability.

## v1.1 Requirements

Requirements for the "Consulta de Puesto de Votación Resiliente" milestone. Each maps to roadmap phases.

### Censo Nacional (Snapshot Import)

- [ ] **CENSO-01**: Operator/system can resolve a voter's polling place from a national census snapshot when the live Registraduría source is unavailable
- [ ] **CENSO-02**: The national census snapshot is imported into an indexed, cédula-queryable table enriched with full department/municipality names and address (not just divipol codes)
- [ ] **CENSO-03**: Snapshot import validates its divipol codes against the current `polling_places` reference data and reports the unmatched percentage before go-live

### Procedencia y Auditoría (Source Provenance & Audit)

- [ ] **SRC-01**: Every polling-place result visibly shows whether it came from a live source, database reconstruction, local snapshot, or manual entry
- [ ] **SRC-02**: A voter's polling-place source is never silently downgraded — a live-verified result is never overwritten by an older snapshot result
- [ ] **SRC-03**: Every change to a voter's polling-place source is recorded in an auditable history (actor, previous → new source, timestamp)
- [ ] **SRC-04**: Operator can manually trigger a re-check of a voter's polling place at any time
- [ ] **SRC-05**: Operator can filter/view which voters currently have a fallback-sourced (non-live) polling place

### Fuente en Vivo Resiliente (Live Source Resiliency)

- [ ] **LIVE-01**: The live Registraduría lookup architecture supports multiple interchangeable source adapters tried in priority order, so a new source can be added without redesigning the resolver
- [ ] **LIVE-02**: Feasibility of `wsp.registraduria.gov.co` (reCAPTCHA Enterprise) as an additional live-source adapter is validated end-to-end before the system relies on it
- [ ] **LIVE-03**: The voter polling-place lookup workflow never blocks waiting on a live source that is unavailable

### Reconciliación Programada (Scheduled Reconciliation)

- [ ] **RECON-01**: A scheduled job automatically re-attempts live lookup for voters currently on fallback-sourced data and upgrades them when successful
- [ ] **RECON-02**: The reconciliation job respects campaign isolation — it resolves each voter's own campaign from the voter record, never from ambient/interactive session context
- [ ] **RECON-03**: The reconciliation job records an auditable actor/reason for every automatic update, even though it runs unattended
- [ ] **RECON-04**: The reconciliation job is rate-limited and bounded so a prolonged live-source outage cannot exhaust the captcha-solving budget or self-flood
- [ ] **RECON-05**: A voter whose live source can never be resolved (or requires human captcha interaction the job can't complete) eventually reaches a terminal state instead of being retried forever
- [ ] **RECON-06**: The scheduled job cannot be silently frozen indefinitely by a stuck/expired lock left by a previous failed or hung run

## v2 Requirements

Deferred to a future release. Tracked but not in this milestone's roadmap.

### Operator Visibility

- **WIDGET-01**: Dashboard widget shows reconciliation-queue depth and last successful live-source reachability
- **WIDGET-02**: Snapshot-hit results carry an explicit confidence/coverage state (`snapshot-hit` vs `unresolved`) distinct from "not found"
- **WIDGET-03**: Operator can bulk-select multiple fallback-sourced voters and force a batch re-check

## Out of Scope

| Feature | Reason |
|---------|--------|
| Automated pipeline to ingest future census snapshot refreshes | v1.1 ships a one-time import of the current snapshot; recurring ingestion is its own project |
| Real-time reconciliation on every voter page view | Live lookups are paid/rate-limited; per-view calls would cause cost explosion and latency — explicitly an anti-feature |
| Additional Registraduría endpoints beyond `wsp.registraduria.gov.co` | No other live candidate is known yet; the multi-adapter architecture (LIVE-01) allows adding one later without a redesign |
| Per-record reconciliation status narrative ("still trying" vs "gave up") | Presentation polish; the binary source flag + freshness timestamp is sufficient for v1.1 |
| Notifying operators on every silent reconciliation upgrade | Defeats the purpose of *silent* reconciliation; aggregate visibility is a v2 concern (WIDGET-01), not per-record notification spam |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| CENSO-01 | Phase 8 | Pending |
| CENSO-02 | Phase 6 | Pending |
| CENSO-03 | Phase 6 | Pending |
| SRC-01 | Phase 10 | Pending |
| SRC-02 | Phase 8 | Pending |
| SRC-03 | Phase 7 | Pending |
| SRC-04 | Phase 10 | Pending |
| SRC-05 | Phase 10 | Pending |
| LIVE-01 | Phase 8 | Pending |
| LIVE-02 | Phase 9 | Pending |
| LIVE-03 | Phase 8 | Pending |
| RECON-01 | Phase 11 | Pending |
| RECON-02 | Phase 11 | Pending |
| RECON-03 | Phase 11 | Pending |
| RECON-04 | Phase 11 | Pending |
| RECON-05 | Phase 11 | Pending |
| RECON-06 | Phase 11 | Pending |

**Coverage:**
- v1.1 requirements: 17 total
- Mapped to phases: 17 ✓
- Unmapped: 0

---
*Requirements defined: 2026-07-24*
*Last updated: 2026-07-24 after v1.1 roadmap creation (all 17 requirements mapped to Phases 6-11)*
