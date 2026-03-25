# Technology Stack

**Analysis Date:** 2026-03-24

## Languages

**Primary:**
- PHP 8.2+ - Backend logic, APIs, and Livewire components
- JavaScript/TypeScript - Build tooling and some frontend logic

**Secondary:**
- Blade (HTML) - Server-side rendering templates
- CSS (TailwindCSS) - Styling

## Runtime

**Environment:**
- PHP 8.2+
- Node.js (for Vite and asset compilation)

**Package Manager:**
- Composer 2.x - PHP dependencies
- npm 9.x+ - Node.js dependencies
- Lockfiles: `composer.lock` and `package-lock.json` present

## Frameworks

**Core:**
- Laravel 12.0 - Web application framework
- Livewire 3.x (via Flux/Volt) - Dynamic frontend interfaces
- Filament 4.0 - Admin panel framework

**Testing:**
- Pest PHP 4.1 - Unit and Feature testing
- Playwright 1.57 - End-to-end browser testing

**Build/Dev:**
- Vite 7.0 - Frontend asset bundling
- TailwindCSS 4.0 - Utility-first CSS framework
- Laravel Pint 1.24 - Code style fixer

## Key Dependencies

**Critical:**
- spatie/laravel-permission 6.22 - Role and permission management
- laravel/fortify 1.30 - Headless authentication backend
- livewire/flux 2.1 & livewire/volt 1.7 - Livewire components and UI
- maatwebsite/excel 3.1 - Excel export/import

**Infrastructure:**
- Laravel Sail 1.41 - Local Docker development environment
- Laravel Tinker 2.10 - Interactive REPL

## Configuration

**Environment:**
- `.env` files for environment variables
- `config/` directory for Laravel configuration files

**Build:**
- `vite.config.js` - Vite build configuration
- `package.json` scripts (`npm run dev`, `npm run build`) for assets

## Platform Requirements

**Development:**
- PHP 8.2+ or Docker (via Laravel Sail)
- Node.js & npm

**Production:**
- PHP 8.2+ web server (Nginx/Apache)
- Database (SQLite, MySQL, PostgreSQL, etc)
- Node.js (only for build step, not required at runtime)

---

*Stack analysis: 2026-03-24*
*Update after major dependency changes*
