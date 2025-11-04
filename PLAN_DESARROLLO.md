# 📋 Plan de Desarrollo SIGMA
## Sistema Integral de Gestión y Análisis Electoral

**Versión del Plan:** 2.0
**Fecha de Creación:** 2025-11-02
**Última Actualización:** 2025-01-21
**Estado del Proyecto:** 70% Completo - Fases Críticas Identificadas

---

## 🎯 Resumen Ejecutivo

### Estado Actual

✅ **COMPLETADO (70%):**
- ✅ Sistema de autenticación completo (Fortify: Login, Registro, 2FA, Reset Password)
- ✅ Panel de administración Filament v4 funcional
- ✅ UI moderna con Volt + Flux UI + Tailwind CSS v4
- ✅ Sistema de roles (5 roles: Super Admin, Admin Campaña, Coordinador, Líder, Revisor)
- ✅ Estructura territorial completa (Department, Municipality, Neighborhood)
- ✅ Sistema multi-campaña operativo
- ✅ Modelos de votantes y censo
- ✅ Sistema de validación contra censo
- ✅ Asignaciones territoriales
- ✅ Sistema de encuestas completo (preguntas, respuestas, métricas)
- ✅ Call Center funcional (asignaciones, llamadas, cola)
- ✅ 410 tests pasando (945 assertions)
- ✅ Base de datos: SQLite (test), MySQL (producción)

⚠️ **CRÍTICO - PENDIENTE (30%):**
- ❌ **Sistema completamente en inglés** (necesita traducción a español)
- ❌ **NO existe UserResource** (no se pueden gestionar usuarios/roles en UI)
- ❌ **NO existe VoterResource** (líderes no pueden registrar votantes en UI)
- ❌ **NO existe SurveyResource** (no se pueden crear encuestas en UI)
- ❌ **NO existe TerritorialAssignmentResource** (no se pueden hacer asignaciones en UI)
- ❌ **NO hay dashboards por rol** (cada rol necesita su vista específica)
- ❌ Reportes y analítica avanzada
- ❌ API REST para integraciones

### Impacto
**Modelos funcionando pero workflow bloqueado:** Toda la lógica de negocio existe en código, pero los usuarios no pueden ejecutar el workflow completo porque faltan las interfaces de administración críticas.

---

## 📊 Estructura del Plan

Este plan está dividido en **10 Fases** principales:

0. **Fase 0:** Configuración Base y Roles ✅
1. **Fase 1:** Estructura Territorial ✅
2. **Fase 2:** Sistema Multi-Campaña ✅
3. **Fase 3:** Gestión de Usuarios y Jerarquía ✅
4. **Fase 4:** Módulo de Votantes ✅
5. **Fase 5:** Validación y Censo Electoral ✅
6. **Fase 6:** Módulos Estratégicos (Encuestas, Call Center) ✅
7. **Fase 7:** Sistema de Traducción (NUEVO - URGENTE) ⏳
8. **Fase 8:** Gestión de Jerarquía y Permisos (NUEVO - CRÍTICO) ⏳
9. **Fase 9:** Reportes y Analítica ⏳

---

## 🔥 FASE 0: Configuración Base y Roles
**Objetivo:** Establecer sistema de permisos y roles para todo el sistema

### Tareas

#### 0.1 Instalación de Sistema de Roles
- [ ] Instalar `spatie/laravel-permission`
- [ ] Configurar middleware de permisos
- [ ] Crear migración para roles y permisos
- [ ] Seeders para roles base

**Roles a Crear:**
```php
- super_admin          // Administrador General
- admin_campaign       // Administrador de Campaña
- coordinator          // Coordinador
- leader               // Líder
- reviewer             // Revisor
```

**Archivos a Crear:**
- `database/migrations/xxxx_create_permission_tables.php`
- `database/seeders/RoleSeeder.php`
- `app/Policies/` (para cada modelo)

**Tests:**
- [ ] Test de asignación de roles
- [ ] Test de permisos por rol
- [ ] Test de políticas de acceso

**Estado:** ⏳ Pendiente

---

## 🗺️ FASE 1: Estructura Territorial
**Objetivo:** Crear el sistema de organización geográfica

### Tareas

#### 1.1 Modelo de Departamento
- [ ] Crear modelo `Department`
- [ ] Migración con campos: `name`, `code`
- [ ] Seeder con departamentos de Colombia
- [ ] Resource de Filament
- [ ] Tests CRUD

**Archivos:**
- `app/Models/Department.php`
- `database/migrations/xxxx_create_departments_table.php`
- `database/seeders/DepartmentSeeder.php`
- `app/Filament/Resources/DepartmentResource.php`
- `tests/Feature/DepartmentTest.php`

#### 1.2 Modelo de Municipio
- [ ] Crear modelo `Municipality`
- [ ] Migración con relación a Department
- [ ] Seeder con municipios
- [ ] Resource de Filament con filtros por departamento
- [ ] Tests CRUD y relaciones

**Campos:**
```php
- id
- department_id (FK)
- name
- code
- timestamps
```

**Archivos:**
- `app/Models/Municipality.php`
- `database/migrations/xxxx_create_municipalities_table.php`
- `database/seeders/MunicipalitySeeder.php`
- `app/Filament/Resources/MunicipalityResource.php`
- `tests/Feature/MunicipalityTest.php`

