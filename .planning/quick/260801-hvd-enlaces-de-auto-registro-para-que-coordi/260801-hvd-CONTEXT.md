# Quick Task 260801-hvd: Enlaces de auto-registro de líderes + agregar apoyos desde detalle de líder (coordinador) - Context

**Gathered:** 2026-08-01
**Status:** Ready for planning

<domain>
## Task Boundary

Feature de cliente con 3 partes sobre la jerarquía Coordinador → Líder → Apoyo:

1. Enlace de auto-registro para que un coordinador invite a un líder a registrarse él mismo (self-registration vía token), sin pasar por el formulario manual `coordinator/leaders/create` (que sigue existiendo, sin cambios).
2. Botón "Agregar Apoyo" dentro de `coordinator/leaders/{leader}/voters` (vista de detalle de un líder específico, dentro del panel del coordinador) para que el coordinador registre un apoyo a nombre de ese líder.
3. Mostrar cuántos apoyos tiene cada líder en el listado de líderes.

</domain>

<code_context>
## Hallazgos de investigación (agente de investigación, 2026-08-01)

- **No hay modelos `Coordinator`/`Leader` separados** — Coordinador y Líder son el mismo modelo `App\Models\User`, diferenciados por rol Spatie. Jerarquía: `users.coordinator_user_id` (auto-FK) para Coordinador→Líder, `voters.registered_by` (FK a users) para Líder→Apoyo.
- **Feature #3 YA EXISTE, no requiere trabajo nuevo**: `resources/views/livewire/coordinator/leaders.blade.php:226` ya muestra `{{ $leader->voters_count }} apoyos registrados` (vía `withCount(['registeredVoters as voters_count'])` en `with()`, línea 56). El Filament `LeadersTable.php` (panel admin) también ya tiene `registered_voters_count` vía `->counts('registeredVoters')`. Verificar en el plan que ambos siguen funcionando correctamente pero no hace falta construir nada — solo confirmar/documentar en el SUMMARY que ya estaba implementado.
- **Feature #2 — la pantalla ya existe pero es solo lectura**: `resources/views/livewire/coordinator/leader-voters.blade.php` (ruta `coordinator.leaders.voters`, `GET coordinator/leaders/{leader}/voters`) ya lista los apoyos de un líder con stats y filtros, pero no tiene ninguna acción para crear un apoyo nuevo. Falta agregar un botón/modal "Agregar Apoyo" que reutilice el mismo patrón de formulario y validación que `resources/views/livewire/leader/register-voter.blade.php` (incluye cruce de censo/Registraduría), pero seteando `registered_by` al líder de la pantalla (`$this->leader->id`), no al usuario autenticado.
- **Feature #1 — genuinamente nueva**: existe un sistema de `Invitation` con token (`app/Models/Invitation.php`, `app/Services/InvitationService.php`, middleware `RequireInvitationForRegistration`, ruta pública `registro/{token}` → `PublicVoterRegistrationController`), pero **solo sirve hoy para que un líder invite apoyos** — siempre requiere `leader_user_id`, y `PublicVoterRegistrationController::store()` únicamente hace `Voter::create()`. El modelo tiene un campo `target_role` ('LEADER'/'COORDINATOR') que hoy no lo usa ningún controlador — es vestigial/no conectado a ninguna lógica real de creación de usuarios. El `InvitationResource` de Filament (formulario para generar estas invitaciones) vive **solo en el panel admin** (`AdminPanelProvider`), no accesible a coordinadores normales (que usan el panel Volt `coordinator`).
- Patrón de creación manual de líder a reutilizar como referencia de campos/validación: `resources/views/livewire/coordinator/create-leader.blade.php` (ruta `coordinator.leaders.create`) — captura name/email/password/phone (con OTP vía `OtpVerificationService`), document_number (con lookup Registraduría vía `IdentityLookupService`), neighborhood_id; en `save()` crea `User::create([...'coordinator_user_id' => $coordinatorUser->id])`, asigna rol `LEADER`, copia campañas del coordinador al líder.
- Scopes de campaña: `User` usa `CampaignMembershipScope` (vía relación `campaigns()`), `Voter` usa `CampaignContextScope` (columna directa `campaign_id`). Ambos son global scopes automáticos — no hay que replicarlos a mano, pero si el nuevo flujo público corre sin usuario autenticado (como el de apoyos), hay que asignar `campaign_id`/`coordinator_user_id` explícitamente igual que hace `PublicVoterRegistrationController`.
- No hay Policy central para User/Coordinator/Leader — el filtrado "qué coordinador ve qué líderes" se hace a mano por query (`where('coordinator_user_id', $user->id)`), replicar el mismo patrón para cualquier ruta pública/nueva.

