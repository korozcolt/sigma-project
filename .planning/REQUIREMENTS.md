# Requirements: SIGMA - Sistema Integral de Gestion y Analisis Electoral

**Defined:** 2026-08-10
**Core Value:** Campaign teams can run critical voter and field operations from one place with trustworthy, campaign-safe data and clear operational traceability.

## v1.2 Requirements

Requirements for the "Articuladores + Metadata de Usuario" milestone. Each maps to roadmap phases.

### Jerarquía de Articulador (ARTIC)

- [x] **ARTIC-01**: Superadmin/admin_campaign puede crear un usuario con rol Articulador (`area_coordinator`)
- [x] **ARTIC-02**: Articulador crea y gestiona coordinadores desde su propio panel de auto-gestión (mirroring el panel de auto-gestión que ya tiene coordinador)
- [x] **ARTIC-03**: Coordinador sigue funcionando exactamente igual que hoy, tenga o no un articulador asignado
- [x] **ARTIC-04**: Sin anidamiento adicional — no existe articulador de articuladores, ni coordinador con sub-coordinadores
- [x] **ARTIC-05**: Sin límite duro de coordinadores por articulador — es solo organizativo, no se valida en backend

### Autorización y Continuidad de Jerarquía (AUTHZ)

- [ ] **AUTHZ-01**: Los widgets/exports/dashboards existentes que asumen que el coordinador es el tope de la jerarquía (p. ej. TopLeadersTable, TopLeadersExport, LeadersExportController) se actualizan para resolver correctamente el equipo transitivo de un articulador (query correcta desde Fase 13; alcanzabilidad de `LeadersExportController` para el rol articulador pendiente — ver Fase 18)
- [x] **AUTHZ-02**: Existe una política explícita que impide que un articulador vea/edite coordinadores que no le pertenecen
- [x] **AUTHZ-03**: El nuevo rol respeta el aislamiento de campaña existente (`CampaignMembershipScope`)

### Catálogo de Metadata (META)

- [x] **META-01**: Superadmin puede crear/editar/desactivar llaves del catálogo de metadata (nombre + tipo: numérico, texto, fecha, selección con opciones)
- [x] **META-02**: Las llaves de metadata no se pueden crear libremente fuera del catálogo (freeform prohibido)
- [x] **META-03**: Un superior (líder/coordinador/articulador/superadmin) puede asignar un valor de una llave del catálogo a uno de sus subordinados directos
- [x] **META-04**: Un superior puede asignar el mismo valor de metadata a varios subordinados a la vez (asignación masiva)
- [x] **META-05**: Cada asignación de metadata queda auditada (quién asignó qué valor, a quién, cuándo)
- [x] **META-06**: Las escrituras de metadata son atómicas por llave (sin condiciones de carrera entre asignaciones concurrentes al mismo usuario)

### Filtro, Orden y Exportación (FILT)

- [x] **FILT-01**: Las tablas Filament de usuarios/coordinadores/líderes/articuladores permiten filtrar por llave y valor de metadata
- [x] **FILT-02**: Las mismas tablas permiten ordenar por valor de una llave de metadata, con orden numérico correcto para llaves tipo número (no alfabético)
- [x] **FILT-03**: Los exports CSV existentes de usuarios/coordinadores/líderes incluyen las columnas de metadata asignada

## v2 Requirements

Deferred to a future release. Tracked but not in this milestone's roadmap.

### Metadata Avanzada

- **META-07**: Valores de metadata con vigencia histórica (punto-en-el-tiempo — "qué valor tenía esta llave en la fecha X")
- **META-08**: Dashboards de agregación/rollup de metadata (p. ej. total de biáticos por equipo de un articulador)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Anidamiento de jerarquía más allá de un nivel (articulador de articuladores) | Explícitamente descartado por el cliente — un solo nivel extra sobre coordinador |
| Compartir catálogo de metadata o jerarquía entre campañas | El aislamiento por campaña es un requisito de producto, no una conveniencia |
| Eliminar (hard delete) llaves del catálogo con asignaciones existentes | Se desactivan (soft-deactivate) en vez de borrarse, para no perder el historial de auditoría |
| Cascada automática de equipo al reasignar un coordinador a otro articulador | Líderes/apoyos no dependen de `area_coordinator_user_id` directamente — la jerarquía se resuelve coordinador→articulador, no denormalizada; reasignar un coordinador es un cambio de una sola columna sin cascada |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| ARTIC-01 | Phase 14 | Done |
| ARTIC-02 | Phase 15 | Done |
| ARTIC-03 | Phase 14 | Done |
| ARTIC-04 | Phase 12 | Done |
| ARTIC-05 | Phase 12 | Done |
| AUTHZ-01 | Phase 13 (query logic), Phase 18 (reachability gap closure) | Pending |
| AUTHZ-02 | Phase 13 | Done |
| AUTHZ-03 | Phase 13 | Done |
| META-01 | Phase 16 | Done |
| META-02 | Phase 16 | Done |
| META-03 | Phase 16 | Done |
| META-04 | Phase 16 | Done |
| META-05 | Phase 16 | Done |
| META-06 | Phase 16 | Done |
| FILT-01 | Phase 17 | Done |
| FILT-02 | Phase 17 | Done |
| FILT-03 | Phase 17 | Done |

**Coverage:**
- v1.2 requirements: 17 total
- Mapped to phases: 17
- Unmapped: 0 ✓
- Pending (gap closure): 1 (AUTHZ-01 — Phase 18)

---
*Requirements defined: 2026-08-10*
*Last updated: 2026-08-10 — roadmap created, all 17 v1.2 requirements mapped to Phases 12-17*
