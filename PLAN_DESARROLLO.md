# 📋 Plan de Desarrollo SIGMA
## Sistema Integral de Gestión y Análisis Electoral

**Versión del Plan:** 1.0
**Fecha de Creación:** 2025-11-02
**Estado del Proyecto:** Fundación Completa - Desarrollo de Dominio Pendiente

---

## 🎯 Resumen Ejecutivo

### Estado Actual
✅ **Implementado (Fundación):**
- Sistema de autenticación completo (Login, Registro, 2FA, Reset Password)
- Panel de administración Filament (esqueleto)
- UI moderna con Volt + Flux + Tailwind
- Base de datos SQLite configurada
- 13 tests funcionando (11 feature + 2 unit)

❌ **Pendiente (Dominio de Negocio):**
- Todos los modelos de negocio electoral
- Sistema multi-campaña
- Gestión territorial
- Validación contra censo
- Sistema de encuestas
- Módulo de cumpleaños
- Reportes y análisis

---

## 📊 Estructura del Plan

Este plan está dividido en **7 Fases** principales:

1. **Fase 0:** Configuración Base y Roles
2. **Fase 1:** Estructura Territorial
3. **Fase 2:** Sistema Multi-Campaña
4. **Fase 3:** Gestión de Usuarios y Jerarquía
5. **Fase 4:** Módulo de Votantes
6. **Fase 5:** Validación y Censo Electoral
7. **Fase 6:** Módulos Estratégicos
8. **Fase 7:** Reportes y Analítica

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

**Estado:** ⏳ Pendiente

---

## 📊 FASE 7: Reportes y Analítica
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

- [ ] **FASE 0:** Configuración Base (0/4 tareas)
- [ ] **FASE 1:** Estructura Territorial (0/3 módulos)
- [ ] **FASE 2:** Sistema Multi-Campaña (0/3 módulos)
- [ ] **FASE 3:** Gestión de Usuarios (0/4 módulos)
- [ ] **FASE 4:** Módulo de Votantes (0/4 módulos)
- [ ] **FASE 5:** Validación y Censo (0/4 módulos)
- [ ] **FASE 6:** Módulos Estratégicos (0/3 módulos)
- [ ] **FASE 7:** Reportes y Analítica (0/3 módulos)

### Progreso General
**0% Completo** (0/28 módulos principales)

---

## 🎯 Próximos Pasos Inmediatos

### Comenzar con:

1. **FASE 0.1:** Instalar y configurar `spatie/laravel-permission`
2. **FASE 1.1:** Crear modelo Department
3. **FASE 1.2:** Crear modelo Municipality
4. **FASE 1.3:** Crear modelo Neighborhood

### Orden Recomendado:
```
FASE 0 → FASE 1 → FASE 2 → FASE 3 → FASE 4 → FASE 5 → FASE 6 → FASE 7
```

Cada fase depende de la anterior.

---

## 📞 Notas y Consideraciones

### Decisiones Pendientes:
- [ ] ¿Coordinadores son Users con rol o tabla separada?
- [ ] ¿Líderes son Users con rol o tabla separada?
- [ ] ¿Qué API usar para WhatsApp? (Twilio, official API, etc)
- [ ] ¿Qué API usar para SMS?
- [ ] ¿Usar PostgreSQL en producción o SQLite?
- [ ] ¿Multi-tenancy real o soft multi-tenancy?

### Optimizaciones Futuras:
- Cache de queries frecuentes
- Queue workers para jobs pesados
- CDN para assets
- Backup automático de base de datos

---

**Última Actualización:** 2025-11-02
**Actualizar este plan** conforme avancemos en el desarrollo.