</code_context>

<decisions>
## Implementation Decisions

### Origen del enlace de auto-registro (Feature #1)
- El propio coordinador genera el enlace desde su panel (self-service), no requiere pasar por el admin/Filament InvitationResource. Debe vivir dentro de `coordinator/leaders.blade.php` (o una acción accesible desde ahí), como botón "Generar enlace de registro" junto a "Agregar Líder".

### Nivel de verificación del formulario público de auto-registro de líder
- Debe exigir la MISMA verificación que el formulario manual `create-leader.blade.php`: OTP por SMS (`OtpVerificationService`) + lookup de cédula contra Registraduría (`IdentityLookupService`). No se acepta un registro simplificado sin OTP.

### Registro de apoyo agregado desde el detalle de un líder (Feature #2)
- El apoyo creado desde `coordinator/leaders/{leader}/voters` debe quedar con `registered_by = leader->id` — exactamente como si el líder lo hubiera registrado él mismo. No se agrega ningún campo/columna nueva para distinguir "ingresado por el coordinador en nombre del líder". Reutilizar el mismo formulario/lógica de validación (incluyendo cruce de censo) que `leader/register-voter.blade.php`.

### Claude's Discretion
- Ciclo de vida del link de invitación de líder (expiración, reutilización, estado pending/expired/cancelled): seguir exactamente el mismo patrón ya usado por `Invitation` para apoyos (token aleatorio de 60 chars, `expires_at` default +7 días, status pending/expired/cancelled) — no se preguntó explícitamente al usuario, pero es la convención ya establecida en el código y evita inconsistencia.
- Si reutilizar el modelo `Invitation` existente extendiéndolo (branch por `target_role`) vs. crear un modelo/tabla nueva específica para invitaciones de líder — el planner debe decidir basado en el menor costo de migración; el modelo ya tiene `target_role` y `coordinator_user_id` como campos vestigiales pensados aparentemente para este caso, así que extender es la opción más barata y consistente.
- Si el nuevo líder auto-registrado queda activo de inmediato (puede loguearse apenas completa el form + OTP) o requiere aprobación de un admin — no se preguntó; por defecto, activo de inmediato, igual que el líder creado manualmente por `create-leader.blade.php` (no existe hoy ningún flujo de aprobación de cuentas en el sistema).
- Feature #3 (conteo de apoyos) no requiere tareas de implementación — solo verificar que sigue funcionando tras los cambios de #1 y #2, y documentarlo como "ya existente" en el SUMMARY para que el cliente sepa que ese punto ya estaba resuelto.

</decisions>

<specifics>
## Specific Ideas

- Botón "Generar enlace de registro" en `coordinator/leaders.blade.php`, junto a "Agregar Líder" / "Exportar Líderes".
- Botón "Agregar Apoyo" en `coordinator/leader-voters.blade.php`, junto al botón "Volver".
- Reusar componentes/servicios existentes (`OtpVerificationService`, `IdentityLookupService`, patrón de `Invitation`) en vez de crear mecanismos paralelos.

</specifics>

<canonical_refs>
## Canonical References

No hay specs/ADRs externos — requerimientos capturados en las decisiones de arriba, basados en la investigación directa del código existente (ver `<code_context>`).

</canonical_refs>
