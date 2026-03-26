# Feature Research

**Domain:** Political campaign operations platform
**Researched:** 2026-03-25
**Confidence:** HIGH

## Feature Landscape

### Table Stakes (Users Expect These)

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Campaign-safe voter database with targeting | Mature campaign tools center around voter files, targeting, and outreach segmentation | HIGH | Already present in SIGMA, but must be hardened around isolation, imports, dedupe, and role-aware filters |
| Canvassing / field follow-up workflows | Products like VAN, MiniVAN, NationBuilder + Ecanvasser, and HandyNation all emphasize mobile or distributed field contact workflows | HIGH | SIGMA does not need a new mobile app for this milestone, but it does need the current voter workflow to feel guided and reliable |
| Phone banking / call queue management | Mature products expose volunteer/reviewer workflows, queue state, and follow-up scripts | HIGH | The current `CallQueueTable` production failure is a direct trust issue in this category |
| Survey and contact capture with scripts | Campaign tools expect structured responses tied to outreach attempts | MEDIUM | SIGMA already has surveys and call history concepts; the next step is reliable transitions and traceability |
| Reporting, dashboards, and progress visibility | Operational tools surface counts, activity reports, turnout progress, and contact results | HIGH | Counts must be campaign-safe and role-safe or decision-makers stop trusting them |
| Election-day execution / GOTV readiness | Mature systems support turnout targeting, exclusion of already-voted contacts, field visibility, and rapid status updates | HIGH | This is central to SIGMA's value and should be treated as a trust-critical workflow, not a demo feature |

### Differentiators (Competitive Advantage)

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Strict campaign isolation inside one instance | Lets organizations operate multiple campaigns safely without standing up separate systems | HIGH | This is one of SIGMA's strongest strategic differentiators if implemented consistently |
| Unified voter spine across validation, communications, reporting, and Day D | Reduces operator context-switching and tribal knowledge | HIGH | This matches the user's stated product direction exactly |
| Territorial accountability by role | Makes it obvious who owns what territory, who is progressing, and where follow-up is blocked | MEDIUM | High decision-support value with modest UI and reporting work if underlying data is clean |
| Evidence-backed Day D execution | Adds trust through vote records, photos, GPS, and audit trails | HIGH | Strong differentiator for campaigns that need operational traceability |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Big-bang new modules during hardening | Teams want visible progress fast | It expands scope while leaving fragile workflows unresolved | Harden the voter spine and expose progress through better reporting first |
| Cross-campaign convenience shortcuts for normal users | Looks useful for coordinators who oversee many areas | Increases leakage risk and weakens the product's isolation promise | Keep cross-campaign visibility super-admin only, with explicit context switching |
| Offline-first mobile rewrite in this milestone | Sounds aligned with field work | Adds a new platform, sync complexity, and operational risk before current workflows are trustworthy | Keep improving current web workflows; evaluate offline later if field evidence demands it |

## Feature Dependencies

```text
Campaign context + role rules
    └──requires──> voter workflow safety
                         ├──requires──> import / validation integrity
                         ├──requires──> survey + call queue traceability
                         └──requires──> reporting consistency

Day D readiness ──requires──> trustworthy voter state + dedupe + evidence rules

Dashboards / command center ──depends on──> every upstream workflow writing correct, scoped data
```

### Dependency Notes

- **Reliable dashboards require trustworthy upstream workflow data:** reporting should be one of the last things to harden, not the first thing to invent
- **Day D requires clean voter state and evidence rules:** live turnout actions are only safe if duplication protection and campaign scoping are solid
- **Call-center and messaging flows depend on segment integrity:** bad scoping or stale states turn communications into noise quickly

## MVP Definition

### Launch With (v1)

- [ ] Campaign-safe voter import, creation, assignment, and status transitions - the core operational spine
- [ ] Census validation and follow-up traceability - needed to move voters through the workflow with confidence
- [ ] Reliable call-center and communication handoff - critical operational transition point
- [ ] Role-safe dashboards and exports for campaign decision-making - the system must be trusted as a control surface
- [ ] Day D readiness with evidence-backed status changes - core campaign execution value

### Add After Validation (v1.x)

- [ ] Leader productivity and territorial health scorecards - add once baseline counts are trusted
- [ ] Guided workflow shortcuts and bulk actions - add after end-to-end workflow rules are stabilized
- [ ] More advanced segmentation and saved views - add after data quality is proven

### Future Consideration (v2+)

- [ ] Offline-first field app or PWA - defer until actual field usage proves current web UX is not enough
- [ ] Predictive scoring or AI prioritization - defer until base reporting and state integrity are dependable
- [ ] Broader fundraising/compliance expansion - valuable, but outside the current voter-spine hardening milestone

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Campaign-safe voter workflow | HIGH | HIGH | P1 |
| Call-center and communication traceability | HIGH | HIGH | P1 |
| Reporting consistency and trusted dashboards | HIGH | MEDIUM | P1 |
| Day D readiness hardening | HIGH | HIGH | P1 |
| Territorial performance views | MEDIUM | MEDIUM | P2 |
| Workflow shortcuts and operator guidance | MEDIUM | MEDIUM | P2 |
| Offline field tooling | MEDIUM | HIGH | P3 |

## Competitor Feature Analysis

| Feature | Competitor A | Competitor B | Our Approach |
|---------|--------------|--------------|--------------|
| Voter targeting and outreach | NGP VAN emphasizes targeted universes, MiniVAN, and phone banks | NationBuilder emphasizes voter file tooling, walk lists, and dashboards | Keep SIGMA focused on a campaign-safe voter spine with stronger operational continuity across modules |
| Field canvassing and routing | Ecanvasser emphasizes walk lists, routing, activity tracking, and real-time sync | HandyNation emphasizes geolocation lists, contact logging, and map-based outreach | Improve SIGMA's workflow continuity first; revisit dedicated field UX only after baseline trust improves |
| Reporting visibility | MiniVAN reporting and activity reports expose attempts, contacts, and commit state | NationBuilder highlights control-panel tallies and mapped dashboards | Make SIGMA's counts and widget states dependable enough for operational decisions |

## Sources

- https://www.ngpvan.com/ - integrated campaign software positioning
- https://www.ngpvan.com/voter-file-access/ - voter targeting, MiniVAN, and phone-bank expectations
- https://www.ngpvan.com/resources/guides/voter-contact-scripts-guide/ - structured voter-contact and script-driven workflows
- https://www.ngpvan.com/wp-content/uploads/2024/10/MiniVAN-reporting.pdf - activity reporting and commit visibility
- https://www.ngpvan.com/wp-content/uploads/Use-and-manage-VPB.pdf - phone-bank state and exclusion behavior
- https://nationbuilder.com/ecanvasserapp - field canvassing, routing, real-time team tracking, and privacy controls
- https://nationbuilder.com/handynation - geolocation lists, map-based outreach, status updates, and event check-ins
- https://nationbuilder.com/voter_file_screencast - voter file workflows, call lists, walk lists, and dashboard tallies

---
*Feature research for: political campaign operations platform*
*Researched: 2026-03-25*
