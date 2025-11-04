<?php

use App\Enums\CallResult;
use App\Models\CallAssignment;
use App\Models\Survey;
use App\Models\VerificationCall;
use App\Models\Voter;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component {
    public int $assignmentId = 0;

    #[Validate('required')]
    public ?CallResult $callResult = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $durationSeconds = null;

    #[Validate('nullable|string|max:1000')]
    public ?string $notes = null;

    #[Validate('nullable|integer|min:1')]
    public ?int $nextAttemptHours = null;

    public ?int $surveyId = null;

    public bool $showSurveyModal = false;

    public function mount(int $assignmentId): void
    {
        $this->assignmentId = $assignmentId;

        // Get active survey for the campaign
        $assignment = $this->assignment;
        $activeSurvey = Survey::where('campaign_id', $assignment->campaign_id)
            ->where('is_active', true)
            ->first();

        $this->surveyId = $activeSurvey?->id;
    }

    #[Computed]
    public function assignment(): CallAssignment
    {
        return CallAssignment::with(['voter.municipality', 'voter.neighborhood', 'campaign'])
            ->findOrFail($this->assignmentId);
    }

    #[Computed]
    public function voter(): Voter
    {
        return $this->assignment->voter;
    }

    #[Computed]
    public function previousCalls(): \Illuminate\Database\Eloquent\Collection
    {
        return VerificationCall::where('voter_id', $this->voter->id)
            ->with('caller')
            ->orderBy('called_at', 'desc')
            ->get();
    }

    #[Computed]
    public function callAttempts(): int
    {
        return $this->previousCalls->count();
    }

    #[Computed]
    public function canApplySurvey(): bool
    {
        return $this->callResult !== null
            && $this->callResult->isSuccessfulContact()
            && $this->surveyId !== null;
    }

    public function saveCall(): void
    {
        $this->validate();

        if ($this->callResult === null) {
            $this->addError('callResult', 'Debe seleccionar un resultado de llamada.');

            return;
        }

        DB::transaction(function () {
            // Create verification call record
            $call = VerificationCall::create([
                'voter_id' => $this->voter->id,
                'caller_id' => auth()->id(),
                'campaign_id' => $this->assignment->campaign_id,
                'assignment_id' => $this->assignmentId,
                'call_result' => $this->callResult,
                'duration_seconds' => $this->durationSeconds ?? 0,
                'notes' => $this->notes,
                'called_at' => now(),
                'attempt_number' => $this->callAttempts + 1,
            ]);

            // Update assignment status
            if ($this->callResult->isSuccessfulContact()) {
                $this->assignment->markCompleted();
            } elseif ($this->callResult->requiresFollowUp() && $this->nextAttemptHours) {
                $call->scheduleNextAttempt($this->nextAttemptHours);
            } elseif ($this->callResult->isInvalidNumber()) {
                $this->assignment->markCompleted();
            }
        });

        // Show survey modal if applicable
        if ($this->canApplySurvey) {
            $this->showSurveyModal = true;
        } else {
            session()->flash('message', 'Llamada registrada exitosamente.');
            $this->redirect('/calls/queue');
        }
    }

    public function applySurvey(): void
    {
        if (! $this->canApplySurvey) {
            return;
        }

        // Redirect to survey application
        $this->redirect("/surveys/{$this->surveyId}/apply?voter_id={$this->voter->id}");
    }

    public function skipSurvey(): void
    {
        session()->flash('message', 'Llamada registrada exitosamente.');
        $this->redirect('/calls/queue');
    }
}; ?>

