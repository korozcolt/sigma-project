---
phase: quick
plan: 260726-hq8
subsystem: api
tags: [hablame, sms, http-client, otp, pest]

requires: []
provides:
  - HablameSmsService::sendRaw()/send() now emit `priority`/`from` as root-level JSON keys (siblings of `messages`), matching Hablame's real API contract
  - HablameSmsService::getAccountInfo() targets the corrected /account/v5/info route
  - Permanent Log::info('Hablame SMS Raw Send Payload', ...) trail in sendRaw() for future debuggability of this exact bug class
affects: [otp-verification, sms-notifications]

tech-stack:
  added: []
  patterns:
    - "Hablame v5 /sms/v5/send payload: `priority`/`from`/`certificate`/`flash` are root-level siblings of `messages`, never nested inside messages[0] entries"

key-files:
  created: []
  modified:
    - app/Services/HablameSmsService.php
    - tests/Feature/HablameSmsServiceTest.php
    - tests/Feature/OtpVerificationServiceTest.php

key-decisions:
  - "Confirmed root cause exactly as reported: sendRaw() nested `priority` inside messages[0] instead of the payload root, and send() never sent `from` at all — both fixed per docs.hablame.co/reference/envio-sms-post and Hablame support's confirmation"
  - "getAccountInfo() route corrected from the 404ing /v5/account/info to /account/v5/info"
  - "Did NOT add certificate/flash keys — out of scope per the bug report; Hablame's API-side defaults for those are accepted as-is"
  - "Verified the fix against Hablame's REAL (non-mocked) API: real getAccountInfo() call (no 404), and a real transactional SMS sent to 3043978157 via sendRaw() — the logged real outgoing payload confirms `priority`/`from` are root-level keys, not nested"

requirements-completed: []

duration: 20min
completed: 2026-07-26
---

# Quick Task 260726-hq8: Fix Hablame SMS API Payload Priority Placement Summary

**Moved `priority`/`from` from inside `messages[0]` to the root of the JSON body POSTed to Hablame's `/sms/v5/send` in both `sendRaw()` (transactional OTP) and `send()` (bulk SMS), fixed `getAccountInfo()`'s 404ing route, and verified all three fixes against Hablame's real (non-mocked) live API.**

## Performance

- **Duration:** ~20 min (includes stale-worktree repair: fast-forward merge + composer install + .env copy, plus a second merge to pick up a race with a concurrent main-branch session)
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- `HablameSmsService::sendRaw()` now builds a `$requestBody` with `from` always at root, `priority` at root only when requested, and no longer puts `priority` inside the individual message object.
- `HablameSmsService::send()` now includes `from` at root; it never emits `priority` (bulk SMS is never transactional).
- `HablameSmsService::getAccountInfo()` now calls `{apiUrl}/account/v5/info` instead of the previously 404ing `{apiUrl}/v5/account/info`.
- Added a permanent `Log::info('Hablame SMS Raw Send Payload', ['body' => $requestBody])` immediately before the `sendRaw()` POST call — this is the exact debug trail that was missing when the prior mislocated fix shipped undetected.
- Rewrote the corresponding Pest assertions in `HablameSmsServiceTest.php` and `OtpVerificationServiceTest.php` to check `$request->data()['priority']` / `['from']` at the payload root (not inside `messages[0]`), and updated the account-info fake route. Confirmed RED against the old nested implementation before the GREEN fix, per TDD.
- Verified against Hablame's REAL API (not mocks):
  - `getAccountInfo()` against the live API returned `success: true` (no 404), confirming the route fix.
  - Sent a real transactional SMS via `sendRaw()` to `3043978157` with `priority: true`. Hablame accepted it (`statusId: 1`, in the recognized success set `{1, 102, 106}`).
  - Polled Hablame's real `/sms/v5/message/{id}` endpoint 3 times (10s apart); observed `messageStatus: 2` ("delivered to operator") each time, with the polled response itself echoing `"priority":true` on the sent message — device-level delivery (`messageStatus: 3`) was not observed within the ~30s polling window, which the plan explicitly treats as expected/non-blocking (Hablame's device delivery is asynchronous).
  - Inspected `storage/logs/laravel.log` and confirmed the real (non-mocked) `local.INFO` log entry: `{"body":{"from":"SIGMA","messages":[{"to":"3043978157","text":"..."}],"priority":true}}` — `priority`/`from` are top-level keys of `body`, and `body.messages[0]` has no `priority` key.

