# Pitfalls Research

**Domain:** Political campaign operations platform
**Researched:** 2026-03-25
**Confidence:** HIGH

## Critical Pitfalls

### Pitfall 1: Partial Campaign Isolation

**What goes wrong:**
Some tables, widgets, exports, or actions respect campaign context while others leak cross-campaign records.

**Why it happens:**
Teams implement scoping in models or resources inconsistently, especially for aggregates, relation queries, imports, and exports.

**How to avoid:**
Centralize campaign resolution, require campaign-aware policies and query helpers, and test the same boundary across CRUD, widgets, exports, imports, and background jobs.

**Warning signs:**
Different counts between list views and dashboards, role confusion, or "works for super admin but not for campaign user" bugs.

**Phase to address:**
Phase 1 and every later verification phase.

---

### Pitfall 2: Workflow State Drift

**What goes wrong:**
Voter status, validation state, call outcomes, survey completion, and Day D readiness stop agreeing with each other.

**Why it happens:**
Each module updates its own local status rules without a shared workflow model.

**How to avoid:**
Define canonical workflow transitions and treat downstream modules as contributors to one voter spine instead of separate status silos.

**Warning signs:**
Operators ask "what state is this voter really in?" or need tribal knowledge to interpret UI.

**Phase to address:**
Phase 2.

---

### Pitfall 3: Trustless Reporting

**What goes wrong:**
Dashboards and exports look polished but disagree with operational reality.

**Why it happens:**
Reporting is built before source workflow rules are clean, or widget queries are copied from list views without role/campaign rigor.

**How to avoid:**
Build reporting from explicit aggregates and verify each KPI against source records and campaign scope.

**Warning signs:**
Counts differ by screen, exports disagree with dashboards, or users maintain their own spreadsheets "just in case."

**Phase to address:**
Phase 4.

---

### Pitfall 4: Communications Without Reconciliation

**What goes wrong:**
Calls and SMS are sent, but the system cannot reliably say what was delivered, failed, answered, or still needs follow-up.

**Why it happens:**
Teams treat outbound messaging or calls as one-shot actions instead of lifecycle events with callbacks, retries, and audit state.

**How to avoid:**
Use auditable message and call state machines, ingest provider callbacks, and keep follow-up eligibility derived from durable records.

**Warning signs:**
Operators manually cross-check providers, cannot explain delivery states, or re-contact people unnecessarily.

**Phase to address:**
Phase 3.

---

### Pitfall 5: Day D Features That Are Demo-Ready but Not Field-Ready

**What goes wrong:**
Election-day tools work in ideal conditions but fail under duplicates, missing evidence, poor connectivity, or high usage pressure.

**Why it happens:**
Teams optimize for feature completeness rather than field conditions and invariants.

**How to avoid:**
Validate evidence rules, duplicate protection, fast lookup, and progress visibility under realistic campaign conditions.

**Warning signs:**
Operators hesitate to rely on the tool live, or manual side channels remain mandatory on election day.

**Phase to address:**
Phase 5.

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Business rules inside widgets/resources | Fast delivery | Runtime fragility and poor testability | Never for critical workflow transitions |
| Database queue only for all background work | Simpler setup | Contention and poor observability as imports/callbacks grow | Only for very small installations |
| Manual spreadsheet reconciliation for reporting | Quick operator workaround | Permanent trust erosion in the platform | Only as a temporary incident response tool |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| SMS provider | Store only "sent" and ignore provider callbacks | Reconcile queued, sent, delivered, failed, and undelivered states |
| Import/export | Treat batch files as direct model writes | Add preview, validation, idempotency, and operator-facing error reports |
| Day D evidence storage | Save files without domain-level metadata checks | Tie uploads to validated vote records with campaign and actor context |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Heavy aggregate widgets on transactional tables | Slow dashboards and timeouts | Precompute or cache trusted aggregates | When dashboards run across large voter/contact tables |
| Large Livewire payloads in operational tables | Sluggish UI and brittle updates | Keep component state lean and query precisely | When lists or forms grow rich and role-aware |
| Synchronous imports/exports | Timeouts and operator confusion | Queue long-running jobs and surface status | As soon as files become routine rather than occasional |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Weak campaign boundary enforcement in exports/widgets | Cross-campaign data exposure | Reuse campaign-safe query builders everywhere |
| Over-broad role visibility | Role confusion or unauthorized operations | Use policies plus panel-specific visibility tests |
| Evidence writes without actor or campaign audit fields | Poor accountability during disputes | Persist actor, campaign, timestamp, and source metadata on critical records |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Module-first navigation with no workflow guidance | Users need tribal knowledge to finish tasks | Make the next action and missing prerequisites explicit |
| Hidden status semantics | Operators misread readiness and follow-up state | Use plain language stage indicators and reason codes |
| Errors that expose framework details | Users lose trust quickly | Convert failures into recoverable operational messages and log the technical detail separately |

## "Looks Done But Isn't" Checklist

- [ ] **Voter import:** Often missing preview, dedupe, and rollback visibility - verify dry-run plus audit output
- [ ] **Campaign isolation:** Often missing export/widget coverage - verify non-super-admin behavior across reads and writes
- [ ] **Call queue:** Often missing relation-safe query handling and status reconciliation - verify queue behavior under live data
- [ ] **Dashboards:** Often missing source-of-truth checks - verify counts against raw records
- [ ] **Day D readiness:** Often missing duplicate protection and evidence integrity - verify unhappy-path behavior

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Partial campaign isolation | HIGH | Freeze affected workflow, audit leaked surfaces, patch shared query helpers, backfill tests |
| Workflow state drift | MEDIUM | Define canonical transition rules, repair conflicting records, then migrate affected screens |
| Trustless reporting | MEDIUM | Recompute aggregates, publish the source-of-truth rule, and add verification tests |
| Communications without reconciliation | MEDIUM | Backfill provider statuses where possible and rebuild follow-up eligibility from durable logs |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Partial campaign isolation | Phase 1 | Role-scoped CRUD, widget, import, export, and report tests pass |
| Workflow state drift | Phase 2 | Voter lifecycle transitions are explicit and consistent across modules |
| Communications without reconciliation | Phase 3 | Call/message state matches follow-up eligibility and delivery logs |
| Trustless reporting | Phase 4 | Dashboard numbers reconcile to transactional records |
| Demo-ready Day D | Phase 5 | Live Day D flows pass unhappy-path and duplicate-protection tests |

## Sources

- Local source: `/Volumes/NAS(MAC)/Data/Herd/sigma-project/.planning/PROJECT.md`
- Local source: `/Volumes/NAS(MAC)/Data/Herd/sigma-project/docs/REGLAS_NEGOCIO.md`
- Local source: `/Volumes/NAS(MAC)/Data/Herd/sigma-project/PROGRESO.md`
- https://www.twilio.com/docs/messaging/guides/outbound-message-status-in-status-callbacks
- https://www.ngpvan.com/wp-content/uploads/2024/10/MiniVAN-reporting.pdf
- https://www.ngpvan.com/wp-content/uploads/Use-and-manage-VPB.pdf
- https://nationbuilder.com/ecanvasserapp
- https://nationbuilder.com/handynation

---
*Pitfalls research for: political campaign operations platform*
*Researched: 2026-03-25*
