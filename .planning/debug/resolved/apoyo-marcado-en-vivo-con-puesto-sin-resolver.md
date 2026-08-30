---
status: resolved
trigger: "apoyo-marcado-en-vivo-con-puesto-de-votacion-sin-resolver: apoyo marcado polling_place_source='live' con polling_place_id NULL, muestra 'Sin resolver' en ViewVoter pese al badge En Vivo"
created: 2026-08-30T00:00:00Z
updated: 2026-08-30T00:45:00Z
---

## Current Focus

hypothesis: CONFIRMED and FIXED (see Resolution). All 3 fronts implemented, self-verified against real local-backup data, full test suite green (1644 passed, 0 regressions; 1 known pre-existing unrelated flaky test — random 2-digit Department factory code colliding with a hardcoded 'SUCRE'/28 fixture in PollingPlaceResolverTest's beforeEach, reproducible on main before any of my changes).
test: Pest unit/feature tests (Municipality fuzzy match, persist() guard, resolveAutomated()/ReconcileFallbackPollingPlaces/CollectRegistraduriaLookupResult regression coverage, new BackfillLiveUnresolved command) + manual end-to-end verification against the real sigma_betha_backup local DB (rolled back, no data mutated)
expecting: user runs the same remediation flow against real production (sigma_betha) after reviewing; awaiting human confirmation before archiving
next_action: awaiting human verification (browser/production confirmation) per checkpoint below

## Update (team-lead cross-check, second pass)

Team lead's parallel investigation surfaced two points, both already covered by this fix (confirmed, no code changes needed for point 2; added a diagnostic enhancement for point 1):

1. Team lead independently found the same 3 concrete mismatch examples (TOLUVIEJO/Tolú Viejo, BOGOTA. D.C./Bogotá D.C., COLOSO (RICAURTE)/Coloso) and asked whether to extract the FULL list of mismatches across all ~1122 municipios before choosing normalization-function vs. alias-table. This agent has no production DB access (only the local sigma_betha_backup), so extended `census:backfill-live-unresolved --dry-run` to report, per affected voter, whether `Municipality::findByFuzzyName()` would now resolve their cached `registraduria_lookups.municipio` (and to which municipality), or whether it still needs manual review — giving the operator the full list by running the dry-run against production themselves, without needing a separate audit script.
2. Team lead flagged `ReconcileFallbackPollingPlaces.php` hardcoding `updateVoterStatus(found: true)` off `$result->source === LIVE` alone, never checking `pollingPlaceId`. Already closed by this fix's design (not by adding a `pollingPlaceId !== null` condition, but more directly): the job's upgrade branch now checks the voter's ACTUALLY-PERSISTED `polling_place_source` (via `$voter->fresh()`) rather than trusting the resolver's returned object — since persist() itself refuses to write LIVE without a real pollingPlaceId, this branch (and its `updateVoterStatus` call) simply never fires for an unmatched-municipality case. Strengthened the existing regression test to explicitly assert `status` stays unchanged (not force-marked VERIFIED_CENSUS) in that case — see tests/Feature/Jobs/ReconcileFallbackPollingPlacesTest.php.

## Symptoms

expected: Si polling_place_source='live', polling_place_id no debería ser NULL. Si no se puede resolver, NO debe marcarse como 'live'/resuelto — debe quedar en estado reintentable.
actual: persist() escribe polling_place_source='live' y polling_place_resolved_at incondicionalmente; polling_place_id solo se llena si resolveOrCreatePollingPlace() hace match exacto (LOWER(name)=...) del string crudo `municipio` contra Municipality.name.
errors: Ninguno explícito — fallo silencioso (return null sin excepción/log).
started: hueco abierto desde 260731-jmq y 260808-jsz, nunca cerrado para el caso de municipio sin match.
reproduction: confirmado en prod (sigma_betha, SSH korserver): 38/2637 voters live con polling_place_id NULL, todos status=verified_census. En backup local (sigma_betha_backup): 4 voters (ids 88, 89, 116, 153).

## Eliminated

- hypothesis: municipality_id=999 (campo propio del Voter, "Sincelejo") es la causa / el municipio de destino no existe en el catálogo.
  evidence: municipality_id=999 en los 4 voters afectados es el municipio DE ORIGEN del voter (su propio campo `municipality_id`), completamente independiente del string `municipio` que llega en el resultado LIVE y que resolveOrCreatePollingPlace() intenta matchear contra la tabla Municipality. No tiene relación causal con el bug.
  timestamp: 2026-08-30T00:10:00Z

## Evidence

- timestamp: 2026-08-30T00:05:00Z
  checked: app/Services/PollingPlaceResolver.php líneas 287-337 (persist()) y 500-569 (resolveOrCreatePollingPlace())
  found: persist() construye $updates con polling_place_source/polling_place_resolved_at SIN condición (líneas 303-306); polling_place_id solo se agrega si no-null (308-310), nunca se limpia si es null (no-downgrade deliberado). resolveOrCreatePollingPlace() matchea Municipality con whereRaw('LOWER(name) = ?', ...) exacto — sin normalización de acentos/puntuación/espacios.
  implication: confirma exactamente la causa raíz reportada por el equipo antes de invocar este agente.

- timestamp: 2026-08-30T00:15:00Z
  checked: los 4 voters afectados en sigma_betha_backup (ids 88, 89, 116, 153) vía tinker + su RegistraduriaLookup correspondiente
  found: |
    - id 88/89: municipio="TOLUVIEJO" (sin espacio/tilde) vs catálogo "Tolú Viejo" (id 1019, dept 29=Sucre) — mismatch de normalización (espacio + tilde).
    - id 116: municipio="BOGOTA. D.C." (punto extra) vs catálogo "Bogotá D.C." (id 31) — mismatch de tilde + puntuación.
    - id 153: municipio="COLOSO (RICAURTE)" vs catálogo "Coloso" (id 1002, dept 29=Sucre) — el sufijo parentético "(RICAURTE)" (nombre alterno que agrega la Registraduría) no existe en el catálogo local.
  implication: los 3 casos son 100% arreglables con matching más robusto (strip acentos + contenido entre paréntesis + puntuación/espacios). NINGUNO de los 4 casos reales es un municipio genuinamente ausente del catálogo — es puramente un problema de normalización de texto. No se requiere autocreación de Municipality ni decisión de producto.

- timestamp: 2026-08-30T00:20:00Z
  checked: colisiones de la normalización propuesta (accent-strip + strip paréntesis + strip puntuación/espacios + uppercase) contra las 1125 filas de Municipality
  found: 68 grupos de colisión, TODOS son municipios homónimos en departamentos distintos (ej. "Granada" existe en 3 departamentos) — una ambigüedad PRE-EXISTENTE que el propio matching exacto actual ya tenía (Municipality::first() ya elegía arbitrariamente una fila de estos grupos, sin desambiguar por departamento). Ninguno de los 3 casos reales afectados cae en un grupo de colisión.
  implication: el fallback normalizado debe devolver null (no adivinar) cuando hay >1 candidato tras normalizar, en vez de elegir el primero arbitrariamente — pero esto es una salvaguarda nueva en el path de fallback, no una regresión respecto al comportamiento actual (que ya era ciego a la ambigüedad en el path exacto). No se toca el path de matching exacto pre-existente para no ampliar el alcance del fix.

- timestamp: 2026-08-30T00:25:00Z
  checked: app/Jobs/ReconcileFallbackPollingPlaces.php (census:reconcile-live, hourly cron)
  found: la query de candidatos ya filtra `whereNull('polling_place_source') OR polling_place_source != 'live'` AND `whereNull('reconciliation_exhausted_at')`. Si persist() deja de marcar source='live' cuando pollingPlaceId es null, el voter automáticamente vuelve a ser candidato de este job hourly — no hace falta un job nuevo para el reintento, solo que persist() deje de mentir.
  implication: el fix de persist() + el fix de matching interactúan: en el próximo ciclo hourly, resolveFromPermanentLookup() (que consulta el registraduria_lookups YA cacheado, sin costo) reintentará resolveOrCreatePollingPlace() con el matching ya arreglado — resolviendo los 3 casos reales SIN gastar 2captcha de nuevo.

- timestamp: 2026-08-30T00:28:00Z
  checked: app/Console/Commands/BackfillPollingPlaceId.php y BackfillLiveStatusDesync.php (precedente de comandos de remediación local-only)
  found: convención establecida — `census:backfill-*` con `--dry-run`, opera solo con datos ya locales, nunca toca un adapter live/2captcha.
  implication: el comando de remediación de este fix debe seguir la misma convención de nombre/estructura.

## Resolution

root_cause: |
  Dos bugs independientes que se combinan:
  1. PollingPlaceResolver::resolveOrCreatePollingPlace() matchea Municipality con comparación EXACTA (LOWER(name) = LOWER($fields['municipio'])), sin normalizar acentos, puntuación o contenido parentético. Los resultados LIVE reales de la Registraduría vienen con variaciones de formato (ej. "TOLUVIEJO" sin espacio/tilde, "BOGOTA. D.C." con punto extra, "COLOSO (RICAURTE)" con sufijo parentético) que nunca calzan contra el catálogo local ("Tolú Viejo", "Bogotá D.C.", "Coloso"), por lo que la función retorna null silenciosamente.
  2. PollingPlaceResolver::persist() escribe polling_place_source='live' y polling_place_resolved_at INCONDICIONALMENTE, sin verificar si $result->pollingPlaceId es null — por lo que un voter queda marcado como "En Vivo"/resuelto aunque el puesto de votación real nunca se haya enlazado. Esto además saca al voter de la query de candidatos de ReconcileFallbackPollingPlaces (que excluye polling_place_source='live'), dejándolo atascado para siempre sin reintento automático.
fix: |
  1. Nuevo helper Municipality::findByFuzzyName() (+ Municipality::normalizeName()): intenta match exacto primero (rápido, indexado); si falla, normaliza (Str::ascii + strip paréntesis + strip puntuación/espacios + uppercase) y compara contra todo el catálogo (1125 filas, barato). Si hay más de un candidato tras normalizar, retorna null y loguea advertencia (no adivina) — nunca por debajo de lo que ya hacía el matching exacto pre-existente.
  2. PollingPlaceResolver::resolveOrCreatePollingPlace() usa el nuevo helper en vez de la query exacta inline. HasRegistraduriaPolling::fillPollingPlaceFields() (mismo bug duplicado en el flujo interactivo) también usa el helper para mantener comportamiento idéntico entre ambos flujos (invariante que el propio código ya documenta).
  3. PollingPlaceResolver::persist() agrega guarda: si $result->source === LIVE y $result->pollingPlaceId es null, retorna null sin escribir nada (ni source, ni resolved_at, ni audit row) — nunca miente sobre una resolución LIVE que en realidad falló. Esto reactiva automáticamente el reintento vía census:reconcile-live sin necesidad de lógica nueva de conteo de intentos.
  4. Nuevo comando `census:backfill-live-unresolved --dry-run` que revierte voters con polling_place_source='live' AND polling_place_id NULL a estado no resuelto (source=null, resolved_at=null, reconciliation_attempts=0, reconciliation_exhausted_at=null), para que el ciclo normal de reconciliación los vuelva a tomar.
verification: |
  Self-verified end-to-end against the real sigma_betha_backup local DB (transaction rolled back afterward, no data mutated):
  1. Municipality::findByFuzzyName() resolves all 3 real production municipio strings (TOLUVIEJO -> Tolú Viejo #1019, BOGOTA. D.C. -> Bogotá D.C. #31, COLOSO (RICAURTE) -> Coloso #1002).
  2. census:backfill-live-unresolved --dry-run correctly identifies exactly the 4 known-affected local voters (88, 89, 116, 153) and no others.
  3. Ran the remediation command for real (inside a rolled-back transaction) then ran ReconcileFallbackPollingPlaces::handle() — all 4 voters ended up with polling_place_source=live AND a real, non-null polling_place_id (43, 43, 44, 45), reconciliation_attempts reset to 0 — using ONLY the already-cached registraduria_lookups permanent table (resolveFromPermanentLookup), so NO 2captcha spend was needed for these specific cases.
  4. Full Pest suite: 1644 passed, 0 regressions attributable to this change. One pre-existing, unrelated flaky test confirmed reproducible on main before any of my changes (random Department factory code occasionally colliding with a hardcoded fixture code in an unrelated beforeEach — not touched, out of scope).
  Confirmed by team lead: ran the full related suite independently (Voter|Municipality|PollingPlace|Registraduria|Census, 665 tests, 0 failures) plus every file touched by this fix (109 tests, 0 failures), and `vendor/bin/pint --dirty` clean. census:backfill-live-unresolved was NOT run against real production (sigma_betha) by this agent — team lead will coordinate that via SSH to korserver after this commit is pushed.
files_changed:
  - app/Models/Municipality.php (new Municipality::findByFuzzyName()/normalizeName() helpers)
  - app/Services/PollingPlaceResolver.php (resolveOrCreatePollingPlace() uses the fuzzy helper; persist() refuses to fake polling_place_source=LIVE when pollingPlaceId is null; resolveAutomated() decouples "genuine census/Registraduría find" from "polling place actually persisted")
  - app/Filament/Resources/Voters/Concerns/HasRegistraduriaPolling.php (interactive flow's duplicated municipality match now uses the same fuzzy helper)
  - app/Jobs/ReconcileFallbackPollingPlaces.php (upgrade detection now checks the voter's own persisted polling_place_source instead of trusting resolveAutomated()'s return value)
  - app/Jobs/CollectRegistraduriaLookupResult.php (persistSuccess() now checks persist()'s return value before resetting reconciliation_attempts; routes a blocked write through the existing genuine-failure bump)
  - app/Console/Commands/BackfillLiveUnresolved.php (new: census:backfill-live-unresolved --dry-run)
  - tests/Unit/Models/MunicipalityFuzzyMatchTest.php (new)
  - tests/Feature/Services/PollingPlaceResolverTest.php (+7 tests: fuzzy matching, persist() guard, DB_RECONSTRUCTION/SNAPSHOT regression, resolveAutomated() decoupling)
  - tests/Feature/Jobs/ReconcileFallbackPollingPlacesTest.php (+1 regression test)
  - tests/Feature/Jobs/CollectRegistraduriaLookupResultTest.php (+1 regression test)
  - tests/Feature/Console/BackfillLiveUnresolvedTest.php (new)
  - tests/Feature/VoterValidationServiceTest.php (corrected a pre-existing test that had inadvertently codified the exact reported bug as an assertion; added a new regression test for the corrected behavior)
  - tests/Feature/RevalidationCoverageTest.php (corrected a pre-existing test with the same issue — seeded a genuinely-matching Municipality/PollingPlace instead of relying on an unmatched random Faker city name)
