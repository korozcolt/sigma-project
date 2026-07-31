# Quick Task 260731-nuk: Crear Filament Resource de solo lectura para visualizar audit_logs - Context

**Gathered:** 2026-07-31
**Status:** Ready for planning

<domain>
## Task Boundary

Crear un Filament Resource de solo lectura (sin create/edit/delete desde la UI) para visualizar la
tabla `audit_logs` creada en la tarea `260731-n0n` (ver `app/Models/AuditLog.php`). El Resource debe
listar quién (user), qué acción (`created`/`updated`/`deleted`/`login`/`logout`/`login_failed`),
sobre qué modelo (`auditable_type`/`auditable_id`), cuándo (`created_at`), y permitir ver el detalle
de `old_values`/`new_values`.

</domain>

<decisions>
## Implementation Decisions

### Visibilidad / roles
- Solo **Super Admin** puede ver este Resource — mismo gate que el badge de Saldos
  (`CampaignContext::isSuperAdmin()`). Admin de Campaña, Coordinador, Líder, Revisor y Analista de
  Reportes NO tienen acceso.
- No es necesario, por ahora, aislamiento por campaña dentro del Resource (solo super admin lo ve, y
  super admin ya tiene visibilidad de todas las campañas en el resto del panel) — pero SÍ debe
  mostrarse la columna/valor de `campaign_id` en la tabla para que el super admin pueda identificar a
  qué campaña pertenece cada registro cuando exista más de una.

### Filtros
- Filtros de entrada obligatorios: por **usuario** (quién hizo la acción), por **acción**
  (created/updated/deleted/login/logout/login_failed), y por **rango de fecha** (`created_at`).
- No es necesario filtro por tipo de modelo auditado en esta iteración (se puede agregar después si
  se vuelve necesario).

### Solo lectura
- Sin páginas de crear/editar. Sin `DeleteAction`/`DeleteBulkAction`/`BulkActions` de borrado — los
  audit logs no deben poder eliminarse desde la UI.
- Página de detalle (`ViewAuditLog` o modal de infolist) para mostrar `old_values`/`new_values`
  formateados (JSON legible), ya que esas columnas no caben bien en la tabla.

### Claude's Discretion
- Nombre exacto de la clase Resource (`AuditLogResource`) y su ubicación (`app/Filament/Resources/`),
  siguiendo la convención de recursos existentes en el proyecto.
- Grupo de navegación / posición en el sidebar (ej. agrupar bajo algo tipo "Sistema" o similar si
  existe un patrón parecido; si no, decidir la agrupación más razonable).
- Cómo formatear `old_values`/`new_values` en el detalle (tabla key-value, JSON con syntax highlight,
  etc.) — usar componentes Filament existentes (Infolist).
- Paginación por defecto y orden (recomendado: descendente por `created_at`, tamaño de página
  razonable dado que la tabla puede crecer).
- Cómo mostrar `auditable_type`/`auditable_id` de forma legible (ej. resolver el nombre corto de la
  clase en vez del FQCN completo).

</decisions>

<specifics>
## Specific Ideas

No hay ejemplos visuales de referencia — usar componentes Filament Table/Infolist existentes,
consistente con otros Resources del proyecto (ver convenciones en `app/Filament/Resources/`).

</specifics>

<canonical_refs>
## Canonical References

- `app/Models/AuditLog.php` (tarea 260731-n0n) — modelo base sobre el que se construye este Resource.
- `database/migrations/2026_07_31_120000_create_audit_logs_table.php` — schema de referencia.

</canonical_refs>
