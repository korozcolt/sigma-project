# 🗳️ SIGMA - Sistema Integral de Gestión y Análisis Electoral

[![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.36-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/)
[![Filament](https://img.shields.io/badge/Filament-4.2-FDAE4B?style=for-the-badge&logo=data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDgiIGhlaWdodD0iNDgiIHZpZXdCb3g9IjAgMCA0OCA0OCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTAgMEg0OFY0OEgwVjBaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K&logoColor=white)](https://filamentphp.com/)
[![Livewire](https://img.shields.io/badge/Livewire-3.6-FB70A9?style=for-the-badge&logo=livewire&logoColor=white)](https://livewire.laravel.com/)
[![Tests](https://img.shields.io/badge/Tests-279_Passing-22C55E?style=for-the-badge&logo=checkmarx&logoColor=white)](https://pestphp.com/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4.1-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)

Plataforma completa para la gestión y análisis de campañas políticas, desde el registro de votantes hasta el análisis post-electoral.

---

## 📋 Tabla de Contenidos

- [Acerca de SIGMA](#acerca-de-sigma)
- [Características](#características)
- [Stack Tecnológico](#stack-tecnológico)
- [Documentación](#documentación)
- [Instalación](#instalación)
- [Desarrollo](#desarrollo)
- [Testing](#testing)
- [Estado del Proyecto](#estado-del-proyecto)

---

## 🎯 Acerca de SIGMA

SIGMA es una plataforma diseñada para apoyar campañas políticas mediante la recolección, validación y análisis de información de posibles votantes. El sistema permite administrar estructura territorial y de liderazgo, verificar registros contra el censo electoral oficial y acompañar el proceso desde identificación del votante hasta la confirmación de su voto el día de elecciones.

### Funcionalidades Principales

- ✅ **Multi-Campaña:** Gestiona múltiples campañas simultáneamente
- 🗺️ **Gestión Territorial:** Organización por Departamento → Municipio → Barrio
- 👥 **Jerarquía de Usuarios:** Super Admin → Admin Campaña → Coordinadores → Líderes
- 🗳️ **Registro de Votantes:** Captura completa de información con validación
- ✅ **Validación contra Censo:** Verificación automática de habilitación electoral
- 📞 **Sistema de Llamadas:** Confirmación telefónica con encuestas
- 📊 **Encuestas Personalizadas:** Medición de intención de voto y compromiso
- 🎂 **Módulo de Cumpleaños:** Mensajería automatizada vía WhatsApp/SMS
- 📈 **Reportes y Analítica:** Dashboards estratégicos y exportación de datos
- 🔐 **Seguridad:** 2FA, roles y permisos granulares

---

## ✨ Características

### Para Administradores de Campaña
- Dashboard ejecutivo con métricas clave
- Configuración de campaña y territorio
- Gestión de equipo (coordinadores y líderes)
- Importación de censo electoral
- Creación de encuestas personalizadas
- Reportes exportables (Excel/PDF)

### Para Coordinadores
- Gestión de líderes asignados
- Supervisión de captación por territorio
- Validación de registros de votantes
- Estadísticas de su zona

### Para Líderes
- Registro rápido de votantes
- Seguimiento de su base electoral
- Aplicación de encuestas
- Notificaciones y recordatorios

### Para Revisores
- Queue de votantes por validar
- Registro de llamadas de verificación
- Aplicación de encuestas telefónicas
- Aprobación/rechazo masivo

---

## 🛠️ Stack Tecnológico

### Backend
- **Laravel 12.36** - Framework PHP
- **PHP 8.4** - Lenguaje
- **SQLite** - Base de datos (dev)
- **Laravel Fortify** - Autenticación
- **Spatie Laravel Permission** - Roles y permisos

### Frontend
- **Filament 4.2** - Panel de administración
- **Livewire 3.6** - Componentes reactivos
- **Volt 1.8** - API funcional para Livewire
- **Flux UI 2.6** - Componentes de UI
- **Tailwind CSS 4.1** - Estilos
- **Alpine.js** - Interactividad ligera

### Testing & Quality
- **Pest 4.1** - Framework de testing
- **Laravel Pint 1.x** - Code formatter
- **PHPUnit 12** - Testing unitario

### DevOps
- **Laravel Herd** - Entorno de desarrollo local
- **Vite** - Asset bundling

---

## 📚 Documentación

El proyecto cuenta con documentación completa:

### Documentos Principales

| Documento | Descripción | Ubicación |
|-----------|-------------|-----------|
| **SIGMA.md** | Especificación del dominio electoral y reglas de negocio | `./SIGMA.md` |
| **PLAN_DESARROLLO.md** | Plan maestro detallado con todas las tareas y especificaciones | `./PLAN_DESARROLLO.md` |
| **PROGRESO.md** | Tracking diario del avance del proyecto | `./PROGRESO.md` |
| **CLAUDE.md** | Guidelines de desarrollo y mejores prácticas | `./CLAUDE.md` |

### Documentación Técnica

| Documento | Descripción | Ubicación |
|-----------|-------------|-----------|
| **GUIA_USO_PLAN.md** | Cómo usar efectivamente el plan de desarrollo | `./docs/GUIA_USO_PLAN.md` |
| **DECISIONES.md** | Registro de decisiones técnicas (ADR) | `./docs/DECISIONES.md` |

### Lectura Recomendada

1. **Nuevos en el proyecto:** Leer `SIGMA.md` → `PLAN_DESARROLLO.md` → `docs/GUIA_USO_PLAN.md`
2. **Desarrolladores:** Leer `CLAUDE.md` → `PLAN_DESARROLLO.md` → `docs/DECISIONES.md`
3. **Tracking diario:** Consultar `PROGRESO.md`

---

## 🚀 Instalación

### Requisitos

- PHP 8.4+
- Composer
- Node.js 18+
- NPM

### Pasos

```bash
# 1. Clonar repositorio
git clone [url-del-repo] sigma-project
cd sigma-project

# 2. Instalar dependencias PHP
composer install

# 3. Instalar dependencias Node
npm install

# 4. Configurar entorno
cp .env.example .env
php artisan key:generate

# 5. Crear base de datos
touch database/database.sqlite

# 6. Ejecutar migraciones
php artisan migrate

# 7. Importar datos territoriales de Colombia
php artisan import:colombia-data

# 8. Seeders (roles y super admin)
php artisan db:seed

# 9. Compilar assets
npm run build

# 10. Iniciar servidor
php artisan serve
```

### Acceso

- **Frontend:** http://localhost:8000
- **Admin Panel:** http://localhost:8000/admin

### Usuario por Defecto

**Email:** ing.korozco@gmail.com
**Rol:** Super Admin

_(El password se debe configurar en el seeder)_

---

## 💻 Desarrollo

### Comandos Útiles

```bash
# Desarrollo con hot reload
npm run dev

# Ejecutar tests
php artisan test

# Ejecutar tests específicos
php artisan test --filter=NombreTest

# Formatear código
vendor/bin/pint

# Ver cobertura de tests
php artisan test --coverage

# Crear nuevo modelo con todo
php artisan make:model NombreModelo -mfsr

# Crear Filament resource
php artisan make:filament-resource NombreModelo --generate

# Crear Livewire Volt component
php artisan make:volt nombre-component

# Crear test
php artisan make:test NombreTest --pest
```

### Flujo de Trabajo

1. Consultar `PROGRESO.md` para ver qué sigue
2. Leer especificación en `PLAN_DESARROLLO.md`
3. Crear rama de feature
4. Implementar código siguiendo `CLAUDE.md`
5. Escribir tests
6. Ejecutar tests y Pint
7. Commit semántico
8. Actualizar `PROGRESO.md`
9. Push y merge

Ver `docs/GUIA_USO_PLAN.md` para detalles.

### Estructura del Proyecto

```
sigma-project/
├── app/
│   ├── Console/         # Comandos Artisan
│   ├── Enums/           # Enumerables
│   ├── Filament/        # Resources de Filament
│   ├── Http/            # Controllers, Middleware
│   ├── Jobs/            # Jobs en queue
│   ├── Models/          # Eloquent models
│   ├── Policies/        # Authorization policies
│   └── Services/        # Lógica de negocio
├── database/
│   ├── factories/       # Model factories
│   ├── migrations/      # Migraciones
│   └── seeders/         # Seeders
├── docs/                # Documentación técnica
├── resources/
│   └── views/
│       ├── livewire/    # Volt components
│       └── components/  # Blade components
├── routes/
│   ├── web.php          # Rutas web
│   ├── api.php          # Rutas API
│   └── console.php      # Comandos de consola
└── tests/
    ├── Feature/         # Feature tests
    ├── Unit/            # Unit tests
    └── Browser/         # Browser tests (Pest v4)
```

---

## 🧪 Testing

### Filosofía de Testing

- **Todos los cambios deben tener tests**
- Target de cobertura: **80%+**
- Feature tests para flujos de usuario
- Unit tests para lógica de negocio
- Browser tests para flujos críticos

### Ejecutar Tests

```bash
# Todos los tests
php artisan test

# Con cobertura
php artisan test --coverage

# Tests específicos
php artisan test --filter=VoterTest

# Tests de una carpeta
php artisan test tests/Feature/Voters/

# Parallel testing
php artisan test --parallel
```

### Escribir Tests

```php
// tests/Feature/VoterTest.php
use function Pest\Laravel\{actingAs, assertDatabaseHas};

it('can create a voter', function () {
    $user = User::factory()->create();

    actingAs($user);

    $voter = Voter::factory()->create();

    assertDatabaseHas('voters', [
        'id' => $voter->id,
    ]);
});
```

---

## 📊 Estado del Proyecto

### Progreso General

**Fase Actual:** FASE 6 - Módulos Estratégicos

**Progreso Total:** 55% (15/28 módulos principales completados)

**Tests:** 279 pasando (608 aserciones)

Ver `PROGRESO.md` para detalle actualizado diariamente.

### Fases del Desarrollo

- ✅ **FASE 0:** Configuración Base y Roles
- ✅ **FASE 1:** Estructura Territorial
- ✅ **FASE 2:** Sistema Multi-Campaña
- ✅ **FASE 3:** Gestión de Usuarios y Jerarquía
- ✅ **FASE 4:** Módulo de Votantes
- ✅ **FASE 5:** Validación y Censo Electoral
- ⏳ **FASE 6:** Módulos Estratégicos (Encuestas, Cumpleaños, Llamadas)
- ⏳ **FASE 7:** Reportes y Analítica

### Estado Actual

✅ **Completado:**
- ✅ Sistema de autenticación completo con 2FA
- ✅ Panel de administración Filament
- ✅ UI con Livewire Volt y Flux
- ✅ Sistema de roles y permisos (5 roles)
- ✅ Estructura territorial (33 departamentos, 1,123 municipios)
- ✅ Sistema multi-campaña con versionamiento
- ✅ Gestión de usuarios y asignaciones territoriales
- ✅ Módulo completo de votantes (8 estados)
- ✅ Validación contra censo electoral
- ✅ Historial de validaciones y auditoría
- ✅ Importación de censo en lotes
- ✅ Sistema de encuestas (5 tipos de preguntas, versionamiento)
- ✅ 279 tests con 80% cobertura

⏳ **En Desarrollo:**
- Métricas de encuestas
- Mensajería política (WhatsApp/SMS)
- Call center workflow

📋 **Siguiente:**
- Reportes y analítica
- Widgets de Filament
- API REST

---

## 🤝 Contribución

### Commits Semánticos

```bash
feat(scope): descripción
fix(scope): descripción
test(scope): descripción
docs(scope): descripción
refactor(scope): descripción
```

### Antes de Hacer Push

- [ ] Tests pasan
- [ ] Código formateado con Pint
- [ ] Documentación actualizada
- [ ] `PROGRESO.md` actualizado

---

## 📄 Licencia

_(Definir licencia)_

---

## 👥 Equipo

_(Agregar información del equipo)_

---

## 📞 Soporte

Para preguntas o issues:
- Revisar documentación en `/docs`
- Consultar `PLAN_DESARROLLO.md`
- _(Agregar canales de comunicación)_

---

**Desarrollado con ❤️ usando Laravel + Filament + Livewire**
