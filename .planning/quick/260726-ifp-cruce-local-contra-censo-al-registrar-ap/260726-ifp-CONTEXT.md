# Quick Task 260726-ifp: Cruce local contra censo al registrar Apoyo desde flujo Líder, con advertencia no-bloqueante y reconciliación en background por líder - Context

**Gathered:** 2026-07-26
**Status:** Ready for planning

<domain>
## Task Boundary

Al registrar un Apoyo desde el flujo del Líder (`resources/views/livewire/leader/register-voter.blade.php`), hacer un cruce LOCAL contra el censo ya importado (`App\Models\CensusRecord`, vía `App\Services\VoterValidationService::validateAgainstCensus()` / lógica equivalente) ANTES de guardar — NO llamada en vivo a Registraduría, solo la base de datos local. Si la cédula no aparece en el censo local de la campaña, mostrar una advertencia clara al líder con opción de corregir o continuar y guardar de todas formas (advertencia no-bloqueante).

Segunda parte: desde el panel admin, una forma de re-verificar/ajustar en bloque las cédulas de todos los apoyos de un líder específico, en background.

Infraestructura ya existente que debe reusarse (no construir desde cero):
- `App\Models\CensusRecord`: censo local importado por campaña.
- `App\Services\VoterValidationService::validateAgainstCensus(Voter)` y `validatePendingVoters(int $campaignId)`.
- `App\Jobs\ValidateVoterAgainstCensus` (ShouldQueue): job ya escrito pero huérfano, no dispachado desde ningún lado hoy.
- Patrón de scheduled command ya usado en el proyecto: `routes/console.php` tiene `Schedule::command('census:reconcile-live')->hourly()->withoutOverlapping(10)` (Fase 11, RECON-01) — seguir el mismo patrón.
- Hoy el único trigger de validación contra censo es un botón manual "Validar contra Censo" por registro individual en el panel admin (Filament VotersTable), sin bulk action ni filtro por líder.

</domain>

<decisions>
## Implementation Decisions

### Interacción de la advertencia
- Banner inline debajo del campo Número de Documento (no modal). El botón "Guardar Apoyo" permanece siempre habilitado — el líder puede ignorar el aviso y guardar directo sin pasos extra ni confirmación adicional.

### Momento del chequeo
- El cruce contra el censo local se dispara al perder el foco del campo Número de Documento (mismo patrón `wire:model.blur` que ya usa el formulario), dando feedback inmediato antes de llegar al submit.

### Superficie de reconciliación en background
- Ambos mecanismos:
  1. Scheduled command periódico (mismo patrón que `census:reconcile-live`, en `routes/console.php`) que revalida en background todos los apoyos pendientes de validar de la campaña.
  2. Botón/acción manual en el panel admin (Filament) para que un admin/reviewer dispare la revalidación de los apoyos de un líder específico bajo demanda, sin esperar al schedule.
- Reusar `VoterValidationService`/`App\Jobs\ValidateVoterAgainstCensus` para ambos; decidir en la fase de planeación si `validatePendingVoters()` necesita un parámetro opcional de filtro por líder (`leader_id` / `registered_by`) para soportar la acción manual por líder.

### Estado del registro guardado con advertencia ignorada
- Se agrega un nuevo valor al enum `VoterStatus` (ej. `CENSUS_NOT_FOUND` o nombre equivalente que decida el planner siguiendo la convención de nombres existente) — semánticamente distinto de `REJECTED_CENSUS`, ya que "no encontrado todavía" no es lo mismo que "rechazado". Debe ser visible/priorizable para un reviewer en el panel admin.

### Claude's Discretion
- Nombre exacto del nuevo valor de `VoterStatus` (seguir convención de nombres del enum existente).
- Frecuencia exacta del scheduled command (seguir precedente de `census:reconcile-live` salvo que haya razón para diferir).
- Si `validatePendingVoters()` se extiende con parámetro opcional o se crea un método nuevo específico para filtrar por líder — decisión técnica de implementación, no de producto.
- Copy exacto del mensaje de advertencia en el banner (mantener el tono ya usado en el formulario, en español).

</decisions>

<specifics>
## Specific Ideas

Mensaje de advertencia sugerido por el usuario: "esta cédula no aparece en el censo actual, revísala" — usar como base para el copy del banner.

</specifics>

<canonical_refs>
## Canonical References

No specs/ADRs externos — requisitos capturados completamente en las decisiones arriba. Referencia de convención de código: patrón `census:reconcile-live` en `routes/console.php` (Fase 11 / RECON-01 del milestone v1.1).

</canonical_refs>
