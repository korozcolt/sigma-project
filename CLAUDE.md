<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Stack Versions
- php 8.4.14 | filament/filament v4 | laravel/fortify v1 | laravel/framework v12
- livewire/flux v2 (FREE) | livewire/livewire v3 | livewire/volt v1 | laravel/pint v1
- laravel/sail v1 | pestphp/pest v4 | phpunit/phpunit v12 | tailwindcss v4

## Conventions
- Follow existing code conventions. Check sibling files for structure/naming.
- Descriptive names: `isRegisteredForDiscounts`, not `discount()`.
- Reuse existing components before creating new ones.
- No new base folders without approval. No dependency changes without approval.
- No verification scripts when tests cover the functionality.
- Only create documentation files if explicitly requested.
- Frontend not updating? Ask user to run `npm run build`, `npm run dev`, or `composer run dev`.

## Import Statements - CRITICAL RULE ⚠️
**ALWAYS explicit `use` statements. NEVER namespace aliases, full inline paths, or inline namespace references.**

✅ DO:
```php
use Filament\Forms\Components\Select;
use App\Models\User;
Select::make('type');
```

❌ NEVER:
```php
use Filament\Forms;
Forms\Components\Select::make('type');   // alias
\App\Models\User::class                  // inline path
```
Violation causes runtime errors.


=== boost rules ===

## Laravel Boost Tools
- `list-artisan-commands` — verify artisan params before running.
- `get-absolute-url` — when sharing project URLs.
- `tinker` — debug PHP/Eloquent. `database-query` for read-only DB access.
- `browser-logs` — browser errors (recent logs only).
- `search-docs` — **use before coding** for all Laravel ecosystem docs. Pass package arrays, use broad topic queries (not package names). Multiple queries supported.


=== php rules ===

## PHP
- Always curly braces for control structures.
- Constructor property promotion: `public function __construct(public GitHub $github) {}`
- No empty `__construct()` with zero params.
- Always explicit return types and parameter type hints.
- PHPDoc over inline comments. Array shape types where appropriate.
- Enum keys: TitleCase (`FavoritePerson`, `Monthly`).


=== filament/core rules ===

## Filament
- `search-docs` before writing Filament code.
- Use Filament Artisan commands (`list-artisan-commands`). Always `--no-interaction`.
- Static `make()` methods. Use `relationship()` on form components for select/checkbox options.
- Tests: authenticate first. Use `livewire()` or `Livewire::test()`.

```php
livewire(ListUsers::class)->assertCanSeeTableRecords($users);
livewire(CreateUser::class)->fillForm([...])->call('create')->assertNotified()->assertRedirect();
livewire(EditInvoice::class, ['invoice' => $invoice])->callAction('send');
Filament::setCurrentPanel('app'); // multi-panel tests
```


=== filament/v4 rules ===

## Filament 4 Changes
- File visibility `private` by default.
- `deferFilters` is default. Disable: `deferFilters(false)`.
- `Grid`, `Section`, `Fieldset` no longer span all columns by default.
- No `all` pagination page method by default.
- All actions extend `Filament\Actions\Action`. No `Filament\Tables\Actions` namespace.
- Form/Infolist layout components → `Filament\Schemas\Components` (Grid, Section, Fieldset, Tabs, Wizard…).
- Icons: `Filament\Support\Icons\Heroicon` enum by default.
- Structure: `Schemas/Components/` | `Tables/Columns/` | `Tables/Filters/` | `Actions/`


=== laravel/core rules ===

## Laravel
- `php artisan make:` for all new files. Always `--no-interaction`.
- DB: Eloquent with return type hints. Avoid `DB::`, prefer `Model::query()`. Eager load to prevent N+1.
- New models: create factories + seeders.
- APIs: Eloquent API Resources + versioning (match existing convention).
- Validation: Form Request classes only, never inline in controllers.
- Queues: `ShouldQueue` for time-consuming tasks.
- URLs: named routes + `route()`.
- Config: never `env()` outside config files. Use `config('app.name')`.
- Tests: use factories. `php artisan make:test --pest <name>` (add `--unit` for unit tests).
- Vite error: run `npm run build` or ask user to run `npm run dev`.


=== laravel/v12 rules ===

## Laravel 12
- No `app/Http/Middleware/`. Register middleware in `bootstrap/app.php`.
- `bootstrap/providers.php` for service providers. No `app/Console/Kernel.php`.
- Commands auto-register from `app/Console/Commands/`.
- Column migration: include ALL previously defined attributes or they're dropped.
- Eager load limit natively: `$query->latest()->limit(10)`.
- Model casts: `casts()` method preferred over `$casts` property (follow existing).


=== fluxui-free/core rules ===

## Flux UI Free (no Pro components)
- Use Flux components when available, fallback to Blade. Use `search-docs` for docs.
- Available: `avatar, badge, brand, breadcrumbs, button, callout, checkbox, dropdown, field, heading, icon, input, modal, navbar, profile, radio, select, separator, switch, text, textarea, tooltip`


=== livewire/core rules ===

## Livewire
- `search-docs` for exact version docs. Create: `php artisan make:livewire ComponentName`.
- State on server. Always validate + authorize in actions.
- Single root element. `wire:loading`, `wire:dirty`. `wire:key` in loops.
- Lifecycle: `mount()`, `updatedFoo()`.
- Namespace: `App\Livewire`. Events: `$this->dispatch()`. Layout: `components.layouts.app`.
- `wire:model` deferred; use `wire:model.live` for real-time.
- Alpine included — don't add manually. Plugins: persist, intersect, collapse, focus.