#### 1.3 Modelo de Barrio (Global)
- [ ] Crear modelo `Neighborhood`
- [ ] Migración con relación a Municipality
- [ ] Soporte para barrios globales y por campaña
- [ ] Resource de Filament
- [ ] Tests CRUD

**Campos:**
```php
- id
- municipality_id (FK)
- campaign_id (FK, nullable) // null = global
- name
- is_global (boolean)
- timestamps
```

**Archivos:**
- `app/Models/Neighborhood.php`
- `database/migrations/xxxx_create_neighborhoods_table.php`
- `app/Filament/Resources/NeighborhoodResource.php`
- `tests/Feature/NeighborhoodTest.php`

**Estado:** ⏳ Pendiente

---

## 🏛️ FASE 2: Sistema Multi-Campaña
**Objetivo:** Crear la estructura base de campañas políticas

### Tareas

#### 2.1 Modelo de Campaña
- [ ] Crear modelo `Campaign`
- [ ] Migración con todos los campos
- [ ] Enum para estados de campaña
- [ ] Resource de Filament completo
- [ ] Tests CRUD

**Campos:**
```php
- id
- name
- description
- candidate_name
- start_date
- end_date
- election_date
- status (enum: draft, active, paused, completed)
- settings (json) // configuraciones varias
- created_by (FK a users)
- timestamps
- soft_deletes
```

**Archivos:**
- `app/Models/Campaign.php`
- `app/Enums/CampaignStatus.php`
- `database/migrations/xxxx_create_campaigns_table.php`
- `app/Filament/Resources/CampaignResource.php`
- `tests/Feature/CampaignTest.php`

#### 2.2 Configuración de Campaña
- [ ] Modelo `CampaignSetting`
- [ ] Migración para settings específicos
- [ ] Form de configuración en Filament
- [ ] Tests

**Configuraciones:**
```php
- Mensaje de bienvenida
- Mensaje de cumpleaños
- Mensaje de recordatorio
- Logo de campaña
- Colores de marca
- Redes sociales
```

**Archivos:**
- `app/Models/CampaignSetting.php`
- `database/migrations/xxxx_create_campaign_settings_table.php`

#### 2.3 Relación Campaña-Usuario
- [ ] Pivot table `campaign_user`
- [ ] Relación many-to-many
- [ ] Middleware para scope de campaña
- [ ] Tests de permisos por campaña

**Campos Pivot:**
```php
- campaign_id
- user_id
- role_id
- assigned_at
- assigned_by
```

**Archivos:**
- `database/migrations/xxxx_create_campaign_user_table.php`
- `app/Http/Middleware/ScopeToCampaign.php`
- `tests/Feature/CampaignUserTest.php`

**Estado:** ⏳ Pendiente

---

## 👥 FASE 3: Gestión de Usuarios y Jerarquía
**Objetivo:** Crear estructura jerárquica de coordinadores y líderes

### Tareas

#### 3.1 Extender Modelo User
- [ ] Agregar campos adicionales a users
- [ ] Migración para nuevos campos
- [ ] Actualizar Factory
- [ ] Actualizar Resource de Filament

**Nuevos Campos:**
```php
- phone
- secondary_phone
- address
- municipality_id (FK)
- neighborhood_id (FK)
- document_number
- birth_date
- profile_photo_path
```

**Archivos:**
- `database/migrations/xxxx_add_profile_fields_to_users_table.php`
- `database/factories/UserFactory.php` (actualizar)

#### 3.2 Modelo Coordinador
- [ ] Crear modelo `Coordinator` (extiende User o relación?)
- [ ] Relación con Campaign
- [ ] Relación con Territory
- [ ] Resource de Filament
- [ ] Tests

**Archivos:**
- `app/Models/Coordinator.php`
- `app/Filament/Resources/CoordinatorResource.php`
- `tests/Feature/CoordinatorTest.php`

#### 3.3 Modelo Líder
- [ ] Crear modelo `Leader`
- [ ] Relación con Coordinator
- [ ] Relación con Campaign
- [ ] Resource de Filament
- [ ] Tests CRUD y jerarquía

**Campos:**
```php
- id
- user_id (FK)
- campaign_id (FK)
- coordinator_id (FK)
- territory (json o relaciones)
- status (active, inactive, suspended)
- timestamps
```

**Archivos:**
- `app/Models/Leader.php`
- `app/Filament/Resources/LeaderResource.php`
- `tests/Feature/LeaderTest.php`

#### 3.4 Jerarquía y Asignaciones
- [ ] Middleware de verificación jerárquica
- [ ] Scopes para consultas por jerarquía
- [ ] Dashboard específico por rol
- [ ] Tests de permisos jerárquicos

**Estado:** ⏳ Pendiente

---

## 🗳️ FASE 4: Módulo de Votantes
**Objetivo:** Crear sistema completo de registro y gestión de votantes

### Tareas

#### 4.1 Enum de Estados del Votante
- [ ] Crear enum `VoterStatus`
- [ ] Documentar cada estado
- [ ] Colores y badges para UI

**Estados:**
```php
enum VoterStatus: string
{
    case PENDING_REVIEW = 'pending_review';
    case REJECTED_CENSUS = 'rejected_census';
    case VERIFIED_CENSUS = 'verified_census';
    case CORRECTION_REQUIRED = 'correction_required';
    case VERIFIED_CALL = 'verified_call';
    case CONFIRMED = 'confirmed';
    case VOTED = 'voted';
    case DID_NOT_VOTE = 'did_not_vote';
}
```

