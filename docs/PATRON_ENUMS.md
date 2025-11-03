# 🎨 Patrón de Enums para SIGMA

**Estándar de Filament para Enums con Interfaces**

Este documento define el patrón estándar para todos los enums en el proyecto SIGMA, aprovechando las interfaces de Filament para mejorar la experiencia de desarrollo y UI.

---

## 🎯 Interfaces de Filament

Filament proporciona 4 interfaces principales para enums:

| Interface | Propósito | Método Requerido |
|-----------|-----------|------------------|
| `HasLabel` | Label legible para UI | `getLabel(): ?string` |
| `HasColor` | Color para badges y UI | `getColor(): string\|array\|null` |
| `HasIcon` | Icono para UI | `getIcon(): ?string` |
| `HasDescription` | Descripción detallada | `getDescription(): ?string` |

---

## 📋 Template Base para Enums

### Enum Básico (Solo Label)

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NombreEnum: string implements HasLabel
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
}
```

### Enum Completo (Label + Color + Icon)

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

### Enum con Descripción

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum NombreEnum: string implements HasLabel, HasColor, HasIcon, HasDescription
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

    public function getDescription(): ?string
    {
        return match ($this) {
            self::OPCION_UNO => 'Esta es una descripción detallada de la opción uno.',
            self::OPCION_DOS => 'Esta es una descripción detallada de la opción dos.',
        };
    }
}
```

---

## 🎨 Colores Disponibles

Filament provee estos colores estándar:

```php
'gray'      // Gris
'primary'   // Color primario del tema
'secondary' // Color secundario
'success'   // Verde
'warning'   // Amarillo/Naranja
'danger'    // Rojo
'info'      // Azul
```

También puedes usar colores personalizados de Tailwind:

```php
'slate'
'zinc'
'neutral'
'stone'
'red'
'orange'
'amber'
'yellow'
'lime'
'green'
'emerald'
'teal'
'cyan'
'sky'
'blue'
'indigo'
'violet'
'purple'
'fuchsia'
'pink'
'rose'
```

---

## 🎯 Iconos Disponibles

Filament usa Heroicons por defecto. Formato: `heroicon-{style}-{nombre}`

### Estilos:
- `o` - outline (línea)
- `s` - solid (sólido)
- `m` - mini (pequeño)

### Ejemplos:

```php
'heroicon-m-check'                    // Check
'heroicon-m-x-mark'                   // X
'heroicon-m-pencil'                   // Lápiz
'heroicon-m-eye'                      // Ojo
'heroicon-m-trash'                    // Basura
'heroicon-m-exclamation-triangle'     // Advertencia
'heroicon-m-information-circle'       // Info
'heroicon-m-user'                     // Usuario
'heroicon-m-users'                    // Usuarios
'heroicon-m-document'                 // Documento
'heroicon-m-phone'                    // Teléfono
'heroicon-m-envelope'                 // Correo
'heroicon-m-map-pin'                  // Ubicación
'heroicon-m-calendar'                 // Calendario
'heroicon-m-clock'                    // Reloj
'heroicon-m-chart-bar'                // Gráfica
```

Ver lista completa: https://heroicons.com/

---

## 💻 Uso en Filament

### En Form Fields

```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ToggleButtons;

// Select simple
Select::make('status')
    ->options(Status::class)
    ->required()

// Radio buttons con descripciones
Radio::make('status')
    ->options(Status::class)
    ->required()

// Toggle buttons con colores e iconos
ToggleButtons::make('status')
    ->options(Status::class)
    ->inline()
    ->required()

// CheckboxList
CheckboxList::make('permissions')
    ->options(Permission::class)
    ->columns(2)
```

### En Table Columns

```php
use Filament\Tables\Columns\TextColumn;

// Automáticamente usa label, color e icono
TextColumn::make('status')
    ->badge()  // Muestra como badge con color
    ->sortable()

// Sin badge (solo texto con color)
TextColumn::make('status')
    ->sortable()
```

