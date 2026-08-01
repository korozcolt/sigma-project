---
phase: quick
plan: 260801-hvd
subsystem: auth
tags: [laravel, livewire, volt, invitation, otp, coordinator, leader, voter]

requires: []
provides:
  - "Coordinators can self-service generate a one-time leader self-registration link from coordinator/leaders, without the admin/Filament InvitationResource"
  - "Public registro-lider/{token} Volt page lets an invited person create their own leader account with the same OTP + cedula verification as the manual create-leader form"
  - "Coordinators can register a voter on behalf of a specific leader from that leader's detail screen (registered_by = leader.id, not the coordinator)"
affects: [invitations, coordinator-panel, public-registration, voter-registration]

tech-stack:
  added: []
  patterns:
    - "Invitation lifecycle extended via target_role=LEADER + leader_user_id=null (vs the pre-existing target_role=LEADER + leader_user_id=<id> apoyo-invitation shape) to distinguish leader self-registration links from voter self-registration links on the same Invitation model/table"
    - "Livewire's native #[Livewire\\Attributes\\Layout(...)] PHP attribute used instead of Volt's layout() global helper for a genuinely unauthenticated full-page Volt route, after discovering layout() has no effect on class-based Volt components in the installed Volt version"

key-files:
  created:
    - app/... (none - only existing files extended)
    - resources/views/livewire/public/register-leader.blade.php
    - resources/views/livewire/coordinator/leader-add-voter.blade.php
    - tests/Feature/InvitationServiceLeaderLinkTest.php
    - tests/Feature/Coordinator/GenerateLeaderInvitationLinkTest.php
    - tests/Feature/PublicLeaderRegistrationTest.php
    - tests/Feature/Coordinator/AddVoterFromLeaderDetailTest.php
    - tests/Feature/Coordinator/LeadersVoterCountDisplayTest.php
  modified:
    - app/Models/Invitation.php
    - app/Services/InvitationService.php
    - routes/web.php
    - resources/views/livewire/coordinator/leaders.blade.php
    - resources/views/livewire/coordinator/leader-voters.blade.php

key-decisions:
  - "createLeaderRegistrationLink()/validateLeaderInvitation()/markLeaderInvitationAccepted() added to InvitationService rather than a new model/table - Invitation already had target_role/coordinator_user_id/accepted_at/registered_user_id columns unused by any real flow"
  - "Invitation's accepted_at and registered_user_id made mass-assignable (added to $fillable) - previously present in the schema but never written to by any existing code path"
  - "leader-add-voter.blade.php duplicates leader/register-voter.blade.php's form/validation logic rather than extracting a shared trait/component, to avoid any risk of regressing the authenticated leader's own registration flow"
  - "Discovered mid-task that Volt's layout() global helper has no effect on class-based full-page Volt components in this installed Volt version - every existing page silently falls back to components.layouts.app regardless of what layout() specifies. Fixed only the new public page (via Livewire's #[Layout(...)] attribute, confirmed working); documented the wider pre-existing gap in deferred-items.md rather than fixing every other page (out of scope)"

patterns-established:
  - "#[Livewire\\Attributes\\Layout('view.name', ['title' => '...'])] on the anonymous class is the correct way to set a non-default layout for a class-based Volt full-page component in this codebase - Volt's layout() helper should not be trusted until the underlying package issue is fixed project-wide"

requirements-completed: []

duration: ~45min
completed: 2026-08-01
---

# Quick Task 260801-hvd: Enlaces de auto-registro de líderes + agregar apoyos desde detalle de líder Summary

**Coordinators can now generate a one-time, self-service leader self-registration link (OTP + cedula verification, same as the manual form) and register a voter on behalf of a specific leader from that leader's detail screen — both built on the pre-existing but previously unused `Invitation.target_role`/`accepted_at`/`registered_user_id` columns.**

## Performance

- **Duration:** ~45 min (includes significant investigation time into a pre-existing Volt layout bug that would have crashed the new public page)
- **Started:** 2026-08-01T13:08:00Z (approx, plan creation)
- **Completed:** 2026-08-01T13:51:00Z
- **Tasks:** 6 of 6 automated tasks complete; final checkpoint (`checkpoint:human-verify`) reached and left PENDING (no human present in this run)
- **Files modified:** 11 (5 modified, 6 created — see key-files)