**Archivos:**
- `app/Enums/VoterStatus.php`

#### 4.2 Modelo de Votante
- [ ] Crear modelo `Voter`
- [ ] Migración completa
- [ ] Factory para testing
- [ ] Relaciones (Campaign, Leader, Territory)
- [ ] Scopes útiles

**Campos:**
```php
- id
- campaign_id (FK)
- document_number (único por campaña)
- first_name
- last_name
- birth_date
- phone
- secondary_phone
- email (nullable)
- municipality_id (FK)
- neighborhood_id (FK)
- address
- detailed_address
- registered_by (FK a users) // líder o coordinador
- status (enum)
- census_validated_at
- call_verified_at
- confirmed_at
- voted_at
- notes (text)
- timestamps
- soft_deletes
```

**Archivos:**
- `app/Models/Voter.php`
- `database/migrations/xxxx_create_voters_table.php`
- `database/factories/VoterFactory.php`
- `tests/Feature/VoterTest.php`

#### 4.3 Resource de Filament para Votantes
- [ ] Crear VoterResource completo
- [ ] Form con validaciones
- [ ] Table con filtros avanzados
- [ ] Acciones masivas
- [ ] Importación CSV
- [ ] Exportación
- [ ] Tests de UI

**Filtros:**
- Por estado
- Por territorio
- Por líder/coordinador
- Por fecha de registro
- Por validación de censo

**Archivos:**
- `app/Filament/Resources/VoterResource.php`
- `app/Filament/Resources/VoterResource/Pages/`
- `tests/Feature/Filament/VoterResourceTest.php`

#### 4.4 Livewire Component para Registro Rápido
- [ ] Crear Volt component para registro
- [ ] Validación en tiempo real
- [ ] Auto-guardado
- [ ] Tests

**Archivos:**
- `resources/views/livewire/voters/quick-register.blade.php`
- `tests/Feature/Volt/VoterQuickRegisterTest.php`

**Estado:** ⏳ Pendiente

---

## ✅ FASE 5: Validación y Censo Electoral
**Objetivo:** Sistema de validación contra censo oficial

### Tareas

#### 5.1 Modelo de Censo Electoral
- [ ] Crear modelo `CensusRecord`
- [ ] Migración optimizada (índices)
- [ ] Importador CSV/Excel
- [ ] Tests

**Campos:**
```php
- id
- campaign_id (FK)
- document_number (indexed)
- full_name
- municipality_code
- polling_station
- table_number
- imported_at
- timestamps
```

**Archivos:**
- `app/Models/CensusRecord.php`
- `database/migrations/xxxx_create_census_records_table.php`
- `app/Services/CensusImporter.php`
- `tests/Feature/CensusImporterTest.php`

#### 5.2 Servicio de Validación
- [ ] Crear `VoterValidationService`
- [ ] Lógica de matching con censo
- [ ] Job asíncrono para validación masiva
- [ ] Tests unitarios

**Archivos:**
- `app/Services/VoterValidationService.php`
- `app/Jobs/ValidateVoterAgainstCensus.php`
- `tests/Unit/VoterValidationServiceTest.php`

#### 5.3 Modelo de Historial de Validación
- [ ] Crear `ValidationHistory`
- [ ] Tracking de cambios de estado
- [ ] Auditoría completa
- [ ] Tests

**Campos:**
```php
- id
- voter_id (FK)
- previous_status
- new_status
- validated_by (FK a users)
- validation_type (census, call, manual)
- notes
- timestamps
```

**Archivos:**
- `app/Models/ValidationHistory.php`
- `database/migrations/xxxx_create_validation_histories_table.php`

#### 5.4 Interface de Revisión
- [ ] Panel de Filament para revisores
- [ ] Queue de votantes pendientes
- [ ] Acciones rápidas (aprobar/rechazar)
- [ ] Tests

**Archivos:**
- `app/Filament/Resources/ReviewQueueResource.php`
- `tests/Feature/Filament/ReviewQueueTest.php`

**Estado:** ⏳ Pendiente

---

## 📞 FASE 6: Módulos Estratégicos
**Objetivo:** Encuestas, cumpleaños, mensajería

### Tareas

#### 6.1 Sistema de Encuestas

##### 6.1.1 Modelo de Encuesta
- [ ] Crear `Survey`
- [ ] Migración
- [ ] Versionamiento de encuestas
- [ ] Resource de Filament
- [ ] Tests

**Campos:**
```php
- id
- campaign_id (FK)
- name
- description
- version (int, default 1) // Para versionamiento
- parent_survey_id (FK a surveys, nullable) // Referencia a versión anterior
- is_active
- start_date
- end_date
- created_by (FK a users)
- timestamps
- soft_deletes
```

**Versionamiento:**
- Al duplicar/editar una encuesta activa, se crea nueva versión
- Se mantiene historial de versiones anteriores
- Las respuestas quedan ligadas a la versión específica

##### 6.1.2 Modelo de Pregunta
- [ ] Crear `SurveyQuestion`
- [ ] Enum `QuestionType` con tipos: yes_no, scale, text, multiple_choice, single_choice
- [ ] Configuración de escalas (1-5, 1-10, etc.)
- [ ] Validación de opciones según tipo
- [ ] Orden de preguntas
- [ ] Tests

