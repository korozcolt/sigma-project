---
phase: 09-live-source-feasibility-spike
verified: 2026-07-25T18:10:00Z
status: passed
score: 6/6 must-haves verified
---

# Phase 9: Live-Source Feasibility Spike Verification Report

**Phase Goal:** A time-boxed spike settles whether `wsp.registraduria.gov.co` (reCAPTCHA Enterprise) can serve as a real live-source adapter, end to end — without blocking the deterministic core.
**Verified:** 2026-07-25T18:10:00Z
**Status:** passed
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | `app.py`'s lookup flow targets `wsp.registraduria.gov.co` and extracts a fresh sitekey/`#token` live on every attempt (never hardcoded) | ✓ VERIFIED | `registraduria-service/app.py:82-88` calls `page.goto(WSP_PAGE_URL)` then `page.get_attribute(".g-recaptcha", "data-sitekey")` and `page.get_attribute("#token", "value")` inside `_lookup_async()`, per-invocation, with no hardcoded fallback |
| 2 | A zero-cost dry run confirmed the live DOM contract (sitekey + `#token`) before real budget was spent | ✓ VERIFIED | 09-01-SUMMARY.md documents the smoke test result (`SMOKE_TEST_OK`, sitekey 44 chars, token 32 chars) after diagnosing and fixing a WAF false-negative; no 2captcha calls made during this check |
| 3 | At least one real cédula is submitted end-to-end against the live endpoint via a real solved 2captcha token | ✓ VERIFIED | 09-SPIKE-RESULTS.md Attempt Log: 30 real `/lookup` calls logged with timestamps, 29 `success` outcomes carrying genuine `data.message` HTML (`#consulta` results table) across all 3 locked cédulas |
| 4 | Every attempt's outcome is classified into one of the five explicit states — a solved token is never treated as success on its own | ✓ VERIFIED | `app.py:168-182` only sets `outcome="success"` after parsing `result.get("success")` from the real AJAX JSON body, never from the captcha token alone; all 30 logged rows use only `success`/`denied_by_score` (grep-checked, no other free-text values) |
| 5 | The spike stayed within the ~20-30 solve budget and stopped at exhaustion or a hard blocker | ✓ VERIFIED | Exactly 30 rows logged (D-04 ceiling), no hard blocker triggered (0 `source_unreachable` rows), 09-02-SUMMARY.md documents the deliberate choice to run the full budget since cost was trivial |
| 6 | A documented go/no-go decision for adopting wsp exists, and the milestone's resilient core is unaffected regardless of outcome | ✓ VERIFIED | 09-SPIKE-RESULTS.md contains `Verdict: GO` with rationale; Scope Note explicitly confirms `RegistraduriaService.php`/`LiveSourceAdapter.php`/`REGISTRADURIA_LIVE_ENABLED` were untouched — confirmed independently via `git diff a4c3f8c bda7895 -- app/ config/` returning empty |

**Score:** 6/6 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `registraduria-service/app.py` | wsp-targeting lookup flow: sitekey/#token extraction, 2captcha solve with enterprise toggle, token injection via `#enviar`, five-state outcome classification | ✓ VERIFIED | All 9 acceptance-criteria greps pass (`wsp.registraduria.gov.co/censo/consultar`, sitekey/token selectors, `g-recaptcha-response`, `#enviar`, all 4 taxonomy strings, `"enterprise": "1"`, no `infovotantes`); `python3 -m py_compile app.py` exits 0 |
| `.planning/phases/09-live-source-feasibility-spike/09-SPIKE-RESULTS.md` | Attempt log, extracted sitekey/token samples, outcome taxonomy, go/no-go verdict | ✓ VERIFIED | All 4 required sections present (`Attempt Log`, `Extracted Values`, `Outcome Summary`, `Go/No-Go Recommendation`, `Scope Note`); 30 attempt rows, 2 distinct token samples (33-char hex nonces), `Verdict: GO` literal string present, `Phase 11` referenced in Scope Note |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| `app.py` `POST /lookup` → `_lookup_async()` | `https://wsp.registraduria.gov.co/censo/consultar/` | `page.goto()` + `page.click('#enviar')` in one browser context, response captured via `page.expect_response()` | ✓ WIRED | Confirmed at `app.py:82` (goto) and `app.py:148-153` (expect_response scoped to `r.url == WSP_PAGE_URL and r.request.method == "POST"`, click inside the `async with` block) |
| `09-SPIKE-RESULTS.md`'s attempt table Outcome column | `app.py`'s outcome classifier | Each logged row's outcome is one of the five states `app.py` emits | ✓ WIRED | `awk` extraction of all 30 Outcome-column values returns only `success` and `denied_by_score` — both members of the five-state enum emitted by `app.py`'s classifier; no stray free-text values found |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
| --- | --- | --- | --- | --- |
| LIVE-02 | 09-01-PLAN.md, 09-02-PLAN.md | Feasibility of `wsp.registraduria.gov.co` (reCAPTCHA Enterprise) as an additional live-source adapter is validated end-to-end before the system relies on it | ✓ SATISFIED | REQUIREMENTS.md marks LIVE-02 `[x]` / "Complete", mapped to Phase 9; end-to-end validation performed and documented with a GO verdict |

