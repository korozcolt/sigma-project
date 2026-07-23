<x-filament-panels::page>
    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        @if($this->getMaintenanceStatus())
            <div class="flex items-center gap-3 text-amber-600 dark:text-amber-400">
                <x-heroicon-o-exclamation-triangle class="h-6 w-6" />
                <span class="font-medium">La aplicación está actualmente EN MANTENIMIENTO.</span>
            </div>
        @else
            <div class="flex items-center gap-3 text-green-600 dark:text-green-400">
                <x-heroicon-o-check-circle class="h-6 w-6" />
                <span class="font-medium">La aplicación está operando normalmente.</span>
            </div>
        @endif
    </div>
</x-filament-panels::page>
