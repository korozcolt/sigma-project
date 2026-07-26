# Quick Task 260726-jao: Tabla permanente de resultados de Registraduría por cédula, consultada por flujo Líder, Coordinador y PollingPlaceResolver - Context

**Gathered:** 2026-07-26
**Status:** Ready for planning

<domain>
## Task Boundary

Hoy existe un cache de resultados de Registraduría en vivo (`App\Filament\Resources\Voters\Concerns\HasRegistraduriaPolling`, clave `registraduria:cedula:{cedula}`, TTL 30 días, backend `CACHE_STORE=database`) usado SOLO por las pantallas de admin (EditVoter/CreateVoter). No lo consulta ni el formulario del líder (`leader/register-voter`), ni el formulario de creación de líder del coordinador, ni `PollingPlaceResolver::resolveAutomated()` (usado por el job de reconciliación automática) — es decir, el sistema puede estar re-pagando consultas en vivo (captcha/2captcha) para cédulas que ya fueron resueltas antes.

Objetivo: persistir estos resultados de forma PERMANENTE (no como cache expirable) en una tabla dedicada, e integrarla en los tres puntos de consumo relevantes, tratando un resultado de Registraduría en vivo como más autoritativo que un match contra `CensusRecord` (censo local importado).

**Precedente de diseño ya existente a respetar:** la clave de cache actual (`registraduria:cedula:{cedula}`) NO está scopeada por campaña — es decir, el comportamiento actual ya trata los resultados de Registraduría como datos globales/cross-campaign (un resultado consultado por una campaña beneficia a cualquier otra). La tabla nueva debe seguir ese mismo precedente (sin `campaign_id` obligatorio, o con `campaign_id` puramente informativo/nullable — no debe restringir la lectura por campaña).

</domain>

<decisions>
## Implementation Decisions

### Almacenamiento
- Tabla dedicada permanente (ej. `registraduria_lookups`), indexada por `document_number`, SIN expiración (no TTL) — sobrevive `cache:clear` y cualquier limpieza del store de cache genérico.
- El mecanismo de cache existente en `HasRegistraduriaPolling` (`Cache::get`/`Cache::put` con TTL 30 días) debe reemplazarse por lectura/escritura contra esta tabla nueva, para no mantener dos fuentes de verdad divergentes. Guardar en la tabla los campos ya parseados relevantes (puesto/nombre, dirección, mesa, departamento, municipio, NUIP/document_number, fecha de la consulta, fuente que respondió) — seguir la forma de datos que ya produce `RegistraduriaService::normalizeResultPayload()` / `parseConsultaHtml()`.

### UX del líder (register-voter) y coordinador (create-leader)
- Al perder el foco del campo de cédula, si la cédula YA está en la tabla permanente de Registraduría (dato más fuerte que el censo local), autocompletar los campos de puesto de votación, dirección y mesa con el dato oficial ya confirmado, y mostrar un banner VERDE tipo "Verificado por Registraduría" — reemplaza cualquier advertencia de censo, no ambas a la vez. El líder/coordinador puede editar los campos autocompletados si quiere.
- Si la cédula NO está en la tabla permanente pero SÍ está en `CensusRecord` (censo local), aplica el comportamiento ya existente (sin advertencia, sin banner especial — ya cubierto por la tarea previa 260726-ifp).
- Si no está en ninguna de las dos fuentes, aplica la advertencia amarilla ya existente de "no aparece en el censo" (comportamiento de 260726-ifp sin cambios).

### Estado del Voter
- Nuevo valor en el enum `VoterStatus` (nombre a definir por el planner siguiendo la convención existente, ej. `VERIFIED_REGISTRADURIA`), distinto y más fuerte que `VERIFIED_CENSUS` — para diferenciar "confirmado por censo local importado" de "confirmado por la fuente oficial en vivo de Registraduría".

### Integración con PollingPlaceResolver / job de reconciliación
- `PollingPlaceResolver::resolveAutomated()` (y por extensión el job de reconciliación automática que lo usa) debe consultar la tabla permanente ANTES de intentar una consulta en vivo a Registraduría, para evitar repetir consultas ya pagadas/resueltas. Mismo principio de ahorro que motiva toda esta tarea, aplicado también al proceso automático, no solo a los formularios interactivos.
- Todo punto que hoy dispare una consulta en vivo exitosa (el flujo admin actual vía `HasRegistraduriaPolling`, y cualquier consulta en vivo que dispare el propio job de reconciliación) debe persistir el resultado en esta tabla permanente — no solo el punto de admin.

### Campo de cédula en el formulario de creación de Líder (coordinador)
- Se agrega un campo nuevo "Número de Documento" a `resources/views/livewire/coordinator/create-leader.blade.php` (hoy no existe ningún campo de cédula en ese formulario, pese a que `User::$fillable` ya incluye `document_number`).
- Es OBLIGATORIO — el coordinador no puede crear el líder sin ingresarlo.
- Aplica exactamente el mismo cruce (tabla permanente de Registraduría → CensusRecord → advertencia) y el mismo patrón de auto-completado/banner que el formulario del líder, sobre los datos del LÍDER que se está creando (no sobre el coordinador).
- Este campo se guarda en `User::document_number` del líder creado.

</decisions>

<specifics>
## Specific Ideas

Cédula real usada para validar manualmente durante esta sesión: 1102812122 (del propio usuario, ya presente en el cache actual con datos reales de Sucre/Sincelejo, mesa 13, IE SAN JOSE C I P) — útil como caso de prueba real para verificar el flujo de auto-completado con dato ya conocido.

</specifics>

<deferred>
## Explicitly Out of Scope (Do Not Implement)

El usuario planteó un tema relacionado pero deliberadamente diferido a otra sesión, para no mezclarlo con esta tarea:

- Deduplicación entre `User` (líderes/coordinadores) y `Voter` (apoyos) — la idea de que líderes y coordinadores "también son votantes" y deberían poder contarse en las cifras de apoyos SIN duplicarse entre tablas.
- Un esquema de identificadores anónimos/placeholder (ej. "1", "2", "3"...) para coordinadores en vez de su cédula real, por razones de seguridad/anonimato — para que no sean identificables personalmente en caso de que algo suceda.

NO tocar `User`/`Voter` deduplication logic ni ningún esquema de identificador anónimo en esta tarea. Si el planner encuentra que alguna de las decisiones de arriba roza este tema, debe mantenerse estrictamente dentro del alcance: el campo `document_number` del líder es su cédula real, sin lógica de anonimato ni de conteo cruzado con Voter.

</deferred>

<canonical_refs>
## Canonical References

Servicios/clases existentes a reusar, no reconstruir:
- `App\Services\RegistraduriaService::normalizeResultPayload()` / `parseConsultaHtml()` — lógica de parseo ya existente y corregida hoy (quick task 260726 anterior sobre el bug del controller).
- `App\Filament\Resources\Voters\Concerns\HasRegistraduriaPolling` — punto donde hoy se escribe al cache de 30 días; migrar su escritura/lectura a la tabla nueva.
- `App\Services\PollingPlaceResolver` — cascada de resolución existente (censo local → fuentes), agregar la tabla permanente como paso previo a la fuente en vivo.
- `App\Services\VoterValidationService::documentExistsInCensus()` — patrón ya usado hoy por 260726-ifp para el cruce contra `CensusRecord`; seguir el mismo patrón arquitectónico para el nuevo cruce contra la tabla de Registraduría.
- `App\Enums\VoterStatus` — enum a extender con el nuevo estado.

</canonical_refs>
