---
phase: quick
plan: 260731-jmq
type: execute
wave: 1
depends_on: []
files_modified: [.planning/STATE.md]
autonomous: true
requirements: []
must_haves:
  truths:
    - "A developer reading STATE.md's Blockers/Concerns section learns that startLiveLookup() does not fall through to the next live adapter when a reachable adapter's startLookup() call itself throws"
    - "The entry explains WHY isReachable() and startLookup() can diverge for the same adapter (external probe_url vs internal service_url are different network paths)"
    - "The entry records the real 2026-07-31 production incident (ConsultaCensoService 404s caused by sigma-registraduria not yet redeployed) as concrete evidence, including that it was resolved by redeploying sigma-registraduria (symptom fix, not code fix)"
    - "The entry states the recommended future fix (catch startLookup() failure, try next adapter, bounded) without implementing it"
    - "No application code (PollingPlaceResolver.php, ConsultaCensoService.php, HasRegistraduriaPolling.php) is modified"
  artifacts:
    - path: ".planning/STATE.md"
      provides: "New Blockers/Concerns bullet documenting the startLiveLookup() fallback gap"
      contains: "startLiveLookup()"
  key_links:
    - from: ".planning/STATE.md ### Blockers/Concerns"
      to: "app/Services/PollingPlaceResolver.php startLiveLookup()"
      via: "documentation reference (file/line, not code change)"
      pattern: "startLiveLookup"
---

<objective>
Document a real production finding in STATE.md: `PollingPlaceResolver::startLiveLookup()` picks the first reachable live adapter and calls `startLookup()` on it directly — if that call throws (rather than `isReachable()` returning false), the exception propagates uncaught instead of falling through to the next adapter in priority order. This caused a real incident in production today (2026-07-31) when `ConsultaCensoService`'s external probe was up but its internal `sigma-registraduria` microservice route wasn't yet deployed, causing hard failures on the interactive lookup path for real users.

Purpose: Preserve this finding as institutional knowledge so a future phase/quick-task can implement the recommended fix (bounded fallback-on-exception in `startLiveLookup()`), and so nobody rediscovers this the hard way in a future incident.
Output: One new dense bullet entry appended to `.planning/STATE.md`'s `### Blockers/Concerns` section, matching the exact style/density of existing entries there.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

This is a documentation-only quick task. No application code is read as implementation input beyond confirming the finding is accurate (already confirmed during planning — see interfaces below). No PROJECT.md/ROADMAP.md context needed; this is a standalone STATE.md edit.
</context>

<interfaces>
<!-- Confirmed during planning: exact current code at app/Services/PollingPlaceResolver.php (~line 41-50) -->

```php
/**
 * Starts a lookup on the first reachable adapter, in priority order (LIVE-01).
 * Skips unreachable adapters rather than blindly using the first one, so priority
 * order only applies among adapters that are actually up.
 */
public function startLiveLookup(string $cedula): string
{
    foreach ($this->liveAdapters as $adapter) {
        if ($adapter->isReachable()) {
            return $adapter->startLookup($cedula);
        }
    }

    throw new \RuntimeException('No live source adapters configured.');
}
```

This is the exact mechanism to reference in the STATE.md entry: the loop returns/throws on the FIRST reachable adapter's `startLookup()` result — it does not catch a `startLookup()` exception and continue to the next adapter.
</interfaces>

<tasks>