### En Table Filters

```php
use Filament\Tables\Filters\SelectFilter;

SelectFilter::make('status')
    ->options(Status::class)
    ->multiple()
```

### En Infolist Entries

```php
use Filament\Infolists\Components\TextEntry;

TextEntry::make('status')
    ->badge()  // Muestra como badge
```

---

## 📝 Convenciones SIGMA

### Nombres de Cases

Usar **SCREAMING_SNAKE_CASE** para los cases:

```php
✅ CORRECTO:
case PENDING_REVIEW = 'pending_review';
case VERIFIED_CENSUS = 'verified_census';

❌ INCORRECTO:
case pendingReview = 'pending_review';
case PendingReview = 'pending_review';
```

### Valores de Cases

Usar **snake_case** para los valores:

```php
✅ CORRECTO:
case PENDING_REVIEW = 'pending_review';

❌ INCORRECTO:
case PENDING_REVIEW = 'PendingReview';
case PENDING_REVIEW = 'pending-review';
```

### Ubicación

Todos los enums en `app/Enums/`:

```
app/
├── Enums/
│   ├── VoterStatus.php
│   ├── CampaignStatus.php
│   ├── UserRole.php
│   ├── CallResult.php
│   ├── MessageChannel.php
│   └── ...
```

### Cast en Models

Siempre castear enums en modelos Eloquent:

```php
use App\Enums\VoterStatus;

class Voter extends Model
{
    protected function casts(): array
    {
        return [
            'status' => VoterStatus::class,
        ];
    }
}
```

---

## 🎨 Ejemplos Completos para SIGMA

### VoterStatus (Estado de Votante)

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum VoterStatus: string implements HasLabel, HasColor, HasIcon
{
    case PENDING_REVIEW = 'pending_review';
    case REJECTED_CENSUS = 'rejected_census';
    case VERIFIED_CENSUS = 'verified_census';
    case CORRECTION_REQUIRED = 'correction_required';
    case VERIFIED_CALL = 'verified_call';
    case CONFIRMED = 'confirmed';
    case VOTED = 'voted';
    case DID_NOT_VOTE = 'did_not_vote';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'Pendiente de Revisión',
            self::REJECTED_CENSUS => 'Rechazado por Censo',
            self::VERIFIED_CENSUS => 'Verificado por Censo',
            self::CORRECTION_REQUIRED => 'Requiere Corrección',
            self::VERIFIED_CALL => 'Verificado por Llamada',
            self::CONFIRMED => 'Confirmado',
            self::VOTED => 'Votó',
            self::DID_NOT_VOTE => 'No Votó',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING_REVIEW => 'gray',
            self::REJECTED_CENSUS => 'danger',
            self::VERIFIED_CENSUS => 'info',
            self::CORRECTION_REQUIRED => 'warning',
            self::VERIFIED_CALL => 'primary',
            self::CONFIRMED => 'success',
            self::VOTED => 'success',
            self::DID_NOT_VOTE => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'heroicon-m-clock',
            self::REJECTED_CENSUS => 'heroicon-m-x-circle',
            self::VERIFIED_CENSUS => 'heroicon-m-check-badge',
            self::CORRECTION_REQUIRED => 'heroicon-m-exclamation-triangle',
            self::VERIFIED_CALL => 'heroicon-m-phone',
            self::CONFIRMED => 'heroicon-m-check-circle',
            self::VOTED => 'heroicon-m-check',
            self::DID_NOT_VOTE => 'heroicon-m-x-mark',
        };
    }
}
```

### CampaignStatus (Estado de Campaña)

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CampaignStatus: string implements HasLabel, HasColor, HasIcon, HasDescription
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::ACTIVE => 'Activa',
            self::PAUSED => 'Pausada',
            self::COMPLETED => 'Completada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ACTIVE => 'success',
            self::PAUSED => 'warning',
            self::COMPLETED => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-m-pencil',
            self::ACTIVE => 'heroicon-m-play',
            self::PAUSED => 'heroicon-m-pause',
            self::COMPLETED => 'heroicon-m-check-circle',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::DRAFT => 'La campaña está en borrador y no es visible para usuarios.',
            self::ACTIVE => 'La campaña está activa y los usuarios pueden trabajar en ella.',
            self::PAUSED => 'La campaña está temporalmente pausada.',
            self::COMPLETED => 'La campaña ha finalizado y está archivada.',
        };
    }
}
```