## Task Commits

1. **Task 1: Move priority/from to payload root in sendRaw()/send(), fix getAccountInfo() route, update tests** - `a02ba71` (fix)
2. **Task 2: Verify the fix against Hablame's REAL API** - no code changes (verification-only task; `storage/logs/laravel.log` inspected, not modified/committed)

## Files Created/Modified

- `app/Services/HablameSmsService.php` - `sendRaw()` builds `$requestBody` with `from`/`priority` at root and logs it; `send()` adds root-level `from`; `getAccountInfo()` targets `/account/v5/info`
- `tests/Feature/HablameSmsServiceTest.php` - Assertions on `priority`/`from` moved to the payload root; `getAccountInfo` fake route corrected
- `tests/Feature/OtpVerificationServiceTest.php` - OTP-flow assertion on `priority` moved to the payload root, proving `OtpVerificationService::generate()` -> `sendRaw()` is correct end-to-end

## Decisions Made

- Confirmed and fixed the exact root cause from the bug report: `priority` was nested inside `messages[0]` instead of at the payload root, and `from` was never sent by either `sendRaw()` or `send()`.
- `getAccountInfo()`'s route corrected from `/v5/account/info` to `/account/v5/info`.
- Deliberately left `certificate`/`flash` unset — Hablame's docs confirm both have acceptable API-side defaults and they were explicitly out of scope for this fix.
- Ran the plan's mandated real (non-mocked) API verification: this is the same class of check that would have caught the previous mislocated fix before it shipped, since that fix passed all mocked tests but was structurally wrong in the real JSON.

## Deviations from Plan

None - plan executed exactly as written, including the real-API verification step in Task 2.

## Issues Encountered

- This worktree (`agent-ab581a18c980cdfd4`) was stale at session start, same class of issue documented repeatedly in STATE.md's Blockers/Concerns: checked out at commit `403e0f0` (pre-dating this quick task's plan entirely), missing `vendor/`, `.env`, and Phase 11's plan/summary files. Confirmed `403e0f0` is a fast-forward ancestor of main's HEAD, resolved via `git stash` -> `git merge --ff-only` -> `git stash pop` (to preserve the in-progress test edits), then copied `.env` from the main checkout and ran `composer install`.
- Main advanced further (with unrelated commits) while this task's tests/implementation were already committed in the worktree, so the initial fast-forward-only merge attempt to pick up this quick task's own PLAN.md failed with "Diverging branches can't be fast-forwarded." Resolved with a regular (non-ff) merge after confirming no file overlap with the new main commits — completed cleanly, no conflicts.

## User Setup Required

None - no external service configuration required. `HABLAME_API_KEY`/`HABLAME_API_URL`/`HABLAME_FROM` were already configured in `.env` from a prior session.

## Next Phase Readiness

- The transactional OTP SMS flow (`OtpVerificationService::generate()` -> `HablameSmsService::sendRaw()`) is now structurally correct against Hablame's real API contract, confirmed by a live send/poll/log-inspection cycle, not just mocks.
- The new permanent payload log line means any future regression of this exact class (right value, wrong JSON location) will be immediately visible in `storage/logs/laravel.log` without needing to re-run a live API investigation.
- No blockers for closing out this quick task.

---
*Phase: quick*
*Completed: 2026-07-26*

## Self-Check: PASSED

- FOUND: app/Services/HablameSmsService.php
- FOUND: tests/Feature/HablameSmsServiceTest.php
- FOUND: tests/Feature/OtpVerificationServiceTest.php
- FOUND: SUMMARY.md at .planning/quick/260726-hq8-fix-hablame-sms-api-payload-priority-fro/260726-hq8-SUMMARY.md
- FOUND: commit a02ba71 (Task 1)