**Campos:**
```php
- id
- survey_id (FK)
- question_text
- question_type (enum: yes_no, scale, text, multiple_choice, single_choice)
- options (json) // Para multiple_choice y single_choice
- scale_min (int, nullable) // Para tipo scale
- scale_max (int, nullable) // Para tipo scale
- scale_labels (json, nullable) // Labels opcionales para escala
- is_required
- order
- timestamps
```

**Tipos de Pregunta:**
- `yes_no`: Pregunta simple Sí/No
- `scale`: Escala numérica (ej: 1-5, 1-10)
- `text`: Respuesta de texto libre
- `multiple_choice`: Selección múltiple (varias respuestas)
- `single_choice`: Selección única (una sola respuesta)

##### 6.1.3 Modelo de Respuesta
- [ ] Crear `SurveyResponse`
- [ ] Relación con Voter
- [ ] Tracking de respuestas
- [ ] Tests

**Campos:**
```php
- id
- survey_id (FK)
- voter_id (FK)
- question_id (FK)
- response (json)
- answered_by (FK a users)
- answered_at
- timestamps
```

##### 6.1.4 Métricas y Resultados
- [ ] Modelo `SurveyMetrics` para agregación de resultados
- [ ] Cálculo automático de métricas
- [ ] Gráficas por tipo de pregunta
- [ ] Comparación entre versiones
- [ ] Tests

**Métricas a Calcular:**
```php
- Total de respuestas
- Tasa de respuesta por pregunta
- Distribución de respuestas (para choice y yes/no)
- Promedio (para scale)
- Análisis de texto (para text) - opcional
- Tiempo promedio de respuesta
- Respuestas por día/semana
```

##### 6.1.5 Interface de Encuestas
- [ ] Volt component para aplicar encuestas
- [ ] Dashboard de resultados con métricas
- [ ] Gráficas con Filament Widgets
- [ ] Exportación de resultados
- [ ] Tests

**Archivos:**
- `app/Models/Survey.php`
- `app/Models/SurveyQuestion.php`
- `app/Models/SurveyResponse.php`
- `app/Models/SurveyMetrics.php`
- `app/Enums/QuestionType.php`
- `app/Services/SurveyMetricsCalculator.php`
- `database/migrations/xxxx_create_surveys_tables.php`
- `app/Filament/Resources/SurveyResource.php`
- `resources/views/livewire/surveys/apply.blade.php`
- `app/Filament/Widgets/SurveyResultsWidget.php`
- `tests/Feature/SurveyTest.php`

#### 6.2 Módulo de Cumpleaños

##### 6.2.1 Comando Diario
- [ ] Crear `SendBirthdayMessages`
- [ ] Schedule en Kernel
- [ ] Tests

**Archivos:**
- `app/Console/Commands/SendBirthdayMessages.php`
- `tests/Feature/Commands/SendBirthdayMessagesTest.php`

##### 6.2.2 Sistema de Mensajería
- [ ] Modelo `Message`
- [ ] Integración WhatsApp (API a definir)
- [ ] Integración SMS (API a definir)
- [ ] Queue para envíos masivos
- [ ] Tests

**Campos Message:**
```php
- id
- campaign_id (FK)
- voter_id (FK)
- template_id (FK a message_templates, nullable)
- type (birthday, reminder, custom, campaign)
- channel (whatsapp, sms, email)
- subject (nullable)
- content
- status (pending, scheduled, sent, failed, delivered, read, clicked)
- scheduled_for (timestamp, nullable)
- sent_at
- delivered_at (nullable)
- read_at (nullable) // Para canales que lo soporten
- clicked_at (nullable) // Para emails con links
- error_message
- external_id (nullable) // ID del proveedor externo
- metadata (json) // Click tracking, opens, etc.
- timestamps
```

**Métricas de Mensajería:**
- Tasa de entrega
- Tasa de lectura (cuando disponible)
- Tasa de click (para emails)
- Tiempo promedio de entrega
- Errores por tipo

**Archivos:**
- `app/Models/Message.php`
- `app/Services/WhatsAppService.php`
- `app/Services/SmsService.php`
- `app/Jobs/SendMessage.php`
- `database/migrations/xxxx_create_messages_table.php`
- `tests/Feature/MessageTest.php`

##### 6.2.3 Plantillas de Mensajes
- [ ] Modelo `MessageTemplate`
- [ ] Variables dinámicas ({{nombre}}, {{fecha}}, etc.)
- [ ] Control anti-spam (límite de mensajes por día)
- [ ] Horarios permitidos de envío
- [ ] Resource de Filament
- [ ] Tests

**Campos MessageTemplate:**
```php
- id
- campaign_id (FK)
- name
- type (birthday, reminder, custom, campaign)
- channel (whatsapp, sms, email)
- subject (nullable, para email)
- content // Con variables: {{nombre}}, {{edad}}, {{candidato}}, etc.
- is_active
- created_by (FK a users)
- timestamps
```

**Control Anti-Spam:**
```php
- Max mensajes por votante por día
- Max mensajes por campaña por hora
- Blacklist de números
- Opt-out tracking
```

**Horarios Permitidos:**
```php
- Hora inicio permitida (ej: 08:00)
- Hora fin permitida (ej: 20:00)
- Días permitidos (lun-dom)
- Excepciones por tipo de mensaje
```

