# Architecture Research

**Domain:** Political campaign operations platform
**Researched:** 2026-03-25
**Confidence:** HIGH

## Standard Architecture

### System Overview

```text
┌──────────────────────────────────────────────────────────────┐
│                 Operational UI and Role Panels              │
├──────────────────────────────────────────────────────────────┤
│  Admin Panel   Coordinator Panel   Leader Panel   Reviewers │
│  Dashboards    Voter Workflows      Territory Ops  Call Queue│
└───────────────┬───────────────────────┬──────────────────────┘
                │                       │
┌───────────────┴───────────────────────┴──────────────────────┐
│              Workflow / Application Services Layer           │
├──────────────────────────────────────────────────────────────┤
│ Campaign Context │ Authorization │ Imports │ Validation      │
│ Segmentation     │ Calls / SMS   │ Reporting│ Day D Commands │
└───────────────┬───────────────────────┬──────────────────────┘
                │                       │
┌───────────────┴───────────────────────┴──────────────────────┐
│                 Core Domain and Data Layer                   │
├──────────────────────────────────────────────────────────────┤
│ Campaigns │ Users/Roles │ Territory │ Voters │ Contact Logs  │
│ Surveys   │ Call Assignments │ Messages │ Vote Records       │
└───────────────┬───────────────────────┬──────────────────────┘
                │                       │
┌───────────────┴───────────────────────┴──────────────────────┐
│           Persistence / Async / Integration Layer            │
├──────────────────────────────────────────────────────────────┤
│ PostgreSQL │ Redis │ Queues │ Files │ SMS/Webhooks │ Exports │
└──────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Typical Implementation |
|-----------|----------------|------------------------|
| Campaign context boundary | Resolve active campaign and enforce tenant-like scoping | Middleware, scoped services, policies, model scopes |
| Voter spine | Own the authoritative lifecycle for voter records and readiness | Domain services plus Eloquent models and workflow-oriented UI |
| Outreach execution | Calls, messages, scripts, survey capture, delivery callbacks | Jobs, webhook handlers, activity logs, queue-backed reconciliation |
| Decision support | Dashboards, KPI snapshots, exports, drilldowns | Read models, cached aggregates, role-safe widgets, export jobs |
| Day D execution | Fast lookup, state changes, evidence capture, dedupe | Tight workflows, audit records, upload storage, invariants |

## Recommended Project Structure

```text
app/
├── Domain/                 # Business workflows and invariants
│   ├── Campaigns/          # Campaign context, scoping, status rules
│   ├── Voters/             # Import, lifecycle, validation, segmentation
│   ├── Outreach/           # Calls, SMS, scripts, contact attempts
│   ├── Reporting/          # KPI queries, aggregate builders, exports
│   └── ElectionDay/        # Vote records, readiness, evidence rules
├── Filament/               # Resources, pages, widgets, role-specific panels
├── Actions/                # UI-safe application actions for single operations
├── Jobs/                   # Imports, callbacks, recomputations, exports
├── Policies/               # Role + campaign-aware permissions
└── Support/                # Shared helpers, DTOs, guards, value objects
```

### Structure Rationale

- **Domain/**: Reduces logic leakage out of widgets, resources, and components
- **Actions/**: Gives UI code small, explicit entry points for sensitive state transitions
- **Jobs/**: Makes slow or retryable operations observable and safe

## Architectural Patterns

### Pattern 1: Workflow-Oriented Services

**What:** Model multi-step operational flows as explicit services or actions instead of spreading logic across widgets and forms.
**When to use:** Imports, validation transitions, queue loading, message dispatch, and Day D status changes.
**Trade-offs:** Adds structure, but sharply reduces hidden coupling in UI components.

### Pattern 2: Campaign Boundary First

**What:** Every query, action, and export starts by resolving campaign context and role scope.
**When to use:** Everywhere data is read or mutated.
**Trade-offs:** Adds discipline and helper abstractions, but prevents the most dangerous class of product bugs.

### Pattern 3: Write Models and Read Models

**What:** Keep transactional state changes separate from reporting queries and KPI snapshots.
**When to use:** Dashboards, widgets, rollups, exports, and command-center views.
**Trade-offs:** Slightly more complexity, but much better reporting trust and performance.

## Data Flow

### Request Flow

```text
[User Action]
    ↓
[Filament/Livewire UI]
    ↓
[Action or Domain Service]
    ↓
[Policy + Campaign Guard]
    ↓
[Eloquent Write / Job Dispatch / Event]
    ↓
[Database + Cache + External Provider]
```

### State Management

```text
[Canonical database state]
    ↓
[Derived aggregates / widgets / exports]
    ↓
[Operator decisions]
    ↓
[New workflow actions]
```

### Key Data Flows

1. **Voter spine:** import/create -> territorial assignment -> validation -> status progression -> segment selection -> calls/messages -> Day D readiness
2. **Outreach evidence:** call or SMS dispatch -> provider callback / agent outcome -> contact history -> updated segment/reporting state
3. **Decision support:** transactional writes -> aggregate refresh -> role-safe dashboard and export visibility

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 0-10 operators per campaign | Monolith is fine; focus on correctness, tests, and query discipline |
| 10-100 concurrent operators | Move to PostgreSQL + Redis queues, add aggregate caching, queue imports/exports |
| 100+ or many active campaigns | Add stronger job isolation, reporting snapshots, and audit-friendly background recomputation |

### Scaling Priorities

1. **First bottleneck:** Heavy Livewire/Filament tables and aggregates - fix with slimmer queries, dedicated read models, and cached KPI builders
2. **Second bottleneck:** Imports, messaging callbacks, and export jobs - fix with Redis-backed queues, retries, and idempotent handlers

## Anti-Patterns

### Anti-Pattern 1: Business Rules Hidden in Widgets

**What people do:** Put workflow logic directly in table/widget closures and resource callbacks
**Why it's wrong:** It creates brittle runtime failures and makes policy/scoping reuse difficult
**Do this instead:** Push sensitive workflow logic into actions or services with tests

### Anti-Pattern 2: Reporting Directly from Transactional UI Queries

**What people do:** Reuse list queries for dashboard truth
**Why it's wrong:** Reporting drifts, role filters are inconsistent, and widgets break under edge cases
**Do this instead:** Build reporting-oriented queries or snapshot jobs explicitly

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| SMS provider | Outbound job + webhook callback reconciliation | Delivery status should update message state, not live only in provider logs |
| File imports/exports | Queued jobs + persistent audit records | Preview, dry-run, and error reports matter for operator trust |
| Storage for Day D evidence | Signed uploads / validated storage writes | Evidence metadata belongs in auditable domain records |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Campaign context <-> every workflow | Shared guards and scoped queries | Must be impossible to bypass accidentally |
| Voter domain <-> reporting | Events or explicit refresh actions | Keep dashboard truth traceable to source state |
| Outreach <-> voter lifecycle | Logged attempts and explicit status transitions | Avoid hidden side effects |

## Sources

- Local source: `/Volumes/NAS(MAC)/Data/Herd/sigma-project/.planning/PROJECT.md`
- Local source: `/Volumes/NAS(MAC)/Data/Herd/sigma-project/.planning/codebase/ARCHITECTURE.md`
- https://www.ngpvan.com/voter-file-access/
- https://www.ngpvan.com/wp-content/uploads/2024/10/MiniVAN-reporting.pdf
- https://www.ngpvan.com/wp-content/uploads/Use-and-manage-VPB.pdf
- https://nationbuilder.com/ecanvasserapp
- https://nationbuilder.com/handynation

---
*Architecture research for: political campaign operations platform*
*Researched: 2026-03-25*
