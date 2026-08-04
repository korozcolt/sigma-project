---
phase: quick
plan: 260804-kss
subsystem: infra
tags: [docker, tini, zombie-process, registraduria-service, dockerfile]

# Dependency graph
requires: []
provides:
  - "registraduria-service/Dockerfile now installs tini and runs it as ENTRYPOINT (PID 1), permanently reaping orphaned headless_shell/Chromium zombie subprocesses"
affects: [registraduria-service, infra-deploys]

# Tech tracking
tech-stack:
  added: [tini (0.19.0-1, via apt-get, Ubuntu Noble universe repo)]
  patterns: ["ENTRYPOINT [init-binary] + CMD [app] for zombie-reaping in any container running a process that spawns short-lived children (Chromium/Playwright subprocesses)"]

key-files:
  created: []
  modified: [registraduria-service/Dockerfile]

key-decisions:
  - "Used apt-get install tini (0.19.0-1 from Ubuntu Noble's universe repo) rather than downloading a static tini binary — cleaner, matches base image's package manager, already confirmed to install cleanly during planning."
  - "ENTRYPOINT + unchanged CMD split (not a single combined ENTRYPOINT) so tini execs `python3 app.py` as its sole child, preserving the existing CMD as documentation/override point."

patterns-established:
  - "tini-as-ENTRYPOINT pattern for any Sigma container that spawns Playwright/Chromium subprocesses and needs proper zombie reaping."

requirements-completed: []

# Metrics
duration: 10min
completed: 2026-08-04
---

# Quick Task 260804-kss: Fix zombie process leak in sigma-registraduria Summary

**Added `tini` as `ENTRYPOINT` (PID 1) in `registraduria-service/Dockerfile`, replacing an unversioned Swarm-level `--init` stopgap, so orphaned `headless_shell` Chromium subprocesses are reaped automatically on every rebuild.**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-08-04T19:56:00Z (approx)
- **Completed:** 2026-08-04T20:06:42Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- `registraduria-service/Dockerfile` installs `tini` via `apt-get` and sets `ENTRYPOINT ["/usr/bin/tini", "--"]`, with the existing `CMD ["python3", "app.py"]` unchanged and passed through as tini's sole child process.
- Verified end-to-end with a real `docker build` + `docker run`: `/proc/1/comm` returns exactly `tini`, and `ps aux` confirms `python3 app.py` runs as a non-PID-1 child (PID 7) of tini (PID 1).
- Confirmed `app.py` and `requirements.txt` are byte-identical to before this change (`git diff` empty for both).
- Test image and container (`sigma-registraduria-tini-check` / `tini-check`) fully cleaned up afterward — no leftover Docker artifacts.

## Task Commits

Each task was committed atomically:

1. **Task 1: Add tini as PID 1 init in registraduria-service's Dockerfile and verify with a real build + run** - `a2d76fd` (fix)

**Plan metadata:** (this SUMMARY + STATE.md update, committed separately below)

## Files Created/Modified
- `registraduria-service/Dockerfile` - Added `RUN apt-get update && apt-get install -y --no-install-recommends tini && rm -rf /var/lib/apt/lists/*` after the `pip install` step, and `ENTRYPOINT ["/usr/bin/tini", "--"]` immediately before the existing `CMD ["python3", "app.py"]`.

## Decisions Made
- No architectural deviation — implemented exactly the Dockerfile shape specified in the plan (already pre-verified during the planning session with an equivalent build).
- Used the local `registraduria-service/.env`'s real `TWO_CAPTCHA_KEY` (via `--env-file`) for the verification-only test container, since `app.py` exits immediately without this required env var — this is a local-verification detail only, no file changes, and the test container/image was deleted afterward.

## Deviations from Plan

None — plan executed exactly as written. The only wrinkle was that the plan's literal verification command (`docker run -d --name tini-check sigma-registraduria-tini-check` with no env vars) causes the container to exit immediately because `app.py` requires `TWO_CAPTCHA_KEY` at import time; this is pre-existing `app.py` behavior, unrelated to the Dockerfile change under test. Passed `--env-file registraduria-service/.env` for the verification run only (no file changes) so the container stayed up long enough to inspect PID 1. Result matches the plan's expected outcome exactly (`tini` as PID 1, `python3 app.py` as a child).

## Issues Encountered
None beyond the verification-only wrinkle documented above.

## User Setup Required

**IMPORTANT — This fix is NOT yet deployed to production.**

`sigma-registraduria` has `autoDeploy=false` in Dokploy, so this commit alone does not ship the fix to the running production container. To deploy it, either:
1. Trigger a manual deploy from the Dokploy panel for the `sigma-registraduria` application, or
2. Repeat the manual build + `docker service update --image ... sigma-registraduria` process used as today's earlier stopgap, now using this updated Dockerfile so the fix is baked into the image itself instead of relying on the Swarm-level `--init` flag.

The earlier manual `docker service update --init sigma-registraduria` stopgap applied directly to the Swarm service today can be safely left in place (redundant with tini, no conflict) or removed once this image is redeployed — either is safe. This manual redeploy step is intentionally left for the user/orchestrator to perform separately; it was not part of this quick task's scope.

## Next Phase Readiness
- Dockerfile fix is complete, committed, and locally verified — ready for a production redeploy whenever the user/orchestrator chooses to trigger one (see "User Setup Required" above).
- No blockers for any other in-flight work; this task touched only `registraduria-service/Dockerfile`.

---
*Phase: quick*
*Completed: 2026-08-04*

## Self-Check: PASSED

- FOUND: registraduria-service/Dockerfile
- FOUND: .planning/quick/260804-kss-fix-zombie-process-leak-en-sigma-registr/260804-kss-SUMMARY.md
- FOUND: a2d76fd (Task 1 commit)