**Archivos:**
- `app/Models/MessageTemplate.php`
- `app/Services/MessageRateLimiter.php`
- `app/Services/MessageScheduler.php`
- `database/migrations/xxxx_create_message_templates_table.php`
- `app/Filament/Resources/MessageTemplateResource.php`

#### 6.3 Call Center Workflow (Llamadas de Verificación)

##### 6.3.1 Asignación de Votantes
- [ ] Modelo `CallAssignment` para asignar votantes a revisores
- [ ] Balanceo de carga (distribución equitativa)
- [ ] Re-asignación automática
- [ ] Tests

**Campos CallAssignment:**
```php
- id
- voter_id (FK)
- assigned_to (FK a users) // El reviewer/caller
- assigned_by (FK a users)
- campaign_id (FK)
- status (pending, in_progress, completed, reassigned)
- priority (low, medium, high, urgent)
- assigned_at
- completed_at (nullable)
- timestamps
```

##### 6.3.2 Modelo de Llamada
- [ ] Crear `VerificationCall`
- [ ] Enum `CallResult` con todas las categorías
- [ ] Tracking de intentos múltiples
- [ ] Integración con encuestas
- [ ] Tests

**Campos:**
```php
- id
- voter_id (FK)
- assignment_id (FK a call_assignments)
- caller_id (FK a users)
- attempt_number (int, default 1)
- call_date
- call_duration (seconds)
- call_result (enum: answered, no_answer, busy, wrong_number, rejected, callback_requested, not_interested, confirmed)
- notes
- survey_id (FK, nullable) // Si se aplicó encuesta
- survey_completed (boolean)
- next_attempt_at (timestamp, nullable) // Para re-intentos programados
- timestamps
```

**Enum CallResult:**
```php
enum CallResult: string
{
    case ANSWERED = 'answered';
    case NO_ANSWER = 'no_answer';
    case BUSY = 'busy';
    case WRONG_NUMBER = 'wrong_number';
    case REJECTED = 'rejected';
    case CALLBACK_REQUESTED = 'callback_requested';
    case NOT_INTERESTED = 'not_interested';
    case CONFIRMED = 'confirmed';
    case INVALID_NUMBER = 'invalid_number';
}
```

**Archivos:**
- `app/Models/CallAssignment.php`
- `app/Models/VerificationCall.php`
- `app/Enums/CallResult.php`
- `app/Services/CallAssignmentService.php`
- `database/migrations/xxxx_create_call_assignments_table.php`
- `database/migrations/xxxx_create_verification_calls_table.php`
- `tests/Feature/CallAssignmentTest.php`
- `tests/Feature/VerificationCallTest.php`

##### 6.3.3 Queue de Llamadas
- [ ] Vista de cola priorizada para callers
- [ ] Asignación automática de siguiente llamada
- [ ] Filtros por territorio/estado
- [ ] Marcador automático de intentos
- [ ] Tests

##### 6.3.4 Interface de Llamadas
- [ ] Volt component para registrar llamadas
- [ ] Quick-dial siguiente votante
- [ ] Formulario de resultado + encuesta inline
- [ ] Historial de llamadas por votante
- [ ] Tests

##### 6.3.5 Estadísticas y Métricas
- [ ] Dashboard por caller (llamadas/hora, tasa de contacto)
- [ ] Métricas de equipo
- [ ] Mejores horarios de contacto
- [ ] Tests

**Métricas:**
```php
- Llamadas realizadas por caller
- Tasa de contacto (%)
- Tiempo promedio por llamada
- Encuestas completadas
- Confirmaciones logradas
- Re-intentos necesarios
- Mejores horarios (análisis temporal)
```

**Archivos:**
- `resources/views/livewire/calls/register.blade.php`
- `resources/views/livewire/calls/queue.blade.php`
- `app/Filament/Resources/VerificationCallResource.php`
- `app/Filament/Widgets/CallCenterStatsWidget.php`
- `app/Services/CallMetricsCalculator.php`

**Estado:** ✅ COMPLETADO (100%)

---

## 🌐 FASE 7: Sistema de Traducción (NUEVO - URGENTE)
**Objetivo:** Implementar sistema completo de traducción al español

### Contexto
El sistema actualmente está completamente en inglés a pesar de estar configurado con `locale='es'`. Necesitamos:
- Traducir todos los recursos de Filament
- Traducir componentes Volt
- Configurar Laravel para español
- Crear archivos de idioma

### Tareas

#### 7.1 Configuración de Idioma
- [ ] Verificar `config/app.php` locale y fallback_locale
- [ ] Instalar paquetes de traducción si es necesario
- [ ] Configurar Filament para español
- [ ] Tests de configuración

**Archivos:**
- `config/app.php`
- `app/Providers/FilamentServiceProvider.php` (si existe)

#### 7.2 Archivos de Traducción
- [ ] Crear `lang/es/filament.php`
- [ ] Crear `lang/es/models.php`
- [ ] Crear `lang/es/enums.php`
- [ ] Crear `lang/es/validation.php`
- [ ] Tests

**Archivos:**
- `lang/es/filament.php`
- `lang/es/models.php`
- `lang/es/enums.php`
- `lang/es/validation.php`

#### 7.3 Traducción de Resources
- [ ] CampaignResource
- [ ] DepartmentResource
- [ ] MunicipalityResource
- [ ] NeighborhoodResource
- [ ] VerificationCallResource
- [ ] Todas las etiquetas y mensajes

**Archivos:**
- Todos los Resources en `app/Filament/Resources/`

