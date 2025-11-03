# 🎯 Registro de Decisiones Técnicas (ADR)

**Architecture Decision Records para SIGMA**

Este documento registra decisiones técnicas importantes tomadas durante el desarrollo del proyecto.

---

## Formato de Registro

Cada decisión sigue este formato:

```markdown
### ADR-XXX: Título de la Decisión

**Fecha:** YYYY-MM-DD
**Estado:** Propuesta | Aceptada | Rechazada | Obsoleta
**Decidido por:** Nombre
**Afecta a:** Fase/Módulo

#### Contexto
Descripción del problema o situación que requiere decisión.

#### Decisión
Qué se decidió hacer.

#### Consecuencias
Implicaciones positivas y negativas de la decisión.

#### Alternativas Consideradas
- Alternativa 1: descripción
- Alternativa 2: descripción

#### Referencias
- Enlaces o documentos relacionados
```

---

## Decisiones Pendientes

### PD-001: Estructura de Coordinadores y Líderes

**Fecha:** 2025-11-02
**Estado:** ⏳ Pendiente de Decisión
**Afecta a:** FASE 3

#### Contexto
Necesitamos definir cómo modelar Coordinadores y Líderes en la base de datos.

#### Opciones

**Opción A: Usar solo roles en tabla users**
```php
// Users tienen roles: 'coordinator', 'leader'
// Relaciones directas desde User
```

**Pros:**
- Más simple
- Menos tablas
- Reutiliza User model

**Contras:**
- Mezcla concerns
- Menos flexible para campos específicos
- Dificulta queries especializadas

**Opción B: Tablas separadas con relación a User**
```php
// Tabla coordinators (user_id, campaign_id, territory_id, etc)
// Tabla leaders (user_id, campaign_id, coordinator_id, etc)
```

**Pros:**
- Separación de concerns clara
- Campos específicos por tipo
- Queries más eficientes
- Mejor para reportes

**Contras:**
- Más tablas
- Más modelos
- Más complejidad

**Recomendación:** Opción B (tablas separadas)

**Razón:** Mayor flexibilidad y escalabilidad para campos específicos del dominio electoral.

---

### PD-002: API de Mensajería (WhatsApp/SMS)

**Fecha:** 2025-11-02
**Estado:** ⏳ Pendiente de Decisión
**Afecta a:** FASE 6.2

#### Contexto
Necesitamos enviar mensajes de cumpleaños y recordatorios vía WhatsApp y SMS.

#### Opciones para WhatsApp

**Opción A: WhatsApp Business API (Oficial)**
- Requiere aprobación de Meta
- Más confiable
- Más costoso
- Oficial

**Opción B: Twilio API para WhatsApp**
- Fácil integración
- Costo moderado
- Bien documentado

**Opción C: Biblioteca no oficial**
- Riesgo de bloqueo
- No recomendado para producción

#### Opciones para SMS

**Opción A: Twilio SMS**
- Confiable
- Bien documentado
- Costo razonable

**Opción B: AWS SNS**
- Si ya usan AWS
- Buena integración

**Opción C: Proveedor local**
- Depende del país

**Recomendación:** Pendiente de presupuesto y país de operación

---

### PD-003: Base de Datos en Producción

**Fecha:** 2025-11-02
**Estado:** ⏳ Pendiente de Decisión
**Afecta a:** Deployment

#### Contexto
Actualmente usando SQLite en desarrollo. Para producción debemos decidir motor.

#### Opciones

**Opción A: PostgreSQL**
- Más robusto
- Mejor para reporting
- JSON support nativo
- Recomendado para multi-tenancy

**Opción B: MySQL/MariaDB**
- Más común
- Buen performance
- Amplio soporte

**Opción C: Mantener SQLite**
- Solo para apps muy pequeñas
- No recomendado para producción

**Recomendación:** PostgreSQL

**Razón:** Mejor soporte para JSON (configuraciones), mejor performance con queries complejas, mejor para analítica.

---

### PD-004: Multi-tenancy Strategy

**Fecha:** 2025-11-02
**Estado:** ⏳ Pendiente de Decisión
**Afecta a:** FASE 2

#### Contexto
SIGMA es multi-campaña. ¿Cómo aislar datos?

#### Opciones

**Opción A: Soft Multi-tenancy (campo campaign_id)**
```php
// Todos los datos en misma BD
// Cada registro tiene campaign_id
// Scopes globales para filtrar
```

**Pros:**
- Simple implementación
- Una sola base de datos
- Fácil backup

**Contras:**
- Riesgo de data leak
- Queries más complejas
- Menos seguro

**Opción B: Database per Campaign**
```php
// Cada campaña tiene su propia base de datos
// Usando paquete como stancl/tenancy
```

**Pros:**
- Aislamiento total
- Más seguro
- Mejor performance por campaña

**Contras:**
- Más complejo
- Más costoso (recursos)
- Backups más complejos

**Opción C: Schema per Campaign (PostgreSQL)**
```php
// Misma BD, esquema diferente por campaña
```

**Pros:**
- Balance entre A y B
- Buen aislamiento
- Un solo servidor

**Contras:**
- Solo PostgreSQL
- Complejidad media

**Recomendación:** Opción A (Soft Multi-tenancy)

**Razón:** Para v1, simplicidad es clave. Si se requiere más seguridad después, migrar a B o C.

