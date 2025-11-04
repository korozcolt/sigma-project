# 📋 Análisis del Sistema SIGMA

**Fecha:** 2025-11-04  
**Versión:** 1.0  
**Autor:** Sistema de Análisis

---

## 1️⃣ GESTIÓN DE ACTORES Y SUS VISTAS

### 🎭 Actores del Sistema Identificados

#### **Actor 1: Usuario (User)**
- **Modelo:** `app/Models/User.php`
- **Roles disponibles:** SuperAdmin, Admin, TeamLead, Analyst, Operator
- **Gestión en Filament:** ❌ **NO EXISTE UserResource**
- **Vistas disponibles:**
  - ✅ `/settings/profile` - Editar perfil personal (Volt)
  - ✅ `/settings/password` - Cambiar contraseña (Volt)
  - ✅ `/settings/appearance` - Tema claro/oscuro (Volt)
  - ✅ `/settings/two-factor` - Autenticación 2FA (Volt)
- **Campos del perfil:**
  - name, email, password
  - phone, secondary_phone
  - document_number
  - birth_date, address
  - municipality_id, neighborhood_id
  - profile_photo_path
- **FALTANTE:** No hay Resource en Filament para que un Admin gestione usuarios del sistema

---

#### **Actor 2: Votante (Voter)**
- **Modelo:** `app/Models/Voter.php`
- **Estados:** 8 estados (Lead, Contacted, Interested, NotInterested, Confirmed, Rejected, Unreachable, Duplicate)
- **Gestión en Filament:** ❌ **NO EXISTE VoterResource**
- **Vistas disponibles:** Ninguna
- **Campos importantes:**
  - full_name, document_number
  - phone, email
  - birth_date, gender, address
  - municipality_id, neighborhood_id
  - campaign_id
  - status (VoterStatus enum)
  - is_validated, validated_at
- **FALTANTE:** No hay Resource en Filament para gestionar votantes

---

#### **Actor 3: Agente de Call Center (User con rol Operator)**
- **Modelo:** `app/Models/User.php` (con rol OPERATOR)
- **Gestión en Filament:** ❌ NO EXISTE
- **Vistas disponibles:**
  - ✅ `/calls/queue` - Cola de llamadas asignadas (Volt)
  - ✅ `/calls/register` - Registrar resultado de llamada (Volt)
- **Relaciones:**
  - CallAssignment (asignaciones de votantes)
  - VerificationCall (llamadas realizadas)
- **FALTANTE:** No hay forma de asignar roles o ver agentes disponibles en Filament

---

#### **Actor 4: Coordinador de Territorio (User con rol TeamLead)**
- **Modelo:** `app/Models/User.php` (con rol TEAM_LEAD)
- **Gestión en Filament:** ❌ NO EXISTE
- **Vistas disponibles:** Ninguna específica
- **Relaciones:**
  - TerritorialAssignment (territorios asignados)
  - Campaign (campañas donde participa)
- **FALTANTE:** No hay dashboard o vistas para coordinadores

---

#### **Actor 5: Analista (User con rol Analyst)**
- **Modelo:** `app/Models/User.php` (con rol ANALYST)
- **Gestión en Filament:** ❌ NO EXISTE
- **Vistas disponibles:** Solo Filament dashboard genérico
- **FALTANTE:** No hay herramientas específicas de análisis

---

### 📊 Resumen de Recursos Filament Existentes

| Recurso | Ubicación | Propósito | Estado |
|---------|-----------|-----------|--------|
| **CampaignResource** | `app/Filament/Resources/Campaigns/` | Gestión de campañas | ✅ Completo |
| **DepartmentResource** | `app/Filament/Resources/Departments/` | Gestión de departamentos | ✅ Completo |
| **MunicipalityResource** | `app/Filament/Resources/Municipalities/` | Gestión de municipios | ✅ Completo |
| **NeighborhoodResource** | `app/Filament/Resources/Neighborhoods/` | Gestión de barrios | ✅ Completo |
| **VerificationCallResource** | `app/Filament/Resources/VerificationCalls/` | Gestión de llamadas | ✅ Completo |
| **UserResource** | - | Gestión de usuarios | ❌ **NO EXISTE** |
| **VoterResource** | - | Gestión de votantes | ❌ **NO EXISTE** |
| **SurveyResource** | - | Gestión de encuestas | ❌ **NO EXISTE** |
| **CensusRecordResource** | - | Gestión de censo | ❌ **NO EXISTE** |
| **CallAssignmentResource** | - | Gestión de asignaciones | ❌ **NO EXISTE** |
| **TerritorialAssignmentResource** | - | Gestión territorial | ❌ **NO EXISTE** |

