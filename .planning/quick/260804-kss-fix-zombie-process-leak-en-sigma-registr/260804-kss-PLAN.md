---
phase: quick
plan: 260804-kss
type: execute
wave: 1
depends_on: []
files_modified:
  - registraduria-service/Dockerfile
autonomous: true
requirements: []

must_haves:
  truths:
    - "The registraduria-service Docker image, when run standalone (no --init flag, no manual docker service update stopgap), reaps orphaned/zombie child processes (e.g. defunct headless_shell processes from Chromium) automatically, because PID 1 inside the container is an init process (tini), not python3 directly"
    - "This fix is permanent and versioned in the Dockerfile itself — a future Dokploy-triggered rebuild (manual redeploy or, if autoDeploy is ever enabled, an automatic one) reproduces the fix with zero manual server-side flags required"
    - "app.py, requirements.txt, and all Python business logic are untouched — this is exclusively a container-runtime/build change"
  artifacts:
    - path: "registraduria-service/Dockerfile"
      provides: "ENTRYPOINT [\"/usr/bin/tini\", \"--\"] making tini PID 1, with CMD [\"python3\", \"app.py\"] passed through as tini's child process"
      contains: "ENTRYPOINT [\"/usr/bin/tini\", \"--\"]"
  key_links:
    - from: "registraduria-service/Dockerfile ENTRYPOINT"
      to: "registraduria-service/Dockerfile CMD"
      via: "Docker's native ENTRYPOINT+CMD combination — tini execs `python3 app.py` as its sole child and reaps any orphaned grandchildren (Chromium's headless_shell subprocesses)"
      pattern: "ENTRYPOINT \\[\"/usr/bin/tini\", \"--\"\\]\\s*\\nCMD \\[\"python3\", \"app.py\"\\]"
---

<objective>
Make `registraduria-service`'s Docker image reap zombie processes permanently, by installing `tini` and setting it as `ENTRYPOINT` (PID 1) in the versioned Dockerfile, replacing the manual/unversioned `docker service update --init` stopgap already applied directly on the production Swarm service.

Purpose: production's `sigma-registraduria` container accumulated ~14,485 defunct `[headless_shell]` zombie processes over 4 days because `python3` runs as PID 1 with no init/reaper — orphaned Chromium subprocesses are never collected. A manual `--init` flag was applied directly to the Swarm service today to confirm the root cause (zombies dropped to 0 immediately), but that flag lives only in Docker Swarm's runtime state, is NOT tracked by Dokploy's database for this app (confirmed: no `initSwarm`-equivalent column exists, unlike `restartPolicySwarm`/`placementSwarm`), and will silently disappear on the next Dokploy-triggered rebuild. This plan makes the fix permanent and code-reviewable by baking `tini` into the image itself.

Output:
- `registraduria-service/Dockerfile`: adds `tini` via `apt-get install` (confirmed NOT preinstalled in the `mcr.microsoft.com/playwright/python:v1.50.0-noble` base image — see verification already performed below) and sets it as `ENTRYPOINT`, with the existing `CMD ["python3", "app.py"]` left unchanged.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

<interfaces>
Current `registraduria-service/Dockerfile` (full file, 14 lines):

    FROM mcr.microsoft.com/playwright/python:v1.50.0-noble

    # Noble = Ubuntu 24.04 with Playwright + Chromium preinstalled
    # No Xvfb needed — headless=True works for browser fetch() calls

    WORKDIR /app
    COPY requirements.txt .
    RUN pip install --no-cache-dir -r requirements.txt

    COPY app.py .

    EXPOSE 5757

    CMD ["python3", "app.py"]