=== volt/core rules ===

## Livewire Volt
- Use Volt for all new interactive pages. Create: `php artisan make:volt [name] [--pest]`
- Class-based preferred (extends `Livewire\Volt\Component`).
- Tests: `Volt::test('component-name')`. Test dir: `tests/Feature/Volt` (or existing).

```php
new class extends Component {
    public int $count = 0;
    public function increment(): void { $this->count++; }
}
```


=== pint/core rules ===

## Pint
- Run `vendor/bin/pint --dirty` before finalizing any changes.


=== pest/core rules ===

## Pest Testing
- All tests use Pest. Create: `php artisan make:test --pest <name>`.
- Never remove test files without approval.
- Test happy paths, failure paths, edge cases.
- Run minimal tests: `php artisan test --filter=testName` or specific file.
- Ask user to run full suite after related tests pass.
- Status assertions: `assertForbidden()`, `assertNotFound()` — not `assertStatus(403)`.
- Mock: `use function Pest\Laravel\mock;` or `$this->mock()`.
- Datasets: use for repeated data (validation rules, etc).


=== pest/v4 rules ===

## Pest 4 Browser Testing
- Browser tests in `tests/Browser/`. Use `search-docs` for guidance.
- Supports `Event::fake()`, `assertAuthenticated()`, `RefreshDatabase`, factories.
- Can test multiple browsers, devices/viewports, dark/light mode.


=== tailwindcss/core rules ===

## Tailwind
- Check existing conventions first. `search-docs` for docs.
- `gap-*` for spacing in lists, not margins. Support `dark:` if existing components do.


=== tailwindcss/v4 rules ===

## Tailwind v4
- Import: `@import "tailwindcss"` (not `@tailwind` directives). No `corePlugins`.
- Removed: `bg-opacity-*`→`bg-black/*`, `flex-shrink-*`→`shrink-*`, `flex-grow-*`→`grow-*`, `overflow-ellipsis`→`text-ellipsis`, `flex-grow-*`→`grow-*`.


=== tests rules ===

## Test Enforcement
- Every change must have a test. Run affected tests before finishing.


=== laravel/fortify rules ===

## Fortify
- `search-docs` before implementing auth features.
- Config: `config/fortify.php`. Routes: `list-routes` (vendor + Fortify filter).
- Set `'views' => false` if handling views yourself.
- Customize in `FortifyServiceProvider::boot()`. Actions in `app/Actions/Fortify/`.
- Features: `registration()`, `emailVerification()`, `twoFactorAuthentication()`, `updateProfileInformation()`, `updatePasswords()`, `resetPasswords()`.

</laravel-boost-guidelines>

<!-- GSD:project-start source:PROJECT.md -->
## Project

**SIGMA - Sistema Integral de Gestion y Analisis Electoral**

Brownfield political operations platform. Centralizes campaign setup, territorial organization, voter operations, validation, communications, reporting, and election-day execution with role-based access and campaign-level data isolation.

**Core Value:** Campaign teams run critical voter and field operations from one place with trustworthy, campaign-safe data and clear operational traceability.

### Constraints
- **Architecture**: Maintain Laravel/Filament/Livewire/Eloquent — harden in place, no major rewrites.
- **Scope**: Harden existing workflows over new modules.
- **Isolation**: Campaign data isolation strict by default — no cross-campaign leakage.
- **Roles**: Admins, coordinators, leaders, reviewers each need stable boundaries.
- **Operations**: Reporting/widgets/exports must reflect campaign reality — inaccurate numbers unacceptable.
- **Quality**: Voter and Day D flows require test protection.
<!-- GSD:project-end -->

<!-- GSD:stack-start source:codebase/STACK.md -->
## Stack
- Laravel 12 | Livewire 3/Volt/Flux | Filament 4 | Pest 4 | Playwright 1.57 | Vite 7 | Tailwind 4 | Pint 1.24
- spatie/laravel-permission 6.22 | laravel/fortify 1.30 | maatwebsite/excel 3.1
<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->
## Conventions
- Naming: `PascalCase` classes, `kebab-case.blade.php` views, `camelCase` methods/props, `snake_case` DB columns, `UPPER_SNAKE_CASE` constants.
- Pint (PSR-12). `use` statements alphabetical.
- Thin controllers — logic in Actions/Services with `handle()` or `execute()`.
- Exceptions: specific classes for domain logic. Rely on global handler for API/web responses.
- Log: `Log::info/error/debug`. Only catch+log if continuing execution.
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->
## Architecture
- Laravel MVC + Livewire/Volt (reactive frontend) + Filament (admin CRUD) + Eloquent ORM.
- Routes: `routes/web.php`, `api.php`, `console.php`.
- Views: `resources/views/` (Blade) + `resources/views/livewire/` (Volt).
- Filament: `app/Filament/` (Resources, Pages, Clusters).
- Models: `app/Models/`. Migrations: `database/migrations/`.
- Entry: `public/index.php` (HTTP) | `artisan` (CLI).
- Auth: Fortify sessions + Spatie RBAC.
<!-- GSD:architecture-end -->

<!-- GSD:workflow-start source:GSD defaults -->
## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:
- `/gsd:quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd:debug` for investigation and bug fixing
- `/gsd:execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->

<!-- GSD:profile-start -->
## Developer Profile

> Profile not yet configured. Run `/gsd:profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
