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
**Estado:** ✅ Aceptada
**Afecta a:** FASE 3

#### Contexto
Necesitamos definir cómo modelar Coordinadores y Líderes en la base de datos.

#### Decisión
Usar **roles en `users`** y **relación `campaign_user`** para asignación por campaña. Coordinadores y líderes son usuarios con rol + asignación en pivot.

#### Consecuencias
- Simplicidad en modelo de usuarios.
- Multi-campaña gestionada con `campaign_user`.
- Para campos específicos, se usa perfil o flags en `users` según necesidad.

#### Alternativas Consideradas
- Tablas separadas para coordinators/leaders (mayor complejidad).

---

### PD-002: API de Mensajería (WhatsApp/SMS)

**Fecha:** 2025-11-02
**Estado:** ✅ Aceptada
**Afecta a:** FASE 6.2

#### Contexto
Necesitamos enviar mensajes de cumpleaños y recordatorios vía WhatsApp y SMS.

#### Decisión
Definir un **driver de SMS** con interfaz única y drivers conmutables por configuración:
- `SmsDriverInterface`
- `HablameDriver` (real, con credenciales)
- `NullDriver` (no-op)
- `LogDriver` (QA, registra en logs)

#### Consecuencias
- Tests funcionan sin credenciales.
- Cambiar proveedor no implica cambios en lógica de negocio.

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
**Estado:** ✅ Aceptada
**Afecta a:** FASE 2

#### Contexto
SIGMA es multi-campaña. ¿Cómo aislar datos?

#### Decisión
**Soft Multi-tenancy con `campaign_id` + Campaign Context + Global Scopes + Policies.**
- Contexto de campaña activo por usuario.
- Scopes globales en modelos multi-campaña.
- Enforcements en writes (campaign_id desde contexto).
- `super_admin` puede cambiar contexto o ver todo.

#### Consecuencias
- Aislamiento estricto sin múltiples bases de datos.
- Requiere disciplina de scopes y context.

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

### ADR-006: Convención de Idiomas (UI vs Código)

**Fecha:** 2025-11-03
**Estado:** ✅ Aceptada
**Decidido por:** Equipo desarrollo
**Afecta a:** Todo el proyecto

#### Contexto
SIGMA está diseñado para usuarios hispanohablantes en Colombia, pero el equipo de desarrollo trabaja con mejores prácticas internacionales que usan inglés para código.

#### Decisión
**Regla de Oro: Español en UI, Inglés en Código**

**Interfaz de Usuario (Español):**
- Todos los labels de Filament Resources: `$navigationLabel`, `$modelLabel`, `$pluralModelLabel`
- Todos los grupos de navegación: `$navigationGroup`
- Labels de formularios: `->label('Nombre')`
- Labels de columnas de tablas: `->label('Departamento')`
- Mensajes de validación
- Notificaciones al usuario
- Textos de ayuda: `->helperText()`
- Placeholders de inputs
- Opciones de selects cuando son texto libre

**Código (Inglés):**
- Nombres de modelos: `Department`, `Municipality`, `Neighborhood`
- Nombres de variables: `$department`, `$municipalityId`
- Nombres de métodos: `createVoter()`, `validateAgainstCensus()`
- Nombres de columnas en BD: `department_id`, `created_at`
- Nombres de clases: `VoterStatus`, `CampaignController`
- Nombres de tests: `it('can create a department')`
- Comentarios de código (preferiblemente)
- Commits y mensajes de Git
- Documentación técnica

**Excepciones:**
- Enums con valores string pueden usar español si van directo a UI
- Seeders de datos reales (ej: nombres de departamentos de Colombia)
- Content en migraciones de datos iniciales

#### Ejemplos

✅ **Correcto:**
```php
// Filament Resource
class DepartmentResource extends Resource
{
    protected static ?string $navigationLabel = 'Departamentos';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $modelLabel = 'Departamento';

    // Pero la clase se llama DepartmentResource (inglés)
}

// Form
Select::make('department_id')
    ->label('Departamento')  // español
    ->helperText('Seleccione el departamento')  // español
    ->relationship('department', 'name')  // código en inglés
    ->required();
```

❌ **Incorrecto:**
```php
// NO hacer esto
class DepartamentoResource extends Resource  // ❌
{
    protected static ?string $navigationLabel = 'Departments';  // ❌
}

Select::make('departamento_id')  // ❌ columna debe ser department_id
    ->label('Department')  // ❌ debe ser español
```

#### Consecuencias

**Positivas:**
- Mejor experiencia para usuarios hispanohablantes
- Código sigue estándares internacionales
- Fácil colaboración con desarrolladores de otros países
- Mejor compatibilidad con packages de terceros
- IDE autocomplete funciona mejor con inglés
- Stack Overflow y documentación en inglés más accesible

**Negativas:**
- Equipo debe dominar inglés técnico básico
- Cambio de contexto mental entre UI y código
- Más trabajo inicial para traducir labels

#### Implementación

1. **Filament Resources**: Siempre incluir estas propiedades en español:
```php
protected static ?string $navigationLabel = '[Nombre en español]';
protected static ?string $navigationGroup = '[Grupo en español]';
protected static ?string $modelLabel = '[Singular en español]';
protected static ?string $pluralModelLabel = '[Plural en español]';
```

2. **Formularios y Tablas**: Todo método `->label()` debe estar en español

3. **Validación**: Usar archivos de idioma de Laravel en español (`lang/es/`)

4. **Migraciones**: Mantener nombres de columnas en inglés y snake_case

#### Referencias
- Laravel Docs - Localization: https://laravel.com/docs/localization
- Filament Docs - Resources: https://filamentphp.com/docs/resources

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

**Última Actualización:** 2025-11-03
