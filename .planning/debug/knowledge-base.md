# GSD Debug Knowledge Base

Resolved debug sessions. Used by `gsd-debugger` to surface known-pattern hypotheses at the start of new investigations.

---

## registraduria-interactive-result-not-parsed — Interactive Registraduria lookup showed generic error and, once fixed, crashed on save due to blank DIVIPOLE codes
- **Date:** 2026-07-26
- **Error patterns:** Error desconocido al consultar la Registraduria, QueryException, Incorrect integer value, zone_code, puesto_codigo, zona_codigo, raw_message_html, polling_places, NOT NULL
- **Root cause:** (1) RegistraduriaController::result() returned the Python microservice's raw JSON passthrough (unparsed raw_message_html) to the Alpine.js polling loop, instead of the parsed fields that RegistraduriaService::getResult() produces via parseConsultaHtml() — so a real success looked like a generic error because puesto_nombre was missing. (2) Real Registraduria HTML responses never include a "CODIGO PUESTO"/"ZONA" column, so puesto_codigo/zona_codigo are always empty string (not an edge case — the normal case). HasRegistraduriaPolling::fillPollingPlaceFields() and PollingPlaceResolver::resolveOrCreatePollingPlace() both used `?? null`/`?? substr(...)` fallbacks that never triggered (since the keys always existed, just blank), so '' was inserted into NOT NULL unsignedSmallInteger zone_code/place_code columns, crashing with QueryException 1366.
- **Fix:** Extracted parsing into a shared static `RegistraduriaService::normalizeResultPayload()` called by both the controller and the service. Made `polling_places.zone_code`/`place_code` nullable via migration. Made `PollingPlaceResolver::resolveOrCreatePollingPlace()` public and rewrote it to use `filled()` checks (blank string -> null) with name-based match/create when codes are blank (following the existing `resolveFromCampaignCensus()` precedent). `HasRegistraduriaPolling::fillPollingPlaceFields()` now delegates to the resolver instead of duplicating the logic.
- **Files changed:** app/Services/RegistraduriaService.php, app/Http/Controllers/RegistraduriaController.php, app/Services/PollingPlaceResolver.php, app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php, database/migrations/2026_07_26_164628_make_zone_and_place_code_nullable_in_polling_places.php, tests/Feature/RegistraduriaControllerTest.php, tests/Feature/Services/PollingPlaceResolverTest.php, tests/Feature/Filament/VoterRegistraduriaRefreshTest.php
---

