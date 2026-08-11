# 🗳️ SIGMA - Sistema Integral de Gestión y Análisis Electoral

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square)](https://laravel.com/)
[![Filament](https://img.shields.io/badge/Filament-4-FDAE4B?style=flat-square)](https://filamentphp.com/)
[![Pest](https://img.shields.io/badge/Tests-Pest_4-22C55E?style=flat-square)](https://pestphp.com/)

Plataforma de gestión de operaciones electorales: organización territorial, ciclo de vida del votante ("Apoyo"), validación contra el censo, comunicaciones, reportería y ejecución de Día D, con aislamiento estricto de datos por campaña y control de acceso por rol.

---

## 🎯 Estado del Proyecto

**v1.0 MVP Hardening** y **v1.1 Consulta de Puesto de Votación Resiliente** — enviados y en producción.
**v1.2 Articuladores + Metadata de Usuario** — en progreso (fases 12-15 de 17 completadas).

Ver `.planning/PROJECT.md` para el estado detallado y decisiones vigentes, `CHANGELOG.md` para el historial de cambios, y `.planning/MILESTONES.md` para el detalle de lo ya enviado.

### Características Principales

- ✅ Sistema multi-campaña con aislamiento estricto por campaña (scopes globales, gate de acceso cruzado)
- ✅ Gestión de usuarios con 7 roles: Super Admin, Admin Campaña, Coordinador, Articulador, Líder, Revisor, Analista de Reportes
- ✅ Jerarquía de dos niveles: articulador → coordinador → líder, con autorización explícita por propiedad (`CoordinatorPolicy`)
- ✅ Base de datos electoral completa (33 departamentos, 1,123 municipios)
- ✅ Registro y validación de Apoyos contra el censo electoral, con manejo de cédulas duplicadas
- ✅ Resolución resiliente de puesto de votación: BD de campaña → snapshot nacional → intento en vivo, con reconciliación automática horaria
- ✅ Sistema de encuestas, call center con tracking de llamadas, y mensajería SMS automatizada (Hablame API)
- ✅ Sistema Día D con evidencia obligatoria (VoteRecord + foto + GPS) y prevención de doble-voto a nivel de BD
- ✅ 5 paneles Filament: Admin, Reportes, Coordinador, Articulador, Líder — cada uno auto-gestionado por su rol

---

## 🚀 Quick Start

### Requisitos
- PHP 8.4+
- Composer
- Node.js 18+

### Instalación

```bash
# 1. Clonar e instalar
git clone [repo-url] sigma-project
cd sigma-project
composer install
npm install

# 2. Configurar entorno
cp .env.example .env
php artisan key:generate

# 3. Base de datos
touch database/database.sqlite
php artisan migrate
php artisan colombia:import       # Importa 33 deptos + 1,123 municipios
php artisan db:seed

# 4. Compilar assets y lanzar
npm run build
php artisan serve
```

### Acceso

- **Panel Admin:** http://localhost:8000/admin
- **Panel Reportes:** http://localhost:8000/reports
- **Panel Coordinadores:** http://localhost:8000/coordinator
- **Panel Articuladores:** http://localhost:8000/articulador
- **Panel Líderes:** http://localhost:8000/leader

**Usuario por defecto:** Ver seeders (`RoleSeeder`, `DatabaseSeeder`) para credenciales

---

## 🛠️ Stack Tecnológico

| Categoría | Tecnología |
|-----------|------------|
| **Backend** | Laravel 12, PHP 8.4, MySQL/SQLite |
| **Frontend** | Filament 4, Livewire 3, Volt, Flux UI, Tailwind CSS 4 |
| **Testing** | Pest 4, PHPUnit 12, Playwright (E2E + visual regression) |
| **Autenticación** | Laravel Fortify (2FA incluido) |
| **Permisos** | Spatie Laravel Permission |
| **DevOps** | Laravel Herd, Vite, Pint |

---

## 📚 Documentación

| Documento | Descripción |
|-----------|-------------|
| **[.planning/PROJECT.md](.planning/PROJECT.md)** | 📊 Estado actual, requisitos validados/activos, decisiones clave |
| **[CHANGELOG.md](CHANGELOG.md)** | 📋 Historial de cambios por versión |
| **[.planning/MILESTONES.md](.planning/MILESTONES.md)** | 🚀 Resumen de milestones ya enviados (v1.0, v1.1) |
| **[CLAUDE.md](CLAUDE.md)** | 🤖 Guidelines de desarrollo y convenciones del proyecto |
| **[docs/REGLAS_NEGOCIO.md](docs/REGLAS_NEGOCIO.md)** | ✅ Reglas de negocio vigentes |
| **[docs/deploy/docker-volumes.md](docs/deploy/docker-volumes.md)** | 🐳 Configuración de volúmenes persistentes para deploy |

---

## 🧪 Testing

```bash
# Todos los tests
php -d memory_limit=512M artisan test

# Tests específicos
php artisan test --filter=VoterTest

# Con cobertura
php artisan test --coverage
```

---

## 💻 Comandos Útiles

```bash
# Desarrollo
npm run dev                    # Hot reload frontend
vendor/bin/pint --dirty        # Formatear código modificado
php artisan test --filter=X    # Tests específicos

# Producción
npm run build                 # Compilar assets
php artisan optimize          # Optimizar Laravel
php artisan config:cache      # Cache configuración

# Datos
php artisan colombia:import           # Importar territorio
php artisan db:seed --class=RoleSeeder  # Crear roles
```

---

## 🤝 Contribución

Este proyecto usa el workflow **GSD** (Get Shit Done) para planificación y ejecución estructurada por fases — ver `.planning/` para el roadmap, estado y planes activos.

1. Leer [CLAUDE.md](CLAUDE.md) para guidelines de código y convenciones del proyecto
2. Trabajar dentro de un flujo GSD (`/gsd:quick`, `/gsd:debug`, `/gsd:execute-phase`) — no editar directamente fuera de un plan salvo excepción explícita
3. Escribir tests (obligatorio — todo cambio requiere cobertura)
4. Ejecutar `vendor/bin/pint --dirty` antes de finalizar
5. Commit semántico: `feat(scope): descripción`

---

**Desarrollado con ❤️ usando Laravel + Filament + Livewire**
