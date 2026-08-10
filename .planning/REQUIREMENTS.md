# Requirements: SIGMA - Sistema Integral de Gestion y Analisis Electoral

**Defined:** 2026-08-10
**Core Value:** Campaign teams can run critical voter and field operations from one place with trustworthy, campaign-safe data and clear operational traceability.

## v1.2 Requirements

Requirements for the "Articuladores + Metadata de Usuario" milestone. Each maps to roadmap phases.

### Jerarquía de Articulador (ARTIC)

- [x] **ARTIC-01**: Superadmin/admin_campaign puede crear un usuario con rol Articulador (`area_coordinator`)
- [ ] **ARTIC-02**: Articulador crea y gestiona coordinadores desde su propio panel de auto-gestión (mirroring el panel de auto-gestión que ya tiene coordinador)
- [x] **ARTIC-03**: Coordinador sigue funcionando exactamente igual que hoy, tenga o no un articulador asignado
- [x] **ARTIC-04**: Sin anidamiento adicional — no existe articulador de articuladores, ni coordinador con sub-coordinadores
- [x] **ARTIC-05**: Sin límite duro de coordinadores por articulador — es solo organizativo, no se valida en backend

### Autorización y Continuidad de Jerarquía (AUTHZ)

- [x] **AUTHZ-01**: Los widgets/exports/dashboards existentes que asumen que el coordinador es el tope de la jerarquía (p. ej. TopLeadersTable, TopLeadersExport, LeadersExportController) se actualizan para resolver correctamente el equipo transitivo de un articulador
- [x] **AUTHZ-02**: Existe una política explícita que impide que un articulador vea/edite coordinadores que no le pertenecen
- [x] **AUTHZ-03**: El nuevo rol respeta el aislamiento de campaña existente (`CampaignMembershipScope`)

### Catálogo de Metadata (META)

- [ ] **META-01**: Superadmin puede crear/editar/desactivar llaves del catálogo de metadata (nombre + tipo: numérico, texto, fecha, selección con opciones)
- [ ] **META-02**: Las llaves de metadata no se pueden crear libremente fuera del catálogo (freeform prohibido)
- [ ] **META-03**: Un superior (líder/coordinador/articulador/superadmin) puede asignar un valor de una llave del catálogo a uno de sus subordinados directos
- [ ] **META-04**: Un superior puede asignar el mismo valor de metadata a varios subordinados a la vez (asignación masiva)
- [ ] **META-05**: Cada asignación de metadata queda auditada (quién asignó qué valor, a quién, cuándo)
- [ ] **META-06**: Las escrituras de metadata son atómicas por llave (sin condiciones de carrera entre asignaciones concurrentes al mismo usuario)

### Filtro, Orden y Exportación (FILT)

- [ ] **FILT-01**: Las tablas Filament de usuarios/coordinadores/líderes/articuladores permiten filtrar por llave y valor de metadata
- [ ] **FILT-02**: Las mismas tablas permiten ordenar por valor de una llave de metadata, con orden numérico correcto para llaves tipo número (no alfabético)
- [ ] **FILT-03**: Los exports CSV existentes de usuarios/coordinadores/líderes incluyen las columnas de metadata asignada

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
| ARTIC-02 | Phase 15 | Pending |
| ARTIC-03 | Phase 14 | Done |
| ARTIC-04 | Phase 12 | Done |
| ARTIC-05 | Phase 12 | Done |
| AUTHZ-01 | Phase 13 | Done |
| AUTHZ-02 | Phase 13 | Done |
| AUTHZ-03 | Phase 13 | Done |
| META-01 | Phase 16 | Pending |
| META-02 | Phase 16 | Pending |
| META-03 | Phase 16 | Pending |
| META-04 | Phase 16 | Pending |
| META-05 | Phase 16 | Pending |
| META-06 | Phase 16 | Pending |
| FILT-01 | Phase 17 | Pending |
| FILT-02 | Phase 17 | Pending |
| FILT-03 | Phase 17 | Pending |

**Coverage:**
- v1.2 requirements: 17 total
- Mapped to phases: 17
- Unmapped: 0 ✓

---
*Requirements defined: 2026-08-10*
*Last updated: 2026-08-10 — roadmap created, all 17 v1.2 requirements mapped to Phases 12-17*
