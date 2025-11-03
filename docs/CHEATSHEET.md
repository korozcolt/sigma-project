# ⚡ SIGMA Development Cheatsheet

Referencia rápida para desarrollo diario.

---

## 🎯 Inicio Rápido Diario

```bash
# 1. Ver qué sigue
cat PROGRESO.md | grep "Próximos"

# 2. Ejecutar tests existentes
php artisan test

# 3. Iniciar dev server
npm run dev

# 4. Iniciar Laravel
php artisan serve
```

---

## 🏗️ Crear Nuevos Componentes

### Modelo Completo

```bash
php artisan make:model NombreModelo -mfsr
# -m = migration
# -f = factory
# -s = seeder
# -r = resource controller (si se necesita)

# Alternativa completa:
php artisan make:model NombreModelo --all
```

### Filament Resource

```bash
# Básico
php artisan make:filament-resource NombreModelo

# Con páginas personalizadas
php artisan make:filament-resource NombreModelo --view

# Auto-generar desde modelo existente
php artisan make:filament-resource NombreModelo --generate

# Ver opciones
php artisan make:filament-resource --help
```

### Livewire Volt Component

```bash
# Crear component
php artisan make:volt nombre-component

# Con test
php artisan make:volt nombre-component --test --pest

# Ver ubicación
# resources/views/livewire/nombre-component.blade.php
```

### Test

```bash
# Feature test
php artisan make:test NombreTest --pest

# Unit test
php artisan make:test NombreTest --pest --unit

# Browser test (Pest v4)
php artisan make:test NombreTest --pest --browser
```

### Migration

```bash
# Crear tabla
php artisan make:migration create_nombre_table

# Modificar tabla
php artisan make:migration add_campo_to_nombre_table

# Ejecutar migraciones
php artisan migrate

# Rollback
php artisan migrate:rollback

# Fresh (recrear todo)
php artisan migrate:fresh --seed
```

### Seeder

```bash
php artisan make:seeder NombreSeeder

# Ejecutar
php artisan db:seed --class=NombreSeeder

# Todos
php artisan db:seed
```

### Enum

```bash
# No hay comando, crear manualmente
# app/Enums/NombreEnum.php
# Ver docs/PATRON_ENUMS.md para el patrón completo
```

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum NombreEnum: string implements HasLabel, HasColor, HasIcon
{
    case OPCION_UNO = 'opcion_uno';
    case OPCION_DOS = 'opcion_dos';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OPCION_UNO => 'Opción Uno',
            self::OPCION_DOS => 'Opción Dos',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPCION_UNO => 'success',
            self::OPCION_DOS => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::OPCION_UNO => 'heroicon-m-check',
            self::OPCION_DOS => 'heroicon-m-exclamation-triangle',
        };
    }
}
```

### Policy

```bash
php artisan make:policy NombrePolicy --model=NombreModelo
```

### Service

```bash
# No hay comando, crear manualmente
# app/Services/NombreService.php
```

### Job

```bash
php artisan make:job NombreJob
```

---

## 🧪 Testing

### Ejecutar Tests

```bash
# Todos
php artisan test

# Con cobertura
php artisan test --coverage

# Filtro por nombre
php artisan test --filter=NombreTest

# Filtro por método
php artisan test --filter=test_puede_crear_votante

# Archivo específico
php artisan test tests/Feature/VoterTest.php

# Paralelo (más rápido)
php artisan test --parallel
```

### Escribir Tests (Pest Patterns)

```php
// Basic test
it('does something', function () {
    expect(true)->toBeTrue();
});

// Con setup
beforeEach(function () {
    $this->user = User::factory()->create();
});

it('uses user', function () {
    actingAs($this->user);
    // ...
});

// Datasets
it('validates emails', function (string $email) {
    expect($email)->toContain('@');
})->with([
    'james@example.com',
    'taylor@example.com',
]);

// Assertions comunes
expect($value)->toBe(true);
expect($value)->toEqual($expected);
expect($value)->toBeTrue();
expect($value)->toContain('substring');
expect($array)->toHaveCount(3);

// Laravel specific
use function Pest\Laravel\{actingAs, get, post, assertDatabaseHas};

actingAs($user);
get('/dashboard')->assertOk();
post('/voters', $data)->assertRedirect();
assertDatabaseHas('voters', ['email' => 'test@example.com']);
```

---

## 🎨 Formateo y Calidad

### Laravel Pint

```bash
# Formatear archivos modificados
vendor/bin/pint --dirty

# Formatear todo
vendor/bin/pint

# Ver qué cambiaría (sin aplicar)
vendor/bin/pint --test

# Archivo específico
vendor/bin/pint app/Models/Voter.php
```

---

## 🗄️ Database

### Queries Útiles

```bash
# Entrar a SQLite
sqlite3 database/database.sqlite

# Ver tablas
.tables

# Describir tabla
.schema nombre_tabla

# Consulta
SELECT * FROM users LIMIT 10;

# Salir
.exit
```

### Eloquent Patterns

```php
// Crear
$model = Model::create([...]);

// Buscar
$model = Model::find($id);
$model = Model::where('campo', 'valor')->first();
$models = Model::all();

// Actualizar
$model->update([...]);

// Eliminar
$model->delete();

// Soft Delete
$model->trashed(); // bool
Model::withTrashed()->get();
Model::onlyTrashed()->get();
$model->restore();

// Relaciones
$model->relation; // get
$model->relation()->create([...]); // create related

// Eager Loading (evitar N+1)
Model::with(['relation1', 'relation2'])->get();

