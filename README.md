# 🗳️ SIGMA - Sistema Integral de Gestión y Análisis Electoral

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square)](https://laravel.com/)
[![Filament](https://img.shields.io/badge/Filament-4-FDAE4B?style=flat-square)](https://filamentphp.com/)
[![Tests](https://img.shields.io/badge/Tests-650+_Passing-22C55E?style=flat-square)](https://pestphp.com/)

Plataforma completa para gestión y análisis de campañas políticas, desde el registro de votantes hasta el análisis post-electoral.

---

## 🎯 Estado del Proyecto

**Progreso:** 95% Completado | **Tests:** 650+ pasando | **Estado:** ✅ LISTO PARA PRODUCCIÓN

### Características Principales

- ✅ Sistema multi-campaña (departamental/municipal/regional)
- ✅ Gestión de usuarios con 5 roles (Super Admin, Admin Campaña, Coordinador, Líder, Revisor)
- ✅ Base de datos electoral completa (33 departamentos, 1,123 municipios)
- ✅ Registro y validación de votantes contra censo
- ✅ Sistema de encuestas personalizado
- ✅ Call center con tracking de llamadas
- ✅ Mensajería SMS automatizada (Hablame API)
- ✅ Sistema Día D con evidencia electoral (VoteRecord)
- ✅ 3 paneles Filament (Admin, Líderes, Coordinadores)

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
php artisan import:colombia-data  # Importa 33 deptos + 1,123 municipios
php artisan db:seed

# 4. Compilar assets y lanzar
npm run build
php artisan serve
```

### Acceso

- **Panel Admin:** http://localhost:8000/admin
- **Panel Líderes:** http://localhost:8000/leader
- **Panel Coordinadores:** http://localhost:8000/coordinator

**Usuario por defecto:** Ver seeders para credenciales

---

## 🛠️ Stack Tecnológico

| Categoría | Tecnología |
|-----------|------------|
| **Backend** | Laravel 12, PHP 8.4, SQLite/MySQL |
| **Frontend** | Filament 4, Livewire 3, Volt, Flux UI, Tailwind CSS 4 |
| **Testing** | Pest 4 (650+ tests), PHPUnit 12 |
| **Autenticación** | Laravel Fortify (2FA incluido) |
| **Permisos** | Spatie Laravel Permission |
| **DevOps** | Laravel Herd, Vite, Pint |

---

## 📚 Documentación

| Documento | Descripción |
|-----------|-------------|
| **[PROGRESO.md](PROGRESO.md)** | 📊 Tracking diario, estadísticas, próximos pasos |
| **[CLAUDE.md](CLAUDE.md)** | 🤖 Guidelines de desarrollo y mejores prácticas |
| **[docs/DECISIONES.md](docs/DECISIONES.md)** | 📋 Architecture Decision Records (ADR) |

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

**Cobertura actual:** ~85% | **Pass rate:** 98.5%

---

## 💻 Comandos Útiles

```bash
# Desarrollo
npm run dev                    # Hot reload frontend
vendor/bin/pint               # Formatear código
php artisan test --filter=X   # Tests específicos

# Producción
npm run build                 # Compilar assets
php artisan optimize          # Optimizar Laravel
php artisan config:cache      # Cache configuración

# Datos
php artisan import:colombia-data      # Importar territorio
php artisan db:seed --class=RoleSeeder  # Crear roles
```

---

## 📊 Módulos Completados

- ✅ Autenticación completa (Login, 2FA, Reset Password)
- ✅ Sistema de roles y permisos
- ✅ Estructura territorial (Department → Municipality → Neighborhood)
- ✅ Sistema multi-campaña con scopes
- ✅ Gestión de usuarios y asignaciones territoriales
- ✅ Módulo de votantes (8 estados)
- ✅ Validación contra censo electoral
- ✅ Sistema de encuestas (5 tipos de preguntas)
- ✅ Call Center funcional
- ✅ Mensajería SMS (Hablame API)
- ✅ Sistema Día D con VoteRecord
- ✅ 12 widgets para dashboards
- ✅ Traducción completa al español

---

## 🤝 Contribución

1. Leer [CLAUDE.md](CLAUDE.md) para guidelines
2. Crear branch feature
3. Escribir tests (obligatorio)
4. Ejecutar `vendor/bin/pint`
5. Actualizar [PROGRESO.md](PROGRESO.md)
6. Commit semántico: `feat(scope): descripción`

---

## 📞 Soporte

Para preguntas o issues, consultar la documentación en `/docs` o revisar [PROGRESO.md](PROGRESO.md) para estado actual.

---

**Desarrollado con ❤️ usando Laravel + Filament + Livewire**
