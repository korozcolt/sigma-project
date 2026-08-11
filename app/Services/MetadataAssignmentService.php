<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\MetadataKey;
use App\Models\User;
use App\Models\UserMetadataValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class MetadataAssignmentService
{
    /**
     * @return Collection<int, MetadataKey>
     */
    public function activeKeys(): Collection
    {
        return MetadataKey::query()
            ->where('is_active', true)
            ->orderBy('label')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function activeKeyOptions(): array
    {
        return $this->activeKeys()->pluck('label', 'id')->all();
    }

    public function findActiveKey(int|string|null $keyId): ?MetadataKey
    {
        if (blank($keyId)) {
            return null;
        }

        return MetadataKey::query()->whereKey($keyId)->where('is_active', true)->first();
    }

    // D-03: subordinados DIRECTOS, no el equipo transitivo resuelto en Fase 13 para AUTHZ-01.
    public function directSubordinatesQuery(User $superior): Builder
    {
        return match (true) {
            $superior->hasAnyRole([UserRole::SUPER_ADMIN->value, UserRole::ADMIN_CAMPAIGN->value]) => User::query(),
            $superior->hasRole(UserRole::AREA_COORDINATOR->value) => User::query()->where('area_coordinator_user_id', $superior->id),
            $superior->hasRole(UserRole::COORDINATOR->value) => User::query()->where('coordinator_user_id', $superior->id),
            default => User::query()->whereRaw('1 = 0'),
        };
    }

    public function canAssignTo(User $superior, User $subject): bool
    {
        return $this->directSubordinatesQuery($superior)->whereKey($subject->getKey())->exists();
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, User>
     */
    public function subordinatesByIds(User $superior, array $ids): Collection
    {
        return $this->directSubordinatesQuery($superior)->whereIn('id', $ids)->get();
    }

    public function validateValue(MetadataKey $key, string $value): void
    {
        if ($value === '') {
            throw new InvalidArgumentException('El valor no puede estar vacío.');
        }

        if ($key->type === 'select' && ! in_array($value, $key->options ?? [], true)) {
            throw new InvalidArgumentException('El valor no pertenece a las opciones de la llave.');
        }

        if ($key->type === 'numeric' && ! is_numeric($value)) {
            throw new InvalidArgumentException('El valor debe ser numérico.');
        }

        if ($key->type === 'date') {
            try {
                Carbon::parse($value);
            } catch (\Throwable) {
                throw new InvalidArgumentException('El valor debe ser una fecha válida.');
            }
        }
    }

    // META-06: única ruta de escritura. INSERT puro, nunca una actualización de fila existente.
    public function assign(User $subject, MetadataKey $key, string $value, User $assignedBy): UserMetadataValue
    {
        if (! $key->is_active) {
            throw new InvalidArgumentException('La llave de metadata no está activa.');
        }

        $this->validateValue($key, $value);

        return UserMetadataValue::create([
            'user_id' => $subject->id,
            'metadata_key_id' => $key->id,
            'value' => $value,
            'assigned_by' => $assignedBy->id,
            'assigned_at' => now(),
        ]);
    }

    /**
     * @param  iterable<int, User>  $subjects
     */
    public function assignMany(iterable $subjects, MetadataKey $key, string $value, User $assignedBy): int
    {
        $count = 0;

        foreach ($subjects as $subject) {
            $this->assign($subject, $key, $value, $assignedBy);
            $count++;
        }

        return $count;
    }

    /**
     * @return Collection<int, UserMetadataValue>
     */
    public function currentValues(User $subject): Collection
    {
        // D-09 + META-06: assigned_at tiene precisión de segundos; id DESC es el desempate determinista.
        return UserMetadataValue::query()
            ->with(['metadataKey', 'assignedByUser'])
            ->where('user_id', $subject->id)
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->get()
            ->unique('metadata_key_id')
            ->keyBy('metadata_key_id');
    }

    public function currentValueFor(User $subject, MetadataKey $key): ?UserMetadataValue
    {
        return UserMetadataValue::query()
            ->with(['metadataKey', 'assignedByUser'])
            ->where('user_id', $subject->id)
            ->where('metadata_key_id', $key->id)
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->first();
    }
}