<div class="max-w-4xl mx-auto py-8 px-4">
    <div class="mb-6">
        <flux:heading size="lg">Registrar Llamada</flux:heading>
        <flux:text>
            Asignación #{{ $assignmentId }} - {{ $this->voter->full_name }}
        </flux:text>
    </div>

    {{-- Voter Information Card --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <flux:heading size="md" class="mb-4">Información del Votante</flux:heading>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">Nombre Completo</flux:text>
                <flux:text class="font-semibold">{{ $this->voter->full_name }}</flux:text>
            </div>
            <div>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">Documento</flux:text>
                <flux:text class="font-semibold">{{ $this->voter->document_number }}</flux:text>
            </div>
            <div>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">Teléfono</flux:text>
                <flux:text class="font-semibold">{{ $this->voter->phone ?? 'No disponible' }}</flux:text>
            </div>
            <div>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">Email</flux:text>
                <flux:text class="font-semibold">{{ $this->voter->email ?? 'No disponible' }}</flux:text>
            </div>
            <div>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">Municipio</flux:text>
                <flux:text class="font-semibold">{{ $this->voter->municipality?->name ?? 'No asignado' }}</flux:text>
            </div>
            <div>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">Barrio</flux:text>
                <flux:text class="font-semibold">{{ $this->voter->neighborhood?->name ?? 'No asignado' }}</flux:text>
            </div>
            <div>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">Campaña</flux:text>
                <flux:text class="font-semibold">{{ $this->assignment->campaign->name }}</flux:text>
            </div>
            <div>
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">Intentos Previos</flux:text>
                <flux:text class="font-semibold">{{ $this->callAttempts }}</flux:text>
            </div>
        </div>
    </div>

    {{-- Previous Calls History --}}
    @if($this->callAttempts > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
            <flux:heading size="md" class="mb-4">Historial de Llamadas ({{ $this->callAttempts }})</flux:heading>

            <div class="space-y-3">
                @foreach($this->previousCalls as $prevCall)
                    <div class="border-l-4 pl-4 py-2" style="border-color: {{ $prevCall->call_result->getColor() }}">
                        <div class="flex justify-between items-start">
                            <div>
                                <flux:text class="font-semibold">
                                    {{ $prevCall->call_result->getLabel() }}
                                </flux:text>
                                <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ $prevCall->called_at->format('d/m/Y H:i') }} -
                                    {{ $prevCall->caller->name }} -
                                    Duración: {{ $prevCall->getFormattedDuration() }}
                                </flux:text>
                                @if($prevCall->notes)
                                    <flux:text class="text-sm mt-1">{{ $prevCall->notes }}</flux:text>
                                @endif
                            </div>
                            @if($prevCall->survey_completed)
                                <flux:badge variant="primary">Encuesta Completada</flux:badge>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Call Registration Form --}}
    <form wire:submit="saveCall" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <flux:heading size="md" class="mb-4">Registrar Nueva Llamada</flux:heading>

        <div class="space-y-4">
            {{-- Call Result --}}
            <flux:field>
                <flux:text>Resultado de la Llamada <span class="text-red-600">*</span></flux:text>
                <flux:select wire:model.live="callResult" placeholder="Seleccione el resultado">
                    @foreach(CallResult::cases() as $result)
                        <option value="{{ $result->value }}">{{ $result->getLabel() }}</option>
                    @endforeach
                </flux:select>
                @error('callResult')
                    <flux:text class="text-red-600 text-sm">{{ $message }}</flux:text>
                @enderror
            </flux:field>

            {{-- Duration --}}
            <flux:field>
                <flux:text>Duración (segundos)</flux:text>
                <flux:input type="number" wire:model="durationSeconds" min="0" placeholder="Ej: 120" />
                @error('durationSeconds')
                    <flux:text class="text-red-600 text-sm">{{ $message }}</flux:text>
                @enderror
            </flux:field>

            {{-- Notes --}}
            <flux:field>
                <flux:text>Notas</flux:text>
                <flux:textarea wire:model.live.debounce.500ms="notes" placeholder="Observaciones sobre la llamada..." rows="4" />
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                    {{ strlen($notes ?? '') }}/1000 caracteres
                </flux:text>
                @error('notes')
                    <flux:text class="text-red-600 text-sm">{{ $message }}</flux:text>
                @enderror
            </flux:field>

            {{-- Next Attempt Hours (only if requires follow-up) --}}
            @if($callResult && $callResult->requiresFollowUp())
                <flux:field>
                    <flux:text>Programar siguiente intento en (horas)</flux:text>
                    <flux:input type="number" wire:model="nextAttemptHours" min="1" placeholder="Ej: 24" />
                    <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                        Dejar vacío para no programar automáticamente
                    </flux:text>
                    @error('nextAttemptHours')
                        <flux:text class="text-red-600 text-sm">{{ $message }}</flux:text>
                    @enderror
                </flux:field>
            @endif
        </div>

        <div class="mt-6 flex gap-3">
            <flux:button type="submit" variant="primary">
                Guardar Llamada
            </flux:button>
            <flux:button type="button" variant="ghost" href="/calls/queue">
                Cancelar
            </flux:button>
        </div>
    </form>

    {{-- Survey Modal --}}
    @if($showSurveyModal)
        <flux:modal wire:model="showSurveyModal">
            <div class="p-6">
                <flux:heading size="lg" class="mb-4">Aplicar Encuesta</flux:heading>
                <flux:text class="mb-6">
                    La llamada fue exitosa. ¿Desea aplicar la encuesta al votante ahora?
                </flux:text>

                <div class="flex gap-3">
                    <flux:button wire:click="applySurvey" variant="primary">
                        Sí, Aplicar Encuesta
                    </flux:button>
                    <flux:button wire:click="skipSurvey" variant="ghost">
                        No, Continuar sin Encuesta
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