#### 7.4 Traducción de Componentes Volt
- [ ] register.blade.php
- [ ] queue.blade.php
- [ ] Otros componentes Volt

**Archivos:**
- `resources/views/livewire/calls/register.blade.php`
- `resources/views/livewire/calls/queue.blade.php`

**Estimación:** 1-2 días
**Prioridad:** ALTA (afecta UX inmediatamente)
**Estado:** ⏳ Pendiente

---

## � FASE 8: Gestión de Jerarquía y Permisos (NUEVO - CRÍTICO)
**Objetivo:** Implementar UI completa para gestión de usuarios, roles y jerarquía territorial

### Contexto
El sistema tiene 5 roles definidos (SUPER_ADMIN, ADMIN_CAMPAIGN, COORDINATOR, LEADER, REVIEWER) pero:
- NO existe UserResource para gestionar usuarios
- NO existe VoterResource para que líderes registren votantes
- NO existe interfaz para asignaciones territoriales
- NO hay dashboards por rol
- El workflow jerarquico no está implementado en UI

### Tareas

#### 8.1 UserResource en Filament
- [ ] Crear Resource completo para User
- [ ] CRUD de usuarios
- [ ] Asignación de roles
- [ ] Asignación de campañas
- [ ] Asignación territorial
- [ ] Filtros por rol, campaña, territorio
- [ ] Búsqueda avanzada
- [ ] Tests (25+ tests)

**Archivos:**
- `app/Filament/Resources/UserResource.php`
- `app/Filament/Resources/UserResource/Pages/`
- `tests/Feature/Filament/UserResourceTest.php`

#### 8.2 VoterResource en Filament
- [ ] Crear Resource completo para Voter
- [ ] CRUD de votantes
- [ ] Importación masiva
- [ ] Gestión de estados (VoterStatus)
- [ ] Asignación de líderes
- [ ] Validación contra censo
- [ ] Historial de validaciones
- [ ] Filtros avanzados
- [ ] Tests (30+ tests)

**Archivos:**
- `app/Filament/Resources/VoterResource.php`
- `app/Filament/Resources/VoterResource/Pages/`
- `app/Filament/Resources/VoterResource/Actions/`
- `tests/Feature/Filament/VoterResourceTest.php`

#### 8.3 SurveyResource en Filament
- [ ] Crear Resource completo para Survey
- [ ] CRUD de encuestas
- [ ] Constructor de preguntas
- [ ] Asignación de encuestas
- [ ] Visualización de resultados
- [ ] Exportación de datos
- [ ] Tests (20+ tests)

**Archivos:**
- `app/Filament/Resources/SurveyResource.php`
- `app/Filament/Resources/SurveyResource/Pages/`
- `tests/Feature/Filament/SurveyResourceTest.php`

#### 8.4 TerritorialAssignmentResource
- [ ] Crear Resource para asignaciones territoriales
- [ ] Asignar coordinadores a departamentos
- [ ] Asignar líderes a municipios/barrios
- [ ] Validar jerarquía
- [ ] Tests (15+ tests)

**Archivos:**
- `app/Filament/Resources/TerritorialAssignmentResource.php`
- `tests/Feature/Filament/TerritorialAssignmentResourceTest.php`

#### 8.5 Dashboards por Rol
- [ ] Dashboard para SUPER_ADMIN (overview completo)
- [ ] Dashboard para ADMIN_CAMPAIGN (su campaña)
- [ ] Dashboard para COORDINATOR (su territorio)
- [ ] Dashboard para LEADER (sus votantes)
- [ ] Dashboard para REVIEWER (call center)
- [ ] Tests

**Archivos:**
- `app/Filament/Pages/Dashboards/SuperAdminDashboard.php`
- `app/Filament/Pages/Dashboards/CampaignAdminDashboard.php`
- `app/Filament/Pages/Dashboards/CoordinatorDashboard.php`
- `app/Filament/Pages/Dashboards/LeaderDashboard.php`
- `app/Filament/Pages/Dashboards/ReviewerDashboard.php`

#### 8.6 Settings Page
- [ ] Configuración general del sistema
- [ ] Configuración por campaña
- [ ] Tests

**Archivos:**
- `app/Filament/Pages/Settings.php`

**Estimación:** 5-7 días
**Prioridad:** CRÍTICA (workflow principal del sistema)
**Estado:** ⏳ Pendiente

---

## �📊 FASE 9: Reportes y Analítica
**Objetivo:** Dashboards y reportes estratégicos

### Tareas

#### 7.1 Widgets de Filament

##### 7.1.1 Widget de Overview General
- [ ] Total votantes por estado
- [ ] Tasa de validación
- [ ] Proyección electoral
- [ ] Tests

##### 7.1.2 Widget por Territorio
- [ ] Mapa de calor
- [ ] Gráfica por municipio
- [ ] Gráfica por barrio
- [ ] Tests

##### 7.1.3 Widget por Líder
- [ ] Ranking de líderes
- [ ] Eficiencia de captación
- [ ] Tasa de confirmación
- [ ] Tests

##### 7.1.4 Widget de Encuestas
- [ ] Resultados visuales
- [ ] Comparativas temporales
- [ ] Tests

**Archivos:**
- `app/Filament/Widgets/CampaignOverviewWidget.php`
- `app/Filament/Widgets/TerritoryMapWidget.php`
- `app/Filament/Widgets/LeaderRankingWidget.php`
- `app/Filament/Widgets/SurveyResultsWidget.php`
- `tests/Feature/Widgets/` (todos)

