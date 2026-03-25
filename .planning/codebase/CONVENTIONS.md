# Coding Conventions

**Analysis Date:** 2026-03-24

## Naming Patterns

**Files:**
- `PascalCase.php` for standard PHP classes (Controllers, Models, Services)
- `kebab-case.blade.php` for Laravel Blade templates

**PHP Classes/Functions:**
- `PascalCase` for Class Names, Traits, Interfaces
- `camelCase` for methods and class properties
- `snake_case` for database columns and relationships (e.g., `user_id`, `created_at`)

**Variables:**
- `camelCase` in PHP code typically (`$userAccount`), sometimes `$snake_case` used broadly in legacy or array contexts in Laravel.
- `UPPER_SNAKE_CASE` for constants.

## Code Style

**Formatting:**
- Laravel Pint 1.24 (comes installed in `composer.json`)
- Based on standard PSR-12 and Laravel's opinionated formatting.

**Linting / Fixing:**
- Run via `./vendor/bin/pint`

## Import Organization

**Order:**
- `use` statements are ordered alphabetically at the top of the file, which is enforced by Laravel Pint.

## Error Handling

**Patterns:**
- Throw specific Exception classes for validation or domain logic.
- Rely on Laravel's built-in global Exception Handler (`bootstrap/app.php` or `app/Exceptions/Handler.php`) to catch and format API/Web responses.
- Validation logic is handled in `FormRequests` or Livewire validate boundaries, which auto-redirect back on syntax failure.

## Logging

**Framework:**
- Laravel `Log` facade (Monolog under the hood).
- Levels widely used: `Log::info`, `Log::error`, `Log::debug`.

**Patterns:**
- Catch and log exceptions explicitly only if continuing execution:
  `Log::error('Process failed: ' . $e->getMessage(), ['context' => $data]);`

## Function Design

**Size & Returns:**
- Keep controllers thin. Business logic moves to Actions or Services.
- Actions should ideally have a single `handle()` or `execute()` method.

## Frontend (Livewire/Tailwind)

- Use Tailwind utility classes directly in Blade components.
- Livewire Volt components combine PHP logic and UI in a single `* .blade.php` file in `resources/views/livewire`.

---

*Convention analysis: 2026-03-24*
*Update when patterns change*