---

## Decisiones Aceptadas

### ADR-001: Framework y Stack Tecnológico

**Fecha:** 2025-11-02
**Estado:** ✅ Aceptada
**Decidido por:** Equipo inicial
**Afecta a:** Todo el proyecto

#### Contexto
Necesitábamos seleccionar stack tecnológico para SIGMA.

#### Decisión
- **Backend:** Laravel 12
- **Admin Panel:** Filament v4
- **Frontend Interactivo:** Livewire v3 + Volt
- **UI Components:** Flux UI (free)
- **Styling:** Tailwind CSS v4
- **Testing:** Pest v4
- **Auth:** Laravel Fortify
- **Base de Datos Dev:** SQLite

#### Consecuencias

**Positivas:**
- Stack moderno y mantenido
- Excelente DX (Developer Experience)
- Filament acelera desarrollo de admin
- Livewire elimina necesidad de API REST interna
- Pest hace testing más agradable

**Negativas:**
- Curva de aprendizaje si equipo no conoce stack
- Livewire requiere JavaScript habilitado
- Filament v4 es reciente (menos recursos online)

#### Alternativas Consideradas
- **Laravel + Vue/React:** Más complejo, requiere API
- **Django + Django Admin:** Fuera de expertise del equipo
- **Ruby on Rails + ActiveAdmin:** Menos popular actualmente

---

### ADR-002: Sistema de Roles y Permisos

**Fecha:** 2025-11-02
**Estado:** ✅ Aceptada
**Decidido por:** Equipo desarrollo
**Afecta a:** FASE 0

#### Contexto
Necesitamos gestionar 5 roles diferentes con permisos variados.

#### Decisión
Usar `spatie/laravel-permission` package.

#### Consecuencias

**Positivas:**
- Package estable y bien mantenido
- Integración perfecta con Laravel
- Soporte para roles y permisos
- Filament tiene integración nativa
- Amplia documentación

**Negativas:**
- Dependencia externa
- Tablas adicionales en BD

#### Alternativas Consideradas
- **Laravel Gates/Policies solo:** Menos flexible
- **Bouncer:** Similar pero menos popular
- **Custom implementation:** Reinventar la rueda

---

### ADR-003: Estrategia de Testing

**Fecha:** 2025-11-02
**Estado:** ✅ Aceptada
**Decidido por:** Equipo desarrollo
**Afecta a:** Todas las fases

#### Contexto
Necesitamos asegurar calidad de código y evitar regresiones.

#### Decisión
- Usar **Pest v4** para todos los tests
- **Feature tests** para flujos de usuario
- **Unit tests** para lógica de negocio aislada
- **Browser tests** para flujos críticos (votación)
- Target: **80% code coverage**
- Tests obligatorios antes de merge

#### Consecuencias

**Positivas:**
- Mayor confianza en código
- Detección temprana de bugs
- Documentación viva del sistema
- Facilita refactoring

**Negativas:**
- Más tiempo de desarrollo inicial
- Requiere disciplina del equipo

---

### ADR-004: Versionado de Código

**Fecha:** 2025-11-02
**Estado:** ✅ Aceptada
**Decidido por:** Equipo desarrollo
**Afecta a:** Todo el proyecto

#### Contexto
Necesitamos estrategia de branching y versionado.

#### Decisión
- **Git Flow simplificado**
- Branch principal: `main`
- Branch de desarrollo: `develop`
- Feature branches: `feature/nombre-corto`
- Commits semánticos: `tipo(scope): descripción`
- Versioning semántico: `v1.0.0`

#### Consecuencias

**Positivas:**
- Historial limpio
- Fácil tracking de features
- Rollback sencillo
- CI/CD más simple

---

### ADR-005: Estructura de Enums

**Fecha:** 2025-11-02
**Estado:** ✅ Aceptada
**Decidido por:** Equipo desarrollo
**Afecta a:** FASE 4, 5

#### Contexto
Estados del votante, estados de validación, etc., necesitan ser consistentes.

#### Decisión
Usar **PHP 8 Enums** para todos los estados:
- `VoterStatus`
- `CampaignStatus`
- `CallResult`
- `MessageChannel`
- etc.

Ubicación: `app/Enums/`

#### Consecuencias

**Positivas:**
- Type safety
- IDE autocomplete
- Menos magic strings
- Más mantenible

**Negativas:**
- Cambios requieren migración
- No dinámico (vs BD)

#### Alternativas Consideradas
- **Estados en BD:** Más flexible pero menos type-safe
- **Constantes en clase:** Menos elegante

---

## Decisiones Rechazadas

### ❌ DR-001: Usar API REST Interna

**Fecha:** 2025-11-02
**Estado:** ❌ Rechazada

#### Razón
Al usar Livewire, no necesitamos API REST interna. Solo crearemos API en FASE 7 para integraciones externas.

---

## Decisiones Obsoletas

_(Ninguna aún)_

---

## Cómo Agregar una Decisión

1. Identificar necesidad de decisión
2. Copiar template de formato
3. Numerar secuencialmente (ADR-XXX)
4. Llenar todas las secciones
5. Discutir con equipo si aplica
6. Marcar estado final
7. Commit con mensaje: `docs: add ADR-XXX about [topic]`

---

**Última Actualización:** 2025-11-02