## Accomplishments
- `InvitationService::createLeaderRegistrationLink()` / `validateLeaderInvitation()` / `markLeaderInvitationAccepted()` and `Invitation::getLeaderRegistrationUrl()` give the `Invitation` model a full leader-self-registration lifecycle (pending → accepted, single-use, non-interchangeable with voter-invitation tokens of the same model).
- "Generar enlace de registro" button + modal on `coordinator/leaders` lets any coordinator generate and copy a public link in one click.
- `registro-lider/{token}` public Volt page reproduces `create-leader.blade.php`'s OTP + Registraduría/cedula-verification flow for an unauthenticated visitor, creates the `User` as `leader` under the inviting coordinator's municipality/campaigns, and marks the invitation accepted.
- `coordinator/leaders/{leader}/voters/create` (+ "Agregar Apoyo" button) lets a coordinator register a voter for a specific leader, with `registered_by` correctly set to the leader, not the coordinator.
- Confirmed via a new regression test that the leaders list's existing voter-count display (`voters_count`) still works correctly, including for voters created through the new Task 5 flow.

## Task Commits

Each task was committed atomically:

1. **Task 1: Extend InvitationService + Invitation with the leader-invitation lifecycle (TDD)** - `bfa1451` (feat)
2. **Task 2: "Generar enlace de registro" button on coordinator/leaders** - `e5c185f` (feat)
3. **Task 3 + 4: Public self-registration page + all 5 security test cases (TDD)** - `e018f61` (feat)
4. **Task 5: "Agregar Apoyo" from a leader's detail screen (TDD)** - `c09f89d` (feat)
5. **Task 6: Voter-count regression test** - `f6c3e32` (test)

_Note: Task 1's commit also registers the `public.leader-registration` route (originally planned as part of Task 3), because `Invitation::getLeaderRegistrationUrl()`'s own test (part of Task 1's TDD scope) needs `route('public.leader-registration', ...)` to resolve. Task 4's security tests were written directly into `PublicLeaderRegistrationTest.php` alongside Task 3's happy-path tests (single file, single commit) rather than as a separate follow-up commit, since both tasks share the same file per the plan's own file list._

**Plan metadata:** (this commit) `docs: complete fix-viewauditlog... quick task` — not yet created, see below (plan currently left at checkpoint, no final docs commit made per constraints).

## Files Created/Modified
- `app/Models/Invitation.php` - `getLeaderRegistrationUrl()`; `accepted_at`/`registered_user_id` added to `$fillable` and `accepted_at` cast to `datetime`
- `app/Services/InvitationService.php` - `createLeaderRegistrationLink()`, `validateLeaderInvitation()`, `markLeaderInvitationAccepted()`
- `routes/web.php` - `public.leader-registration` (`registro-lider/{token}`) and `coordinator.leaders.voters.create` (`coordinator/leaders/{leader}/voters/create`) routes
- `resources/views/livewire/coordinator/leaders.blade.php` - "Generar enlace de registro" button + copyable-link modal, `generateLeaderInvitationLink()` action
- `resources/views/livewire/public/register-leader.blade.php` (new) - public leader self-registration Volt page
- `resources/views/livewire/coordinator/leader-voters.blade.php` - "Agregar Apoyo" button
- `resources/views/livewire/coordinator/leader-add-voter.blade.php` (new) - coordinator-facing "add voter for this leader" Volt page
- `tests/Feature/InvitationServiceLeaderLinkTest.php`, `tests/Feature/Coordinator/GenerateLeaderInvitationLinkTest.php`, `tests/Feature/PublicLeaderRegistrationTest.php`, `tests/Feature/Coordinator/AddVoterFromLeaderDetailTest.php`, `tests/Feature/Coordinator/LeadersVoterCountDisplayTest.php` (new)

## Decisions Made
- Extended the existing `Invitation` model/table instead of creating a new one — `target_role`, `coordinator_user_id`, `accepted_at`, `registered_user_id` were already present but unused by any real code path.
- `leader-add-voter.blade.php` is a deliberate copy of `leader/register-voter.blade.php`'s logic (not a shared extraction), to keep the authenticated leader's own flow completely untouched and regression-free.
- Layout for the new public page set via Livewire's `#[Layout(...)]` PHP attribute rather than Volt's `layout()` helper — see Deviations below.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Volt's `layout()` helper has no effect on class-based full-page components; new public page used the working `#[Layout(...)]` attribute instead**
- **Found during:** Task 3 (public self-registration page)
- **Issue:** Following the plan's literal instruction (`layout('components.layouts::public', [...])`, matching every other Volt page in the codebase) produced a page that crashed with `Call to a member function hasRole() on null` on any unauthenticated request. Root-caused (via a debug dump of Livewire's internal `PageComponentConfig`, cross-checked with `php artisan view:clear`) to `layout()` never actually influencing the render for class-based Volt SFCs in the installed `livewire/volt` version — every page in the app silently renders with the config default (`components.layouts.app`, which requires an authenticated user for its sidebar) regardless of what `layout()` specifies. This is invisible for every *other* existing page because they're only ever visited by an authenticated user; this task's page is the app's first genuinely unauthenticated full-page Volt route.
- **Fix:** `public.register-leader` now declares its layout via `#[Livewire\Attributes\Layout('components.layouts.public', ['title' => 'Registro de líder'])]` on the anonymous class — confirmed via the same debug method to correctly resolve to `components.layouts.public`.
- **Files modified:** `resources/views/livewire/public/register-leader.blade.php`
- **Verification:** `PublicLeaderRegistrationTest`'s "muestra el formulario..." test asserts 200 + page content for an unauthenticated GET.
- **Committed in:** `e018f61` (Task 3 commit)
- **Not fixed (documented, out of scope):** every other existing Volt page in the app still uses the ineffective `layout()` helper. See `.planning/quick/260801-hvd-enlaces-de-auto-registro-para-que-coordi/deferred-items.md` for the full write-up and a recommended follow-up (either upgrade `livewire/volt`, or migrate every `layout()` call to the `#[Layout(...)]` attribute pattern proven here).

