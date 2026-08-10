# Phase 9: Live-Source Feasibility Spike -- Results

Spike executed against the live `wsp.registraduria.gov.co` endpoint via `registraduria-service/app.py`'s rewritten flow (Plan 09-01), spending real 2captcha budget per D-04. Rows below were appended in real time as each attempt completed (not batched at the end).

## Attempt Log

| Number | Cedula | Enterprise | Outcome | Message/Detail | Timestamp |
| --- | --- | --- | --- | --- | --- |
| 1 | 1102812122 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:19:20Z |
| 2 | 1102815878 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:19:30Z |
| 3 | 64552231 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:19:40Z |
| 4 | 1102812122 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:19:50Z |
| 5 | 1102815878 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:20:00Z |
| 6 | 64552231 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:20:10Z |
| 7 | 1102812122 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:20:20Z |
| 8 | 1102815878 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:20:30Z |
| 9 | 64552231 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:20:40Z |
| 10 | 1102812122 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:20:50Z |
| 11 | 1102815878 | False | denied_by_score | Curl error: Operation timed out after 15008 milliseconds with 0 bytes received | 2026-07-25T17:21:20Z |
| 12 | 64552231 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:21:30Z |
| 13 | 1102812122 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:21:40Z |
| 14 | 1102815878 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:21:50Z |
| 15 | 64552231 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:22:00Z |
| 16 | 1102812122 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:22:10Z |
| 17 | 1102815878 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:22:20Z |
| 18 | 64552231 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:22:30Z |
| 19 | 1102812122 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:22:40Z |
| 20 | 1102815878 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:22:50Z |
| 21 | 64552231 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:23:00Z |
| 22 | 1102812122 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:23:10Z |
| 23 | 1102815878 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:23:20Z |
| 24 | 64552231 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:23:30Z |
| 25 | 1102812122 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:23:40Z |
| 26 | 1102815878 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:23:50Z |
| 27 | 64552231 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:24:00Z |
| 28 | 1102812122 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:24:10Z |
| 29 | 1102815878 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:24:20Z |
| 30 | 64552231 | False | success | {"data": {"message": "<div class='table-responsive'><table class='display responsive table table-bordered table-striped' id='consulta'><thead><tr><th class='text-center'>NUIP</th><th class='text-cente... | 2026-07-25T17:24:30Z |

Note on attempt 11 (`denied_by_score`): the raw message was `"Curl error: Operation timed out after 15008 milliseconds with 0 bytes received"` -- this reads as Registraduría's own backend surfacing an internal upstream timeout (their server making some downstream call that itself uses curl), not a captcha-score rejection message. `app.py`'s classifier has no dedicated bucket for this shape of message and correctly defaults any unrecognized `success:false` message to `denied_by_score` per its safe-default design (09-RESEARCH.md Open Question 2) rather than misclassifying it as `success`. Treated here as a transient backend hiccup, not a captcha-denial signal -- see Outcome Summary below.

## Extracted Values

- **Sitekey** (from `.g-recaptcha[data-sitekey]`, stable across the whole spike): `6LcthjAgAAAAAFIQLxy52074zanHv47cIvmIHglH`
- **Anti-replay `#token` nonce samples** (zero-cost post-hoc re-extraction, same WAF-evading UA-spoofed Playwright context as Plan 09-01 Task 2 -- no 2captcha budget spent capturing these two samples):
  - Load 1: `c61aff2427cd0114306febc741a521b1`
  - Load 2: `30718af6ff62fbc9f594decbe59c5a5e`
  - Confirms 09-RESEARCH.md's finding: the `#token` value is a distinct, per-page-load, server-generated nonce that rotates on every request and is unrelated to the reCAPTCHA sitekey (which stayed constant).

## Outcome Summary

| Outcome | Count | % of 30 |
| --- | --- | --- |
| success | 29 | 96.7% |
| denied_by_score | 1 | 3.3% |
| not_found | 0 | 0% |
| session_expired | 0 | 0% |
| source_unreachable | 0 | 0% |

- **Overall success rate:** 29/30 (96.7%).
- **`denied_by_score` rate:** 1/30 (3.3%) -- and per the note above, this single instance was itself a backend-surfaced upstream timeout message, not evidence of an actual captcha-score-based rejection. No genuinely captcha-score-flavored denial message was observed at all during this spike.
- **Cédula coverage:** all three locked test cédulas (`1102812122`, `1102815878`, `64552231`) were attempted exactly 10 times each (30 total), well over the required minimum of twice each.
- **Enterprise escalation (D-04's >50%-denied trigger):** NOT triggered. The baseline round (attempts 1-6) had 0/6 `denied_by_score` (0%), far below the 50% threshold, so all 30 attempts ran with `enterprise=false` (the plain `userrecaptcha` 2captcha method matching the page's classic-v2-shaped widget, per 09-RESEARCH.md's corrected finding). The `enterprise=1` escalation lever was never exercised because it was never needed -- plain solves were accepted essentially every time.

## Go/No-Go Recommendation

Verdict: GO

Rationale: 29/30 real, live attempts against `wsp.registraduria.gov.co` succeeded end-to-end -- a real 2captcha-solved plain (non-enterprise) `userrecaptcha` token was accepted by the backend and returned genuine polling-place HTML (`success:true`, `data.message` containing the `#consulta` results table) for all three known cédulas, each attempted 10 times. This directly answers 09-RESEARCH.md's core open question ("does the backend accept a plain solve given the classic-v2-shaped front end?") with a strong empirical yes -- no `enterprise=1` escalation was ever required. The single non-success attempt (#11) was not a captcha-score denial at all (see note above) but an unrelated backend-surfaced upstream timeout, and is not evidence against viability -- if anything, a 96.7% raw success rate with zero genuine `denied_by_score`/`not_found`/`source_unreachable` outcomes is a stronger signal than the spike's budget was sized to expect (D-04 was budgeted assuming a much higher denial/escalation rate than what was actually observed). `wsp.registraduria.gov.co` is empirically viable as a live polling-place source and worth wiring into production in a future phase.

## Scope Note

Per D-05, no production wiring was performed in this phase regardless of the verdict above: `app/Services/RegistraduriaService.php`, `app/Services/LiveSourceAdapter.php`, and the `REGISTRADURIA_LIVE_ENABLED` config flag were NOT modified, touched, or flipped during this spike. This document's sole purpose is the go/no-go recommendation above. Phase 11 (and any future production-wiring phase) should consult this document's Go/No-Go Recommendation -- a strong GO with a 96.7% observed success rate and no captcha-score-based denials -- to scope how much automated reconciliation is realistically achievable via the live path; given this result, Phase 11's automated reconciliation job can reasonably expect the live path to succeed on the large majority of retry attempts rather than needing to lean heavily on the snapshot-fallback path.
