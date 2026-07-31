@php
    use App\Services\CampaignContext;

    // Evita montar el componente Livewire por completo para no-super-admin:
    // wire:snapshot embebe el nombre del componente ("saldos-badge") en el
    // HTML sin importar si el contenido interno está vacío, lo que rompía la
    // aserción assertDontSee('saldos-badge') existente.
    if (! CampaignContext::isSuperAdmin()) {
        return;
    }
@endphp

<livewire:saldos-badge />