#### 7.2 Reportes Exportables

##### 7.2.1 Reporte de Votantes
- [ ] Excel con filtros aplicados
- [ ] PDF con resumen
- [ ] Tests

##### 7.2.2 Reporte de Líderes
- [ ] Performance por líder
- [ ] Excel/PDF
- [ ] Tests

##### 7.2.3 Reporte de Territorio
- [ ] Distribución geográfica
- [ ] Proyecciones
- [ ] Tests

**Archivos:**
- `app/Services/ReportGenerator.php`
- `app/Exports/VotersExport.php`
- `app/Exports/LeadersExport.php`
- `tests/Feature/ReportGeneratorTest.php`

#### 7.3 API para Integraciones
- [ ] Endpoints REST para datos
- [ ] Autenticación con Sanctum
- [ ] Versionado
- [ ] Documentación
- [ ] Tests

**Archivos:**
- `routes/api.php`
- `app/Http/Controllers/Api/V1/` (controllers)
- `app/Http/Resources/` (API Resources)
- `tests/Feature/Api/` (tests)

**Estado:** ⏳ Pendiente

---

## 🧪 Testing y Calidad

### Objetivos de Cobertura
- [ ] 80%+ cobertura de código
- [ ] Tests para todos los modelos
- [ ] Tests para todos los Resources de Filament
- [ ] Tests para todos los Volt components
- [ ] Tests de integración
- [ ] Tests de Browser (Pest v4)

### Tests Críticos
- [ ] Flujo completo de registro de votante
- [ ] Validación contra censo
- [ ] Sistema de permisos
- [ ] Jerarquía de usuarios
- [ ] Envío de mensajes
- [ ] Generación de reportes

**Comando de Testing:**
```bash
php artisan test --coverage
```

---

## 📦 Dependencias Adicionales

### A Instalar Durante Desarrollo

```bash
# Roles y Permisos
composer require spatie/laravel-permission

# Importación/Exportación
composer require maatwebsite/excel

# Generación de PDFs
composer require barryvdh/laravel-dompdf

# API
composer require laravel/sanctum

# Gráficas
composer require filament/spatie-laravel-charts-plugin

# Auditoría
composer require owen-it/laravel-auditing
```

---

## 📝 Documentación a Crear

Durante el desarrollo, crear:

- [ ] `docs/API.md` - Documentación de API
- [ ] `docs/ROLES.md` - Descripción de roles y permisos
- [ ] `docs/WORKFLOW.md` - Flujo de trabajo del sistema
- [ ] `docs/DEPLOYMENT.md` - Guía de despliegue
- [ ] `docs/TESTING.md` - Guía de testing
- [ ] README.md actualizado

---

## 🔄 Proceso de Desarrollo

### Para Cada Tarea:

1. **Crear rama** de feature
2. **Implementar** código
3. **Escribir tests**
4. **Ejecutar** tests
5. **Ejecutar** Pint para formateo
6. **Commit** con mensaje descriptivo
7. **Marcar** tarea como completada en este plan
8. **Merge** a main/develop

### Comandos Útiles:

```bash
# Crear modelo con todo
php artisan make:model Vote -mfsr

# Crear Filament Resource
php artisan make:filament-resource Voter --generate

# Crear test
php artisan make:test VoterTest --pest

# Ejecutar tests
php artisan test --filter=VoterTest

# Formatear código
vendor/bin/pint --dirty
```

---

## 📈 Tracking de Progreso

### Resumen por Fase

- [x] **FASE 0:** Configuración Base (4/4 tareas) ✅
- [x] **FASE 1:** Estructura Territorial (3/3 módulos) ✅
- [x] **FASE 2:** Sistema Multi-Campaña (3/3 módulos) ✅
- [x] **FASE 3:** Gestión de Usuarios (4/4 módulos) ✅
- [x] **FASE 4:** Módulo de Votantes (4/4 módulos) ✅
- [x] **FASE 5:** Validación y Censo (4/4 módulos) ✅
- [x] **FASE 6:** Módulos Estratégicos (10/10 sub-módulos) ✅
- [ ] **FASE 7:** Sistema de Traducción (0/4 módulos) ⏳ URGENTE
- [ ] **FASE 8:** Gestión de Jerarquía y Permisos (0/6 módulos) ⏳ CRÍTICO
- [ ] **FASE 9:** Reportes y Analítica (0/3 módulos) ⏳

### Progreso General
**70% Completo** (24/34 módulos principales)

**Estado Actual:**
- ✅ Infraestructura base completada
- ✅ Modelos core implementados
- ✅ Sistema de encuestas funcionando
- ✅ Call center operativo
- ⚠️ **CRÍTICO:** Sistema completamente en inglés (necesita traducción)
- ⚠️ **CRÍTICO:** Falta UI para gestión de usuarios y roles
- ⚠️ **CRÍTICO:** Falta UI para gestión de votantes
- ⚠️ **BLOQUEANTE:** No hay interfaz para workflow de jerarquía

---

## 🎯 Próximos Pasos Inmediatos

### PRIORIDAD ALTA (Completar Primero):

1. **FASE 7:** Sistema de Traducción (1-2 días)
   - Configurar Laravel para español
   - Traducir todos los Resources de Filament
   - Traducir componentes Volt
   - Crear archivos de idioma
   - **Impacto:** Mejora UX inmediatamente

