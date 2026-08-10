---
phase: 11-scheduled-reconciliation-job
plan: 02
subsystem: services
tags: [registraduria, wsp, html-parser, dom, laravel-http]

# Dependency graph
requires:
  - phase: 11-scheduled-reconciliation-job
    plan: 01
    provides: "Real, untruncated wsp #consulta success HTML fixture (tests/fixtures/registraduria/consulta-sample.html) revealing the actual table structure"
provides:
  - "parseConsultaHtml() private method on RegistraduriaService — parses the wsp #consulta HTML table into the 7 structured fields (puesto_nombre, puesto_codigo, zona_codigo, mesa_numero, departamento, municipio, direccion) using bundled DOMDocument/DOMXPath, zero new dependency"
  - "getResult() now returns structured fields (not raw_message_html) whenever the Python service responds status=done with data.raw_message_html present; every other status/data shape passes through unchanged"
affects: [11-03-reconciliation-job, 11-04-scheduling]

# Tech tracking
tech-stack:
  added: []
  patterns: ["DOMDocument/DOMXPath label-based cell lookup by matching normalized <th> header text to a field-name map, positional to the matching <td> in the first <tbody> row — never a hardcoded column index"]

key-files:
  created:
    - tests/Feature/Services/RegistraduriaServiceParserTest.php
  modified:
    - app/Services/RegistraduriaService.php

key-decisions:
  - "The real fixture's actual header set is NUIP, DEPARTAMENTO, MUNICIPIO, PUESTO, DIRECCIÓN, MESA — there is no CODIGO PUESTO or ZONA column in the real wsp response, so puesto_codigo and zona_codigo remain '' after parsing this fixture. The label-to-field map still includes those two labels (for forward compatibility if a future response shape includes them) but they are never exercised by the current real data."
  - "Accent normalization done via a plain str_replace table (Á/É/Í/Ó/Ú -> A/E/I/O/U) rather than a regex+closure — simpler and sufficient since the only accented header in the real fixture is DIRECCIÓN."
  - "RECON-01 intentionally NOT marked complete in REQUIREMENTS.md by this plan alone, matching the precedent set by 11-01 and 11-03 — RECON-01's actual claim (a scheduled job that re-attempts and upgrades) requires 11-04's job/scheduling work, which this plan does not build. This plan only closes the parsing gap (D-02/D-03) that the future job depends on."

requirements-completed: []  # RECON-01 deferred to phase completion (11-04) per precedent

# Metrics
duration: 12min
completed: 2026-07-26
---

# Phase 11 Plan 02: wsp HTML Parser Summary

**`parseConsultaHtml()` uses DOMDocument/DOMXPath to derive field-to-column mapping from the real captured wsp fixture's actual header labels, closing the gap where `getResult()` only forwarded raw HTML instead of the 7 structured fields the resolver already expects**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-07-26T14:49:30Z
- **Completed:** 2026-07-26T15:01:51Z
- **Tasks:** 2
- **Files modified:** 1 modified, 1 created

## Accomplishments
- Read the real captured fixture (`tests/fixtures/registraduria/consulta-sample.html`) first and confirmed its actual `<thead>`/`<tbody>` structure: 6 header columns (NUIP, DEPARTAMENTO, MUNICIPIO, PUESTO, DIRECCIÓN, MESA), one data row, MESA's value wrapped in a `<b>` tag.
- Added `RegistraduriaService::parseConsultaHtml()` — a private method using PHP's bundled `DOMDocument`/`DOMXPath` (no new Composer dependency) that maps each real `<th>` header label (normalized: trimmed, uppercased, accents stripped) to one of the 7 documented field keys, then pulls the positionally-matching `<td>` text from the first `<tbody>` row.
- Malformed/empty HTML (empty string, no `id="consulta"` table, or an empty header/row set) degrades to all 7 fields present but `''` — never throws, so a parse failure falls back to snapshot instead of crashing the reconciliation job.
- Wired into `getResult()`: only replaces `data` with the parsed structured fields when the Python service payload has `status === 'done'` and a non-empty `data.raw_message_html`; every other status (`pending`, `waiting_captcha`, `error`) or a `null`/missing `data` shape passes through completely unchanged.
- 3 new Pest tests cover the success path (against the real fixture's actual HTML) and both pass-through paths (`done`+`data:null`, `pending`), plus the 5 pre-existing reachability tests still pass — 8/8 green.

## Task Commits

Each task was committed atomically:

1. **Task 1: Write parseConsultaHtml() from the real captured sample and wire it into getResult()** - `eb56e2c` (feat)
2. **Task 2: Pest coverage against the real captured fixture** - `d8483cf` (test)

## Files Created/Modified
- `app/Services/RegistraduriaService.php` - added `parseConsultaHtml()` private method (DOMDocument/DOMXPath, label-based column mapping derived from the real fixture) and wired it into `getResult()` for the `status=done` + `raw_message_html`-present shape only
- `tests/Feature/Services/RegistraduriaServiceParserTest.php` - 3 Pest tests: success path against the real fixture, `done`+`data:null` pass-through, `pending` pass-through

## Decisions Made
- Kept the label-to-field map's `CODIGO PUESTO`/`ZONA` entries even though the real fixture has no matching headers — harmless forward-compatibility for a future response shape, verified they're never exercised by the current real data (both fields correctly stay `''`, confirmed by the test only asserting non-empty on `puesto_nombre`/`departamento`/`municipio`, not all 7 keys).
- Used a simple `str_replace` accent table instead of a regex+closure for header-label normalization — the real fixture only has one accented header (`DIRECCIÓN`), so the simpler approach was sufficient and more readable.
- RECON-01 intentionally left unmarked in REQUIREMENTS.md (see key-decisions above) — deferred to Plan 11-04, matching the split-requirement precedent from Plans 11-01 and 11-03.

## Deviations from Plan

None - plan executed exactly as written. The real fixture's structure matched what was expected closely enough (label-based lookup as planned); the only refinement was confirming that `CODIGO PUESTO`/`ZONA` have no corresponding real column, which the plan itself anticipated ("adjust this map's left-hand labels to match the ACTUAL header text").

## Issues Encountered

None.

## User Setup Required

None.

## Next Phase Readiness
- `getResult()` now returns the exact 7-key structured shape that `PollingPlaceResolver::attemptLiveAutomated()` and `resolveOrCreatePollingPlace()` already consume — the live tier's HTML-to-fields gap (D-02/D-03) is closed.
- No blockers for Plan 11-03 (reconciliation job) or 11-04 (scheduling), both of which can now rely on `getResult()` producing usable structured data on a real success.

---
*Phase: 11-scheduled-reconciliation-job*
*Completed: 2026-07-26*

## Self-Check: PASSED

All claimed files exist on disk and both task commits (`eb56e2c`, `d8483cf`) are present in git history.
