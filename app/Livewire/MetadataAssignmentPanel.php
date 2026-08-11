<?php

namespace App\Livewire;

use App\Models\MetadataKey;
use App\Models\User;
use App\Services\MetadataAssignmentService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Component;

class MetadataAssignmentPanel extends Component
{
    public User $user;

    public ?int $metadataKeyId = null;

    public string $value = '';

    public ?string $successMessage = null;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function getCanAssignProperty(): bool
    {
        return app(MetadataAssignmentService::class)->canAssignTo(auth()->user(), $this->user);
    }

    /**
     * @return Collection<int, MetadataKey>
     */
    public function getKeysProperty(): Collection
    {
        return app(MetadataAssignmentService::class)->activeKeys();
    }

    public function getSelectedKeyProperty(): ?MetadataKey
    {
        return app(MetadataAssignmentService::class)->findActiveKey($this->metadataKeyId);
    }

    public function getCurrentValuesProperty(): Collection
    {
        return app(MetadataAssignmentService::class)->currentValues($this->user);
    }

    public function updatedMetadataKeyId(): void
    {
        $this->value = '';
        $this->resetErrorBag('value');
    }

    public function assign(): void
    {
        $service = app(MetadataAssignmentService::class);
        $actor = auth()->user();

        // El id del usuario llega hidratado desde el cliente: se revalida la propiedad
        // en cada escritura, no solo en mount().
        abort_unless($service->canAssignTo($actor, $this->user), 403);

        $this->validate([
            'metadataKeyId' => ['required', 'integer', 'exists:metadata_keys,id'],
            'value' => ['required', 'string', 'max:255'],
        ], attributes: [
            'metadataKeyId' => 'llave',
            'value' => 'valor',
        ]);

        $key = $service->findActiveKey($this->metadataKeyId);

        if (! $key) {
            $this->addError('metadataKeyId', 'La llave seleccionada no está disponible.');

            return;
        }

        try {
            $service->assign($this->user, $key, $this->value, $actor);
        } catch (InvalidArgumentException $e) {
            $this->addError('value', $e->getMessage());

            return;
        }

        $this->reset(['metadataKeyId', 'value']);
        $this->successMessage = 'Metadata asignada correctamente.';
    }

    public function render(): View
    {
        return view('livewire.metadata-assignment-panel');
    }
}
