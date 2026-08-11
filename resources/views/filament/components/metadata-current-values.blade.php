{{-- D-09: solo el valor vigente por llave + quién lo asignó y cuándo. Sin historial. --}}
<div class="space-y-2">
    @forelse ($currentValues as $value)
        <div class="flex items-center justify-between gap-3">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ $value->metadataKey?->label }}
            </span>
            <div class="flex items-center gap-2">
                <x-filament::badge>{{ $value->value }}</x-filament::badge>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $value->assignedByUser?->name ?? 'Sistema' }} ·
                    {{ $value->assigned_at?->format('d/m/Y H:i') }}
                </span>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">Sin metadata asignada.</p>
    @endforelse
</div>
