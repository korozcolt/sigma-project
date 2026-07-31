# Quick Task 260731-n7t: Fix banner de revalidacion sin boton de cerrar y saldo 2captcha sin refresco manual - Context

**Gathered:** 2026-07-31
**Status:** Ready for planning

<domain>
## Task Boundary

Dos bugs de UI reportados por el usuario en `/admin/voters`:

1. El banner "Última revalidación finalizada" (`RevalidationProgressWidget` /
   `resources/views/filament/widgets/revalidation-progress-widget.blade.php`) se queda visible
   indefinidamente después de que termina una revalidación — no tiene botón de cerrar ni condición
   de expiración. El widget hace `wire:poll` cada 5s y siempre renderiza el `RevalidationRun` más
   reciente de la campaña.

2. El badge "Saldo 2captcha" (`resources/views/filament/components/saldos-badge.blade.php`) solo
   lee el último `TwoCaptchaBalanceSnapshot` (snapshot horario vía
   `Schedule::command('balances:snapshot-2captcha')->hourly()`). No hay forma de forzar una
   consulta en vivo — el usuario debe esperar hasta la próxima hora y recargar la página.

</domain>

<decisions>
## Implementation Decisions

### Banner de revalidación — cierre
- Agregar botón de cerrar (X) al banner "Última revalidación finalizada" (NO al estado "en
  progreso" — ese debe seguir visible mientras corre).
- El cierre es por sesión/cliente: una vez cerrado, el banner no debe reaparecer para ese mismo
  `RevalidationRun` (identificar el run por su `id`, ej. guardado en localStorage vía Alpine.js,
  siguiendo el patrón existente de plugins Alpine ya usados en el proyecto — `persist` está
  disponible per Livewire core rules).
- Si corre una revalidación nueva (nuevo `run->id`), el banner debe volver a aparecer normalmente.
- Solo cierre manual — NO se pidió auto-ocultar por tiempo. No implementar temporizador de
  auto-hide.

### Saldo 2captcha — refresco
- Agregar un botón "Refrescar" dentro del dropdown de saldos (`saldos-badge.blade.php`), junto al
  saldo 2captcha.
- Al pulsarlo, debe disparar una llamada real a la API de 2captcha vía `TwoCaptchaService` (mismo
  servicio que usa `SnapshotTwoCaptchaBalance`), y debe persistir el resultado como un nuevo
  `TwoCaptchaBalanceSnapshot` (mismo mecanismo que el snapshot horario) para que el promedio diario
  y el historial se mantengan consistentes — no solo actualizar el número en pantalla sin guardar
  snapshot.
- Es una acción bajo demanda (consume cuota de la API cada vez que se pulsa) — no agregar
  `wire:poll` automático al dropdown.
- Este botón debe ser accesible solo donde ya se muestra el badge (`CampaignContext::isSuperAdmin()`
  gate existente) — no cambiar esa visibilidad.

### Claude's Discretion
- Mecanismo Livewire/Alpine exacto para el estado de "cerrado" del banner (localStorage directo vs
  Alpine `$persist`) — usar el patrón más simple y consistente con el resto del proyecto.
- Si el botón de refrescar debe ser una Livewire action en un componente, o un endpoint/acción
  Filament — decidir según cómo esté estructurado `saldos-badge.blade.php` actualmente (es un
  partial de Blade, no un componente Livewire propio — puede requerir envolverlo en un componente
  Livewire pequeño o usar `wire:click` sobre el layout padre si ya es Livewire).
- Feedback visual mientras se refresca el saldo (loading state) — usar componentes Filament
  existentes (`x-filament::loading-indicator`), consistente con el patrón ya visto en
  `revalidation-progress-widget.blade.php`.

</decisions>

<specifics>
## Specific Ideas

No hay ejemplos específicos de UI de referencia — usar componentes Filament existentes
(`x-filament::icon-button`, `x-filament::loading-indicator`) ya usados en el proyecto.

</specifics>

<canonical_refs>
## Canonical References

No hay specs/ADRs externos — requisitos capturados completos en las decisiones arriba.

</canonical_refs>