---

## 2️⃣ CONFIGURACIÓN Y EDICIÓN DE MÓDULOS

### 🔧 Módulos Implementados y sus Ubicaciones

#### **FASE 0: Sistema de Roles**
- **Configuración:** `config/permission.php`
- **Seeder:** `database/seeders/RoleSeeder.php`
- **Gestión:** ❌ No hay UI para gestionar roles y permisos
- **Edición:** Solo por código o base de datos directa

#### **FASE 1: Estructura Territorial**
- **Departamentos:**
  - ✅ Filament: `/admin/departments`
  - CRUD completo disponible
- **Municipios:**
  - ✅ Filament: `/admin/municipalities`
  - CRUD completo disponible
- **Barrios:**
  - ✅ Filament: `/admin/neighborhoods`
  - CRUD completo disponible
- **Configuración:** No requiere configuración adicional

#### **FASE 2: Campañas**
- **Gestión:**
  - ✅ Filament: `/admin/campaigns`
  - CRUD completo con formulario de 3 secciones
- **Configuración:** Settings integrado en el formulario de campaña
- **Relaciones:** Asignación de usuarios a campañas disponible

#### **FASE 3: Usuarios y Jerarquía**
- **Gestión:**
  - ✅ Perfil personal: `/settings/profile`
  - ❌ Gestión de otros usuarios: NO EXISTE
- **Asignaciones Territoriales:**
  - ❌ No hay UI para crear/editar asignaciones territoriales

#### **FASE 4: Votantes**
- **Gestión:**
  - ❌ No hay Resource en Filament
  - ❌ No hay vistas para importación masiva
  - ❌ No hay vistas para gestión individual

#### **FASE 5: Validación y Censo**
- **Gestión:**
  - ❌ No hay Resource para CensusRecord
  - ❌ No hay Resource para ValidationHistory
  - ❌ No hay UI para importar censo
  - ❌ No hay UI para validar votantes

#### **FASE 6.1: Encuestas**
- **Gestión:**
  - ❌ No hay SurveyResource en Filament
  - ✅ Aplicación pública: `/surveys/{id}/apply`
- **Configuración:** No disponible en UI

#### **FASE 6.2: Mensajería (Parcial)**
- **Estado:** Modelos creados pero sin implementación
- **Gestión:** ❌ No existe

#### **FASE 6.3: Call Center**
- **Gestión:**
  - ✅ Filament: `/admin/verification-calls`
  - ✅ Queue: `/calls/queue` (Volt)
  - ✅ Registro: `/calls/register` (Volt)
- **Configuración:** No requiere configuración adicional
- **Asignaciones:** ❌ No hay UI para gestionar CallAssignment

---

### 🎛️ Configuraciones del Sistema

#### **Archivo de Configuración Principal**
- `config/app.php` - Configuración general (locale, timezone, etc.)
- `config/filament.php` - NO EXISTE (Filament v4 usa PanelProvider)
- `app/Providers/Filament/AdminPanelProvider.php` - Configuración del panel

#### **Configuraciones Disponibles**
```php
// En AdminPanelProvider.php
->id('admin')              // ID del panel
->path('admin')            // URL del panel
->colors([                 // Colores del tema
    'primary' => Color::Amber,
])
->navigationGroups([       // Grupos de navegación
    'Gestión',
    'Configuración',
])
```

#### **Configuraciones FALTANTES**
- ❌ No hay settings page en Filament
- ❌ No hay gestión de permisos por rol
- ❌ No hay configuración de notificaciones
- ❌ No hay configuración de integraciones (SMS, etc.)

---

## 3️⃣ SISTEMA DE TRADUCCIÓN

### 📝 Estado Actual de Internacionalización

#### **Configuración de Idioma**
```php
// config/app.php
'locale' => 'en',           // Idioma por defecto
'fallback_locale' => 'en',  // Idioma de respaldo
'faker_locale' => 'es_CO',  // Idioma para Faker
```

#### **Problemas Identificados**

1. **Filament está en inglés:**
   - Recursos: "Departments", "Municipalities", etc.
   - Formularios: "Name", "Code", "Save", etc.
   - Mensajes: "Successfully created", "Are you sure?", etc.

2. **Enums en español:**
   - CallResult: "Contestada", "Sin Respuesta", etc. ✅
   - VoterStatus: Labels en español ✅
   - Pero Filament los muestra mezclados con inglés

3. **Vistas Volt mezcladas:**
   - Algunas etiquetas en español
   - Validaciones en inglés
   - Mensajes del sistema en inglés