### UserRole (Roles de Usuario)

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel, HasColor, HasIcon, HasDescription
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN_CAMPAIGN = 'admin_campaign';
    case COORDINATOR = 'coordinator';
    case LEADER = 'leader';
    case REVIEWER = 'reviewer';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrador',
            self::ADMIN_CAMPAIGN => 'Administrador de Campaña',
            self::COORDINATOR => 'Coordinador',
            self::LEADER => 'Líder',
            self::REVIEWER => 'Revisor',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SUPER_ADMIN => 'danger',
            self::ADMIN_CAMPAIGN => 'warning',
            self::COORDINATOR => 'primary',
            self::LEADER => 'success',
            self::REVIEWER => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'heroicon-m-shield-check',
            self::ADMIN_CAMPAIGN => 'heroicon-m-user-circle',
            self::COORDINATOR => 'heroicon-m-users',
            self::LEADER => 'heroicon-m-user',
            self::REVIEWER => 'heroicon-m-eye',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Acceso completo al sistema y gestión de todas las campañas.',
            self::ADMIN_CAMPAIGN => 'Administra una campaña específica y su equipo.',
            self::COORDINATOR => 'Coordina líderes en un territorio específico.',
            self::LEADER => 'Registra y gestiona votantes en su zona.',
            self::REVIEWER => 'Valida votantes y realiza llamadas de verificación.',
        };
    }
}
```

---

## ✅ Beneficios

### Para Desarrolladores

- ✅ **Type-safe:** PHP garantiza que solo uses valores válidos
- ✅ **DRY:** No repites labels y colores en múltiples lugares
- ✅ **IDE Support:** Autocomplete funciona perfectamente
- ✅ **Refactoring:** Fácil cambiar labels/colores en un solo lugar
- ✅ **Mantenible:** Toda la lógica de presentación en un lugar

### Para UI/UX

- ✅ **Consistencia:** Mismo color e icono en toda la app
- ✅ **Visual:** Badges coloridos mejoran legibilidad
- ✅ **Accesibilidad:** Iconos ayudan a identificar estados
- ✅ **Profesional:** UI más pulida y coherente

### Para Testing

- ✅ **Fácil de testear:** Valores constantes y predecibles
- ✅ **Assertions claras:** `expect($voter->status)->toBe(VoterStatus::CONFIRMED)`

---

## 🚫 Anti-Patrones (Evitar)

### ❌ No usar strings mágicos

```php
// MAL
$voter->status = 'confirmed';

// BIEN
$voter->status = VoterStatus::CONFIRMED;
```

### ❌ No duplicar labels en código

```php
// MAL - Labels en el Resource
Select::make('status')
    ->options([
        'confirmed' => 'Confirmado',
        'pending' => 'Pendiente',
    ])

// BIEN - Labels en el Enum
Select::make('status')
    ->options(VoterStatus::class)
```

### ❌ No mezclar estilos de naming

```php
// MAL
enum Status: string {
    case Confirmed = 'confirmed';  // PascalCase
    case pending_review = 'pending'; // snake_case
}

// BIEN
enum Status: string {
    case CONFIRMED = 'confirmed';
    case PENDING_REVIEW = 'pending_review';
}
```

---

## 📚 Referencias

- [Filament Enum Tricks](https://filamentphp.com/docs/4.x/support/enums)
- [PHP Enums Documentation](https://www.php.net/manual/en/language.enumerations.php)
- [Heroicons](https://heroicons.com/)

---

**Última Actualización:** 2025-11-02