// Scopes
public function scopeActive($query)
{
    return $query->where('status', 'active');
}

Model::active()->get();
```

---

## 🔐 Roles y Permisos (Spatie)

```php
// Asignar rol
$user->assignRole('admin');
$user->assignRole(['admin', 'coordinator']);

// Remover rol
$user->removeRole('admin');

// Verificar rol
$user->hasRole('admin');
$user->hasAnyRole(['admin', 'coordinator']);

// Dar permiso
$user->givePermissionTo('edit voters');

// Verificar permiso
$user->can('edit voters');

// En Blade
@role('admin')
    // Content
@endrole

@can('edit voters')
    // Content
@endcan

// Middleware
Route::group(['middleware' => ['role:admin']], function () {
    //
});
```

---

## 🎨 Filament Patterns

### Form Components

```php
use Filament\Forms\Components\{TextInput, Select, DatePicker, Textarea};

TextInput::make('name')
    ->required()
    ->maxLength(255),

Select::make('status')
    ->options([
        'active' => 'Active',
        'inactive' => 'Inactive',
    ])
    ->required(),

Select::make('municipality_id')
    ->relationship('municipality', 'name')
    ->searchable()
    ->required(),

DatePicker::make('birth_date')
    ->native(false)
    ->required(),

Textarea::make('notes')
    ->rows(3),
```

### Table Columns

```php
use Filament\Tables\Columns\{TextColumn, BadgeColumn};

TextColumn::make('name')
    ->searchable()
    ->sortable(),

TextColumn::make('municipality.name')
    ->label('Municipality'),

BadgeColumn::make('status')
    ->colors([
        'success' => 'active',
        'danger' => 'inactive',
    ]),
```

### Filters

```php
use Filament\Tables\Filters\{SelectFilter, Filter};

SelectFilter::make('status')
    ->options([
        'active' => 'Active',
        'inactive' => 'Inactive',
    ]),

SelectFilter::make('municipality')
    ->relationship('municipality', 'name'),
```

### Actions

```php
use Filament\Tables\Actions\{Action, BulkAction};

Action::make('verify')
    ->action(fn (Voter $record) => $record->verify())
    ->requiresConfirmation(),

BulkAction::make('approve')
    ->action(fn (Collection $records) => $records->each->approve()),
```

---

## ⚡ Livewire/Volt Patterns

### Volt Class-based

```php
<?php

use Livewire\Volt\Component;
use function Livewire\Volt\{state};

new class extends Component {
    public $count = 0;

    public function increment()
    {
        $this->count++;
    }
} ?>

<div>
    <h1>{{ $count }}</h1>
    <button wire:click="increment">+</button>
</div>
```

### Volt Functional

```php
<?php

use function Livewire\Volt\{state, computed};

state(['search' => '']);

$voters = computed(fn() => Voter::where('name', 'like', "%{$this->search}%")->get());

?>

<div>
    <input wire:model.live="search" type="text">

    @foreach($this->voters as $voter)
        <div>{{ $voter->name }}</div>
    @endforeach
</div>
```

### Wire Directives

```blade
<input wire:model="name">                    {{-- Lazy (on change) --}}
<input wire:model.live="name">              {{-- Real-time --}}
<input wire:model.live.debounce.300ms="name"> {{-- Debounced --}}

<button wire:click="save">Save</button>

<div wire:loading>Loading...</div>
<div wire:loading.remove>Content</div>

<div wire:dirty>You have unsaved changes</div>
```

---

## 🔄 Git Workflow

```bash
# Crear rama feature
git checkout -b feature/nombre-corto

# Stage y commit
git add .
git commit -m "feat(scope): descripción"

# Push
git push origin feature/nombre-corto

# Merge a main
git checkout main
git merge feature/nombre-corto
git push origin main

# Borrar rama
git branch -d feature/nombre-corto
```

### Commits Semánticos

```bash
feat(voters): add census validation
fix(campaign): correct date validation
test(department): add CRUD tests
docs(readme): update installation steps
refactor(territory): simplify relationships
style(voters): format with pint
chore(deps): update filament to 4.2
```

---

## 📊 Artisan Útiles

```bash
# Cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Info
php artisan route:list
php artisan model:show NombreModelo

# Queue
php artisan queue:work
php artisan queue:retry all

# Storage
php artisan storage:link

# Optimize
php artisan optimize
php artisan optimize:clear
```

---

## 🔍 Debugging

```php
// dd (die and dump)
dd($variable);

// dump (sin morir)
dump($variable);

// Log
\Log::info('Message', ['data' => $data]);

// Query log
DB::enableQueryLog();
// ... queries
dd(DB::getQueryLog());

// Ray (si está instalado)
ray($variable);
```

---

## 📦 Composer Útiles

```bash
# Instalar package
composer require vendor/package

# Remover package
composer remove vendor/package

# Actualizar
composer update

# Dump autoload
composer dump-autoload
```

---

## 🎯 Checklist Pre-Commit

- [ ] Tests pasan: `php artisan test`
- [ ] Código formateado: `vendor/bin/pint --dirty`
- [ ] No hay dd() o dump() olvidados
- [ ] PROGRESO.md actualizado
- [ ] Commit semántico

---

## 🚀 Producción

```bash
# Optimizar
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Build assets
npm run build

# Permisos
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## 📱 URLs Útiles

- Frontend: http://localhost:8000
- Admin Panel: http://localhost:8000/admin
- Filament Docs: https://filamentphp.com/docs
- Laravel Docs: https://laravel.com/docs
- Pest Docs: https://pestphp.com/docs

---

**Última Actualización:** 2025-11-02