<task type="auto">
  <name>Task 1: Append startLiveLookup() fallback-gap finding to STATE.md Blockers/Concerns</name>
  <files>.planning/STATE.md</files>
  <action>
    Open `.planning/STATE.md` and locate the `### Blockers/Concerns` section (currently ends with the `260730-cs3` `CampaignContext` test-pollution bullet, just before `### Pending Todos`). Append ONE new bullet at the end of this section, in the same dense-paragraph style as the existing entries (bold lead-in phrase, concrete file/line references, root cause, real-world impact, recommended fix, explicitly not-yet-fixed). The new bullet must cover, in prose:

    1. **The mechanism**: `PollingPlaceResolver::startLiveLookup(string $cedula): string` (`app/Services/PollingPlaceResolver.php`, ~line 41-50) loops over `$this->liveAdapters`, and for the FIRST adapter where `isReachable()` returns true, calls `$adapter->startLookup($cedula)` and returns/throws immediately — it does NOT catch a `startLookup()` exception and try the next adapter. Contrast with `isLiveReachable()` just above it, which loops through ALL adapters checking only the cheap, side-effect-free `isReachable()` probe.
    2. **Why they diverge**: `isReachable()` probes the real EXTERNAL Registraduría-hosted site (`config('services.consulta_censo.probe_url')` etc.) while `startLookup()` calls the INTERNAL Python microservice proxy (`config('services.consulta_censo.url')`, e.g. `http://sigma-registraduria:5757/lookup/censo`) — a different network path. An adapter can be "reachable" while its internal proxy path is broken.
    3. **Real incident (2026-07-31)**: after deploying quick tasks 260731-ezk through 260731-i5g (added ConsultaCensoService as 3rd live adapter + config fallback), `sigma-registraduria` (separate Dokploy service, autoDeploy=false) had not yet been redeployed with the new `/lookup/censo` route. The external probe was up so `isReachable()` returned true, `startLiveLookup()` committed to `ConsultaCensoService::startLookup()`, which 404'd against the internal proxy and threw — uncaught all the way up through `HasRegistraduriaPolling::openRegistraduriaBrowser()`'s outer try/catch, which just shows a hard "Error al conectar con el servicio" notification and never reaches the DB-reconstruction/national-snapshot fallback tiers (gated behind `isLiveReachable()`, which was true). Confirmed via `storage/logs/laravel.log`: 8 "ConsultaCensoService: lookup failed" 404 entries for real cédulas in sigma-betha production, between the deploy and the manual `sigma-registraduria` redeploy minutes later that fixed it (rebuilt image from git checkout at `/etc/dokploy/applications/sigma-registraduria/code/registraduria-service` via direct SSH, verified in an isolated test container first, tagged prior image `sigma-registraduria:rollback-260731` for rollback safety, then `docker service update --force --image sigma-registraduria:latest sigma-registraduria`). This fixed the SYMPTOM, not the underlying code gap.
    4. **Not adapter-specific**: the same class of bug exists for InfovotantesService and RegistraduriaService — any adapter whose external probe_url and internal service_url are decoupled.
    5. **Recommended future fix (not yet implemented)**: `startLiveLookup()` should catch a `startLookup()` failure on one adapter and continue trying the next reachable adapter (bounded) before finally throwing, mirroring the resilience `attemptLiveAutomated()` already has for the automated/headless cascade (covered by `PollingPlaceResolverPriorityTest`). This would make the interactive modal path (`openRegistraduriaBrowser`/`forceRefreshFromRegistraduria`) as resilient as the automated path already is.

    Match the existing entries' voice: written as a completed retrospective finding, not an instruction. Do not modify any other section of STATE.md. Do not touch `last_updated` frontmatter or any other file.
  </action>
  <verify>
    <automated>grep -c "startLiveLookup" .planning/STATE.md</automated>
  </verify>
  <done>STATE.md's Blockers/Concerns section contains exactly one new bullet documenting the startLiveLookup() fallback gap, referencing PollingPlaceResolver.php, the 2026-07-31 ConsultaCensoService incident, and the recommended (unimplemented) fix; no application code files were modified.</done>
</task>

</tasks>

<verification>
- `git diff --stat` shows only `.planning/STATE.md` changed.
- `grep -n "startLiveLookup" .planning/STATE.md` returns the new bullet plus any pre-existing references.
- No test run needed — documentation-only change, explicitly exempted from CLAUDE.md's test-enforcement rule since no executable code was modified.
</verification>

<success_criteria>
`.planning/STATE.md`'s `### Blockers/Concerns` section has one new entry (matching existing density/style) fully describing the `startLiveLookup()` fallback gap, its root cause, today's real incident, and the recommended future fix — with zero application code changes.
</success_criteria>

<output>
After completion, create `.planning/quick/260731-jmq-document-startlivelookup-fallback-gap-fi/260731-jmq-SUMMARY.md`
</output>