---

### 🔧 Solución Propuesta: Implementar Traducción Completa

#### **Paso 1: Publicar traducciones de Filament**
```bash
php artisan filament:install --panels
php artisan vendor:publish --tag=filament-translations
```

#### **Paso 2: Crear archivos de idioma**
```
lang/
├── en/
│   ├── filament.php
│   ├── validation.php
│   └── messages.php
├── es/
│   ├── filament.php       # Traducciones de Filament
│   ├── validation.php     # Mensajes de validación
│   ├── messages.php       # Mensajes del sistema
│   ├── auth.php          # Mensajes de autenticación
│   ├── passwords.php     # Mensajes de contraseñas
│   └── enums.php         # Traducciones de Enums
```

#### **Paso 3: Configurar Filament para español**
```php
// En AdminPanelProvider.php
->locale('es')
->defaultLocale('es')
```

#### **Paso 4: Traducir Recursos**
```php
// Ejemplo: CampaignResource.php
protected static ?string $navigationLabel = 'Campañas';
protected static ?string $modelLabel = 'Campaña';
protected static ?string $pluralModelLabel = 'Campañas';
```

#### **Paso 5: Traducir formularios y tablas**
```php
// En CampaignForm.php
TextInput::make('name')
    ->label(__('Nombre'))
    ->required()
    ->helperText(__('Ingrese el nombre de la campaña'));
```

---

### 🎯 Archivos que Requieren Traducción

#### **Recursos Filament (5 archivos)**
- `CampaignResource.php`
- `DepartmentResource.php`
- `MunicipalityResource.php`
- `NeighborhoodResource.php`
- `VerificationCallResource.php`

#### **Formularios (5 archivos)**
- `CampaignForm.php`
- `VerificationCallForm.php`
- Otros formularios en Resources

#### **Tablas (5 archivos)**
- `CampaignsTable.php`
- `VerificationCallsTable.php`
- Otras tablas en Resources

#### **Vistas Volt (16 archivos)**
- Auth: login, register, forgot-password, etc.
- Settings: profile, password, appearance, two-factor
- Calls: queue, register
- Surveys: apply-survey

#### **Widgets (3 archivos)**
- `CallCenterStatsWidget.php`
- `SurveyResultsWidget.php`
- `SurveyStatsOverview.php`

---

## 📊 RESUMEN DE FALTANTES CRÍTICOS

### 🚨 Alta Prioridad

1. **UserResource en Filament**
   - Gestión de usuarios del sistema
   - Asignación de roles
   - Gestión de permisos
   - Asignación a campañas

2. **VoterResource en Filament**
   - CRUD de votantes
   - Importación masiva desde CSV/Excel
   - Asignación a campañas
   - Cambio de estados
   - Historial de interacciones

3. **Sistema de Traducción Completo**
   - Archivos de idioma en español
   - Configuración de Filament en español
   - Traducción de todos los recursos
   - Selector de idioma en settings

### ⚠️ Media Prioridad

4. **SurveyResource en Filament**
   - CRUD de encuestas
   - Gestión de preguntas
   - Visualización de resultados
   - Exportación de datos

5. **CallAssignmentResource**
   - Asignación manual de votantes
   - Reasignación de llamadas
   - Estadísticas por agente

6. **Settings Page en Filament**
   - Configuración general del sistema
   - Configuración de notificaciones
   - Configuración de integraciones

### 🔵 Baja Prioridad

7. **CensusRecordResource**
   - Gestión de registros de censo
   - Importación masiva

8. **TerritorialAssignmentResource**
   - Gestión de asignaciones territoriales

9. **Dashboards personalizados por rol**
   - Dashboard para TeamLead
   - Dashboard para Analyst
   - Dashboard para Operator

---

## 🎯 RECOMENDACIONES

### Orden de Implementación Sugerido

1. **Sistema de Traducción** (1-2 días)
   - Configurar español como idioma principal
   - Traducir recursos existentes
   - Crear archivos de idioma

2. **UserResource** (2-3 días)
   - CRUD completo
   - Asignación de roles
   - Asignación a campañas
   - Filtros y búsqueda

3. **VoterResource** (3-4 días)
   - CRUD completo
   - Importación masiva
   - Exportación
   - Filtros avanzados

4. **SurveyResource** (2-3 días)
   - CRUD de encuestas
   - Gestión de preguntas
   - Visualización de resultados

5. **Settings Page** (1-2 días)
   - Configuraciones del sistema
   - Preferencias de usuario

---

**Total estimado: 9-14 días de desarrollo**

¿Deseas que comencemos con alguno de estos puntos?