No orphaned requirements — REQUIREMENTS.md maps only LIVE-02 to Phase 9, and both plans declare it.

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| --- | --- | --- | --- | --- |
| `registraduria-service/app.py` | — | No TODO/FIXME/placeholder patterns found | — | None |

One notable, non-blocking observation: `submit_payload["enterprise"]`/`enterprise=1` escalation code path exists but was never exercised in the actual 30-attempt spike (0/6 baseline `denied_by_score` never crossed the 50% trigger). This is expected, correctly-implemented conditional logic that simply wasn't needed given the near-100% raw success rate — not a stub, since the branch is reachable and would execute given the right input (`enterprise: true` in the request body). Flagged as informational only.

### Scope-Boundary Check (D-05)

`git diff a4c3f8c bda7895 --stat -- app/ config/` (comparing the commit before Phase 9's plan-authoring commit against the phase's final commit) returns empty — confirms no production Laravel code (`RegistraduriaService.php`, `LiveSourceAdapter.php`, `config/services.php`'s `REGISTRADURIA_LIVE_ENABLED`) was touched during this phase, consistent with the Scope Note in `09-SPIKE-RESULTS.md` and D-05.

### Human Verification Required

None. All must-haves are verifiable from the codebase, git history, and the documented attempt log — this phase's success criteria are inherently code/document-based (a spike report, not a UI feature), and the underlying data (attempt log timestamps, service.log entries corroborating the same request cadence, commit hashes) is internally consistent.

### Note on Roadmap Success Criterion Wording

ROADMAP.md's Success Criterion 1 for Phase 9 is worded as "...solves the Enterprise captcha (`enterprise=1` + sitekey)...". The spike's actual empirical finding (documented in `09-RESEARCH.md`'s "corrected finding" and confirmed by the 30-attempt results) is that `wsp.registraduria.gov.co` presents a classic (non-Enterprise) reCAPTCHA v2 checkbox — a plain `userrecaptcha` 2captcha solve was accepted on 29/30 attempts, and the `enterprise=1` escalation lever was implemented but never needed. This is a stronger and more actionable result than the roadmap's original framing anticipated (which assumed the harder Enterprise path would be required), not a shortfall — the code implements the escalation capability per the plan's design, the end-to-end submission and explicit non-token-based success classification (the two substantive parts of the criterion) are both fully met, and the go/no-go verdict is unambiguous. Treated as achieved, not a gap.

### Gaps Summary

No gaps found. All observable truths, artifacts, and key links verified against the actual codebase (not just SUMMARY claims): `app.py`'s rewritten flow was read in full and cross-checked line-by-line against both plans' mandated code and acceptance-criteria greps; all 4 phase commits (`1fc6f66`, `7ed7b22`, `671245f`, `d9bc805`) exist in git history; `09-SPIKE-RESULTS.md`'s 30-row attempt log was independently parsed (row count, cédula distribution, outcome values) rather than trusting the prose summary; `service.log` timestamps corroborate the same 10s-cadence, 30-call pattern described in the summary; and a direct `git diff` confirms no production Laravel wiring was touched, satisfying D-05's scope boundary.

---

*Verified: 2026-07-25T18:10:00Z*
*Verifier: Claude (gsd-verifier)*
