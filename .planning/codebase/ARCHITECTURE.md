# Architecture

**Analysis Date:** 2026-03-24

## Pattern Overview

**Overall:** Full-stack MVC with Reactive Frontend and Admin Panel

**Key Characteristics:**
- Laravel MVC backend processing HTTP requests
- Livewire/Volt for reactive, component-based frontend without writing JS
- Filament PHP for generating admin panels and backend CRUD interfaces
- Eloquent ORM for database interactions

## Layers

**Routing Layer:**
- Purpose: Map web endpoints to logic
- Contains: `routes/web.php`, `routes/api.php`, `routes/console.php`
- Depends on: Livewire Components, Controllers, Filament Resources
- Used by: Laravel framework request lifecycle

**Frontend / View Layer (Livewire & Tailwind):**
- Purpose: Render HTML and handle user interactions
- Contains: Blade templates (`resources/views/`), Volt components (`resources/views/livewire/`), Tailwind CSS (`resources/css/`)
- Depends on: Models, sometimes Services
- Used by: Routing Layer

**Admin Panel Layer (Filament):**
- Purpose: Manage backend data and administrative tasks
- Contains: Filament Resources, Pages, Clusters (`app/Filament/`)
- Depends on: Eloquent Models
- Used by: Admin users (`/admin` namespace typically)

**Business & Data Layer (Eloquent):**
- Purpose: Data persistence, schema interactions, and business rules
- Contains: Models (`app/Models/`), Migrations (`database/migrations/`), Factories, Seeders
- Depends on: Database
- Used by: Livewire Components, Filament Resources, Controllers

## Data Flow

**Standard Web Request (Livewire):**
1. User visits URL
2. `routes/web.php` maps URL to a Livewire Component (Volt)
3. Component mounts, fetches data via Eloquent Models
4. Component renders corresponding Blade view with initial state
5. Successive UI interactions send AJAX requests triggering component methods
6. Component updates state, re-renders DOM diffs

**Admin Request (Filament):**
1. Admin visits `/admin/` URL
2. Filament handles routing automatically
3. Filament Resource fetches data via Eloquent
4. Native Filament UI renders tables/forms
5. Submits process directly through Resource logic to save to DB

**State Management:**
- Server-side state: Handled by Livewire (serialized between requests)
- Database state: PostgreSQL/MySQL/SQLite via Eloquent
- Session state: Laravel database/file session driver

## Key Abstractions

**Livewire Volt Component:**
- Purpose: Single-file components combining PHP logic and Blade syntax
- Examples: `resources/views/livewire/[name].blade.php`
- Pattern: Reactive UI component

**Filament Resource:**
- Purpose: CRUD blueprint for Eloquent Models
- Examples: `app/Filament/Resources/UserResource.php`
- Pattern: Builder pattern defining inputs and columns

**Eloquent Model:**
- Purpose: Active Record implementation linking objects to DB rows
- Examples: `User.php`
- Pattern: Active Record

## Entry Points

**Web Requests:**
- Location: `public/index.php`
- Triggers: Nginx/Apache handling HTTP traffic
- Responsibilities: Boot Laravel, handle request, return response

**CLI Commands:**
- Location: `artisan` (root directory)
- Triggers: CLI invocation (`php artisan`)
- Responsibilities: Run migrations, dev servers, queue workers, custom commands

## Error Handling

**Strategy:** Global Exception Handler bubbling up exceptions to Laravel's default error page or API JSON response.

**Patterns:**
- Try/catch within specific complex business logic
- Validation exceptions thrown automatically via Laravel Form Requests or Livewire validation (`$this->validate()`)

## Cross-Cutting Concerns

**Logging:**
- Approach: Laravel Log facade writing to `storage/logs/laravel.log` via Monolog

**Validation:**
- Approach: Handled primarily within Livewire component methods or Filament form definitions.

**Authentication:**
- Approach: Laravel session-based authentication (Fortify) + Spatie Laravel Permissions for RBAC.

---

*Architecture analysis: 2026-03-24*
*Update when major patterns change*