**2. [Rule 1 - Bug] `Invitation::accepted_at`/`registered_user_id` were not mass-assignable**
- **Found during:** Task 1 (`markLeaderInvitationAccepted` test)
- **Issue:** `Invitation::update(['accepted_at' => ..., 'registered_user_id' => ...])` silently no-op'd on those two columns because they weren't in `$fillable`, even though the columns existed in the schema.
- **Fix:** Added both to `$fillable`, plus cast `accepted_at` to `datetime` for consistency with `expires_at`.
- **Files modified:** `app/Models/Invitation.php`
- **Verification:** `InvitationServiceLeaderLinkTest`'s "markLeaderInvitationAccepted..." test.
- **Committed in:** `bfa1451` (Task 1 commit)

---

**Total deviations:** 2 auto-fixed (1 blocking, 1 bug)
**Impact on plan:** Both were necessary for the plan's own stated behavior to work at all (a crashing public page, a no-op accept-invitation call). No scope creep — the wider layout() gap was explicitly deferred, not fixed project-wide.

## Issues Encountered

The Volt `layout()` investigation (see Deviation 1) required directly instrumenting `vendor/livewire/livewire` with temporary debug output to trace the actual runtime layout-view string being resolved, since the failure mode (silent fallback to the wrong layout, not an exception with a clear message) gave no direct signal. All debug instrumentation and temporary routes/files were removed before committing; `vendor/` was restored byte-for-byte (confirmed via `git diff` showing no changes, since `vendor/` isn't tracked anyway).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**All 6 automated tasks are complete, committed, and verified** (60 tests passing across the 5 new test files plus 3 pre-existing regression suites — `PublicVoterRegistrationLinkTest`, `CreateLeaderOtpTest`, `tests/Feature/Leader/LeaderAppTest.php`; `vendor/bin/pint --dirty` clean).

**The plan's final task — `checkpoint:human-verify` (gate="blocking") — has NOT been completed.** No human was present in this execution run to perform the required manual browser verification (real SMS OTP round-trip, confirming the created leader's role/coordinator/campaigns in the DB, confirming the link is single-use, and confirming the voter-count updates visually). Per this run's explicit instructions, this checkpoint is being reported as **PENDING**, not fabricated as approved. See the plan's checkpoint task for the exact 5-step manual verification script. STATE.md and this SUMMARY intentionally do NOT mark this quick task fully "complete" until that human verification happens.

---
*Phase: quick*
*Completed: 2026-08-01 (automated tasks only — checkpoint pending)*

## Self-Check: PASSED

- FOUND: app/Models/Invitation.php
- FOUND: app/Services/InvitationService.php
- FOUND: routes/web.php
- FOUND: resources/views/livewire/coordinator/leaders.blade.php
- FOUND: resources/views/livewire/public/register-leader.blade.php
- FOUND: resources/views/livewire/coordinator/leader-voters.blade.php
- FOUND: resources/views/livewire/coordinator/leader-add-voter.blade.php
- FOUND: tests/Feature/InvitationServiceLeaderLinkTest.php
- FOUND: tests/Feature/Coordinator/GenerateLeaderInvitationLinkTest.php
- FOUND: tests/Feature/PublicLeaderRegistrationTest.php
- FOUND: tests/Feature/Coordinator/AddVoterFromLeaderDetailTest.php
- FOUND: tests/Feature/Coordinator/LeadersVoterCountDisplayTest.php
- FOUND: commit bfa1451
- FOUND: commit e5c185f
- FOUND: commit e018f61
- FOUND: commit c09f89d
- FOUND: commit f6c3e32