**Pre-verified facts (already confirmed live in this planning session by actually pulling the image, installing the package, and running a full build+run test — do NOT re-investigate, just implement):**
- `tini` is NOT preinstalled in `mcr.microsoft.com/playwright/python:v1.50.0-noble` (base is Ubuntu 24.04.1 LTS "Noble"). Confirmed via `docker run --rm mcr.microsoft.com/playwright/python:v1.50.0-noble sh -c 'which tini; dpkg -l | grep -i tini'` — empty result, no package installed.
- `apt-get update && apt-get install -y --no-install-recommends tini` installs cleanly from Ubuntu Noble's standard `universe` repo (no extra apt sources needed). Confirmed package: `tini 0.19.0-1`.
- The installed binary's real path is exactly `/usr/bin/tini` (confirmed via `which tini` post-install; `tini --version` -> `tini version 0.19.0`).
- A full end-to-end test was already run in this planning session using this exact Dockerfile shape (base image + pip install + the new tini install RUN + `ENTRYPOINT ["/usr/bin/tini", "--"]` + unchanged `CMD ["python3", "app.py"]`): the image built successfully, and `docker exec <container> ps aux` on the running container showed PID 1 = `/usr/bin/tini -- python3 app.py` with `python3 app.py` as a separate child PID — confirming PID 1 is `tini`, not `python3` (exactly the fix needed: tini will now reap orphaned Chromium subprocesses that were previously becoming permanent zombies under PID 1's non-reaping `python3`). The base image is already pulled/cached locally from this test, so the executor's own build will be fast (no re-pull of the ~3.4GB base layer needed).
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add tini as PID 1 init in registraduria-service's Dockerfile and verify with a real build + run</name>
  <files>registraduria-service/Dockerfile</files>
  <action>
Edit `registraduria-service/Dockerfile` to install `tini` and use it as `ENTRYPOINT`, keeping every other line unchanged. Insert the new `RUN apt-get install` line after the `pip install` step and before `COPY app.py .`, then add `ENTRYPOINT` immediately before the existing `CMD` line. Final file:

    FROM mcr.microsoft.com/playwright/python:v1.50.0-noble

    # Noble = Ubuntu 24.04 with Playwright + Chromium preinstalled
    # No Xvfb needed — headless=True works for browser fetch() calls

    WORKDIR /app
    COPY requirements.txt .
    RUN pip install --no-cache-dir -r requirements.txt

    # tini as PID 1 reaps orphaned Chromium subprocesses (headless_shell zombies) —
    # without an init process, python3 as PID 1 never collects them (root cause of
    # ~14,485 accumulated defunct processes found in production 2026-08-04).
    RUN apt-get update && apt-get install -y --no-install-recommends tini && rm -rf /var/lib/apt/lists/*

    COPY app.py .

    EXPOSE 5757

    ENTRYPOINT ["/usr/bin/tini", "--"]
    CMD ["python3", "app.py"]

Do NOT modify `app.py`, `requirements.txt`, `EXPOSE`, `WORKDIR`, or the `pip install` line — this is exclusively a Dockerfile change. `tini` is confirmed NOT preinstalled in this base image (see `<interfaces>` above) so the `apt-get install` step is required, not optional.

After editing, rebuild and verify locally (this exact sequence was already run successfully once during planning with an equivalent Dockerfile — repeat it here against the real, final committed file to confirm it too works):

1. Build: `docker build -t sigma-registraduria-tini-check registraduria-service/`
2. Run detached: `docker run -d --name tini-check sigma-registraduria-tini-check`
3. Give the Python process a moment to start, then inspect PID 1: `sleep 2 && docker exec tini-check ps aux` — expect PID 1's COMMAND to be `/usr/bin/tini -- python3 app.py` (or equivalent), with `python3 app.py` as a separate, non-PID-1 child. Cross-check with `docker exec tini-check cat /proc/1/comm` — expect output exactly `tini`.
4. Clean up regardless of outcome: `docker rm -f tini-check` and `docker rmi sigma-registraduria-tini-check` (do not leave test images/containers behind).

If step 3 shows `python3` (not `tini`) as PID 1, the ENTRYPOINT/CMD split is wrong — do not mark this task done until PID 1 is confirmed to be tini.
  </action>
  <verify>
    <automated>grep -qF 'ENTRYPOINT ["/usr/bin/tini", "--"]' registraduria-service/Dockerfile && grep -qF 'CMD ["python3", "app.py"]' registraduria-service/Dockerfile && docker build -t sigma-registraduria-tini-check registraduria-service/ && docker run -d --name tini-check sigma-registraduria-tini-check && sleep 2 && test "$(docker exec tini-check cat /proc/1/comm)" = "tini" && echo PID1_IS_TINI_CONFIRMED; RC=$?; docker rm -f tini-check >/dev/null 2>&1; docker rmi sigma-registraduria-tini-check >/dev/null 2>&1; exit $RC</automated>
  </verify>
  <done>
`registraduria-service/Dockerfile` contains both `ENTRYPOINT ["/usr/bin/tini", "--"]` and the unchanged `CMD ["python3", "app.py"]`. A real `docker build` of the modified Dockerfile succeeds, and a running container from that image has `tini` as PID 1 (confirmed via `/proc/1/comm` and cross-checked via `ps aux`), with `python3 app.py` running as a non-PID-1 child. `app.py` and `requirements.txt` are byte-identical to before this task (confirm via `git diff registraduria-service/app.py registraduria-service/requirements.txt` showing zero output). Test image/container cleaned up, no leftover `sigma-registraduria-tini-check` artifacts.
  </done>
</task>

</tasks>

<verification>
git diff registraduria-service/Dockerfile -- confirm only the `RUN apt-get install ... tini` line and the `ENTRYPOINT [...]` line were added; every other line unchanged.
git diff registraduria-service/app.py registraduria-service/requirements.txt -- confirm zero output (neither file touched).
docker build -t sigma-registraduria-tini-check registraduria-service/ -- confirm the image builds successfully with the new Dockerfile.
docker run -d --name tini-check sigma-registraduria-tini-check && sleep 2 && docker exec tini-check cat /proc/1/comm -- confirm output is exactly `tini`; then `docker rm -f tini-check && docker rmi sigma-registraduria-tini-check` to clean up.
</verification>

<success_criteria>
- `registraduria-service/Dockerfile` uses `tini` (installed via `apt-get`) as `ENTRYPOINT`, with `CMD ["python3", "app.py"]` unchanged and passed through as tini's child.
- A real local `docker build` + `docker run` of the modified image proves PID 1 is `tini`, not `python3` — the exact condition that lets orphaned Chromium `headless_shell` subprocesses get reaped instead of accumulating as zombies.
- Zero changes to `app.py`, `requirements.txt`, or any Python business logic.
- No test images/containers left behind on the local Docker daemon after verification.
</success_criteria>

<output>
After completion, create `.planning/quick/260804-kss-fix-zombie-process-leak-en-sigma-registr/260804-kss-SUMMARY.md`. In the summary, explicitly flag: this fix is NOT yet deployed to production — `sigma-registraduria` has `autoDeploy=false` in Dokploy, so shipping this change requires either (a) triggering a manual deploy from the Dokploy panel for the `sigma-registraduria` application, or (b) repeating the manual build + `docker service update --image ... sigma-registraduria` process already used today as the stopgap, now using this Dockerfile so the fix is baked into the image instead of relying on the Swarm-level `--init` flag applied earlier. Also note that the earlier manual `docker service update --init sigma-registraduria` stopgap can be left in place harmlessly (redundant with tini, doesn't conflict) or removed once this image is deployed — either is safe.
</output>
