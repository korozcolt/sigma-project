---
status: root_cause_found
instance: sigma-betha (sigma_betha DB)
campaign_id: 1 ("Alcaldía 2027", Sincelejo)
created_at: 2026-07-30
---

# Apoyos masivamente "Rechazado en Censo" (sigma-betha)

## Síntoma

Tabla Apoyos (VoterResource) muestra decenas de registros con `status = rejected_census`
("Rechazado en Censo") y `polling_place_source` vacío, todos campaña "Alcaldía 2027" / Sincelejo.

## Datos duros (producción sigma-betha, campaign_id=1)

| Métrica | Valor |
|---|---|
| Total Apoyos (voters) | 188 |
| `rejected_census` | 148 |
| `verified_census` | 40 |
| `pending_review` | 0 |
| Filas en `census_records` (campaign_id=1) | **45** |
| Filas en `national_census_records` (censo nacional, Phase 6) | **0** |
| Filas en `national_identity_records` (cédula→nombre) | 371,010 |
| Voters con `polling_place_source` NULL | 187 / 188 |

## Root cause (confirmado con evidencia, no hipótesis)

1. **`VoterValidationService::validateAgainstCensus()`** valida cada Apoyo contra la tabla
   **legacy** `census_records` (scoped por `campaign_id`) — NO usa `national_census_records`
   (el censo nacional construido en Phase 6, que en esta instancia tiene 0 filas).

2. **`census_records` para esta campaña NUNCA se importó de forma masiva.** El servicio
   `App\Services\CensusImporter` (`import()`/`importInBatches()`) existe pero está **huérfano**
   — no hay ninguna ruta, comando artisan, Job o recurso Filament que lo invoque. `grep` confirma
   0 referencias fuera del propio archivo.

3. Las 45 filas que sí existen en `census_records` **se crearon una por una, incidentalmente**,
   como efecto secundario de `HasRegistraduriaPolling.php:350`
   (`CensusRecord::updateOrCreate(...)`) — cada vez que un líder hacía una consulta en vivo
   exitosa contra la Registraduría para un documento puntual. Confirmado por fechas: 1 fila el
   2026-07-27, 44 filas el 2026-07-29 — coincide exactamente con actividad de consultas, no con
   una carga de archivo.

4. Con solo 45/188 documentos "conocidos", cualquier disparo de revalidación
   (botón **"Revalidar apoyos de un líder"** en la captura → `DispatchCensusRevalidation` →
   `ValidateVoterAgainstCensus` → `VoterValidationService::updateVoterStatus()`, o el botón
   individual "Validar contra Censo") marca como `REJECTED_CENSUS` a cualquier Apoyo cuyo
   documento no esté en esas 45 filas — **aunque sea una persona real y válida**. Resultado:
   148/188 (79%) rechazados incorrectamente.

5. **`REJECTED_CENSUS` es un callejón sin salida.** `DispatchCensusRevalidation` sólo re-encola
   `PENDING_REVIEW` y `CENSUS_NOT_FOUND` (decisión documentada en STATE.md, quick task
   260726-ifp) — nunca vuelve a intentar con los ya rechazados. No se autocorrigen solos.

6. **`polling_place_source` vacío es un síntoma separado, no directamente causado por el
   rechazo:** 187/188 Apoyos (incluyendo 39/40 `verified_census`) tienen esa columna en NULL.
   Se llena solo vía el flujo interactivo de Registraduría o el job de reconciliación (Phase 11),
   ninguno de los cuales corre sobre `rejected_census`. Confirma que el pipeline de resolución de
   puesto de votación nunca se ha ejecutado a escala en esta instancia (consistente con
   `registraduria_lookups` teniendo 208 filas — sí hay actividad manual — pero eso no
   retro-alimenta el campo `polling_place_source` del Voter salvo casos puntuales).

## Por qué esto NO es "mala data insertada por el usuario"

No es un problema del archivo/importación de Apoyos (`ApoyoImporter`) — esos registros son
correctos. El problema es que la única fuente de verdad para "está o no en el censo" en esta
campaña (`census_records`) casi no tiene datos, y nadie corrió jamás un import real. El campo
"Rechazado en Censo" no significa "esta persona no existe" — significa "no estaba en nuestras 45
filas incidentales".

## Opciones de arreglo (no aplicadas — pendiente decisión)

1. **Importar el censo real de Sincelejo para campaign_id=1** vía `CensusImporter` (falta wiring
   a un comando/acción Filament, no existe hoy) y luego revalidar.
2. **Refactor de fondo:** migrar `VoterValidationService::findInCensus()` para usar
   `national_census_records`/`national_identity_records` (ya con 371K filas reales) en vez de la
   tabla `census_records` por-campaña, que quedó huérfana desde que existe el censo nacional.
3. **Remediación inmediata de los 148 ya rechazados:** revertir su `status` a `pending_review` (o
   `census_not_found` si corresponde) para que vuelvan a entrar en el ciclo normal de
   validación/reconciliación una vez se resuelva 1 o 2.

## Hallazgo adicional: por qué `polling_place_source` está NULL en 187/188

No es un bug de guardado — es un hueco de cobertura confirmado en el código:

- `polling_place_source` solo lo llena `PollingPlaceResolver`, invocado desde:
  1. El botón "Consultar/Actualizar Registraduría" en la pantalla de **edición individual**
     (`HasRegistraduriaPolling.php`, trait usado por `VoterForm`'s `EditRecord`) — requiere que un
     humano abra cada Apoyo uno por uno.
  2. El job programado `census:reconcile-live` (hourly) → `ReconcileFallbackPollingPlaces`.

- **`ReconcileFallbackPollingPlaces::handle()` exige `whereNotNull('polling_place_source')`** —
  solo *mejora* (upgrade a LIVE) apoyos que YA tienen algún nivel resuelto. Nunca hace la
  **primera** resolución. Ningún job programado resuelve por primera vez a un Apoyo con
  `polling_place_source` NULL.

- El otro job programado, `census:reconcile-validation` → `DispatchCensusRevalidation` (el que
  ya estamos arreglando), tampoco toca `polling_place_source` — solo cambia `status`.

**Conclusión:** con 188+ Apoyos, la única vía de resolución inicial (abrir cada edit individual)
no escala. De ahí 187/188 en NULL, incluso 39/40 `verified_census`.

**Implicación para el fix:** la revalidación unificada (que ya delega en
`PollingPlaceResolver::resolveAutomated()`) debe correr también sobre Apoyos con
`polling_place_source IS NULL`, no solo sobre los ya-rechazados o los ya-parcialmente-resueltos.
Un mismo botón/job resuelve censo + puesto de votación en una sola pasada.

## Siguiente paso sugerido

Quick task 260730-cs3 en curso (`.planning/quick/260730-cs3-fix-root-cause-in-planning-debug-apoyos-/`)
implementa la opción 2 (unificar con `PollingPlaceResolver`), remedia los 148 registros y agrega
indicador de progreso — ampliado para también cubrir `polling_place_source` NULL.