2. **FASE 8.1:** UserResource (2-3 días)
   - CRUD completo de usuarios
   - Asignación de roles
   - Asignación de campañas
   - Asignación territorial
   - **Impacto:** Habilita gestión de jerarquía

3. **FASE 8.2:** VoterResource (2-3 días)
   - CRUD completo de votantes
   - Importación masiva
   - Gestión de estados
   - Asignación de líderes
   - **Impacto:** Habilita workflow principal

### PRIORIDAD MEDIA:

4. **FASE 8.3:** SurveyResource (1-2 días)
   - CRUD de encuestas
   - Constructor de preguntas
   - Visualización de resultados

5. **FASE 8.4:** TerritorialAssignmentResource (1 día)
   - Asignaciones territoriales

6. **FASE 8.5:** Dashboards por Rol (2-3 días)
   - Dashboard específico para cada rol

### PRIORIDAD BAJA:

7. **FASE 9:** Reportes y Analítica
   - Widgets avanzados
   - Exportaciones
   - API

### Orden Recomendado:
```
FASE 7 (Traducción) → FASE 8.1 (Users) → FASE 8.2 (Voters) → FASE 8.3 (Surveys) → FASE 8.4-8.6 → FASE 9
```

**Estimación Total Restante:** 12-15 días de desarrollo

---

## ⚠️ Hallazgos Críticos del Sistema

### Roles Definidos (UserRole enum):
1. **SUPER_ADMIN** - Acceso total al sistema
2. **ADMIN_CAMPAIGN** - Administrador de campaña
3. **COORDINATOR** - Coordinador territorial (gestiona líderes)
4. **LEADER** - Líder territorial (registra votantes)
5. **REVIEWER** - Revisor (valida y hace llamadas)

### Problemas Identificados:
- ✅ Modelos creados y funcionando
- ✅ Relaciones entre modelos correctas
- ✅ Tests pasando (410 tests, 945 assertions)
- ❌ **NO existe UserResource** (no se pueden gestionar usuarios/roles)
- ❌ **NO existe VoterResource** (líderes no pueden registrar votantes)
- ❌ **NO existe SurveyResource** (no se pueden crear/gestionar encuestas)
- ❌ **NO existe TerritorialAssignmentResource** (no se pueden hacer asignaciones)
- ❌ **Sistema completamente en inglés** (configurado 'es' pero sin traducciones)
- ❌ **NO hay dashboards por rol** (todos ven lo mismo)
- ❌ **Workflow jerárquico no implementado en UI**

### Workflow Esperado vs Actual:

**Esperado:**
```
Admin → Crea campaña → Asigna coordinador
Coordinador → Asigna territorio → Gestiona líderes
Líder → Registra votantes → Valida datos
Revisor → Valida votantes → Hace llamadas
```

**Actual:**
```
❌ No hay UI para estas operaciones
✅ Solo modelos y relaciones en base de datos
```

### Decisión de Arquitectura:
El sistema debe priorizar **completar la UI de gestión básica** antes de reportes avanzados, porque sin UserResource y VoterResource, el workflow principal no funciona.

---

## 📞 Notas y Consideraciones

### Decisiones Tomadas:
- ✅ Coordinadores y Líderes son Users con roles (UserRole enum)
- ✅ Sistema usa Spatie Permission para roles
- ✅ Multi-campaña implementado (soft multi-tenancy)
- ✅ SQLite para testing, MySQL para producción
- ✅ Filament v4 como panel admin principal
- ✅ Volt para componentes interactivos
- ✅ Pest v4 para testing (incluye browser tests)

### Decisiones Pendientes:
- [ ] ¿Qué API usar para WhatsApp? (Twilio, official API, etc)
- [ ] ¿Qué API usar para SMS? (ver `docs/INTEGRACION_HABLAME_SMS.md`)
- [ ] ¿Implementar notificaciones push?
- [ ] ¿Usar Redis para cache y queues en producción?

### Optimizaciones Futuras:
- Cache de queries frecuentes (Redis)
- Queue workers para jobs pesados
- CDN para assets estáticos
- Backup automático de base de datos
- Monitoreo con Laravel Pulse

---

## 🎓 Recursos de Documentación

### Documentación Creada:
- ✅ `docs/DECISIONES.md` - Decisiones de arquitectura
- ✅ `docs/PATRON_ENUMS.md` - Patrón para enums
- ✅ `docs/CHEATSHEET.md` - Comandos útiles
- ✅ `docs/INTEGRACION_HABLAME_SMS.md` - Integración SMS
- ✅ `docs/SURVEY_EXPORT_INTEGRATION.md` - Exportación de encuestas
- ✅ `docs/GUIA_USO_PLAN.md` - Guía de uso del plan

### Documentación Pendiente:
- [ ] `docs/API.md` - Documentación de API (cuando se implemente)
- [ ] `docs/DEPLOYMENT.md` - Guía de despliegue
- [ ] `docs/ROLES.md` - Descripción detallada de roles y permisos
- [ ] `docs/TESTING.md` - Guía completa de testing
- [ ] README.md mejorado con screenshots

---

**Última Actualización:** 2025-01-21
**Actualizar este plan** conforme avancemos en el desarrollo.

**Estado:** 70% completo - Fases críticas identificadas y priorizadas
