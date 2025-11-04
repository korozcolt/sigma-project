<?php

use App\Enums\QuestionType;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\Voter;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public int $surveyId = 0;

    public ?int $voterId = null;

    public int $currentQuestionIndex = 0;

    public array $responses = [];

    public bool $completed = false;

    public function mount(int $surveyId, ?int $voterId = null): void
    {
        $this->surveyId = $surveyId;
        $this->voterId = $voterId;

        // Initialize responses array for all questions
        foreach ($this->survey->questions as $index => $question) {
            $this->responses[$index] = null;
        }
    }

    #[Computed]
    public function survey(): Survey
    {
        return Survey::with(['questions' => fn ($query) => $query->orderBy('order')])
            ->findOrFail($this->surveyId);
    }

    #[Computed]
    public function voter(): ?Voter
    {
        return $this->voterId ? Voter::find($this->voterId) : null;
    }

    #[Computed]
    public function currentQuestion(): ?SurveyQuestion
    {
        return $this->survey->questions->get($this->currentQuestionIndex);
    }

    #[Computed]
    public function progress(): float
    {
        $total = $this->survey->questions->count();

        return $total > 0 ? (($this->currentQuestionIndex + 1) / $total) * 100 : 0;
    }

    #[Computed]
    public function canGoNext(): bool
    {
        // Check if current question is required and has response
        $current = $this->currentQuestion;
        if (! $current) {
            return false;
        }

        if ($current->is_required && empty($this->responses[$this->currentQuestionIndex])) {
            return false;
        }

        return $this->currentQuestionIndex < $this->survey->questions->count() - 1;
    }

    #[Computed]
    public function canGoPrevious(): bool
    {
        return $this->currentQuestionIndex > 0;
    }

    #[Computed]
    public function canSubmit(): bool
    {
        // Check if all required questions have responses
        foreach ($this->survey->questions as $index => $question) {
            if ($question->is_required && empty($this->responses[$index])) {
                return false;
            }
        }

        return $this->currentQuestionIndex === $this->survey->questions->count() - 1;
    }

    public function nextQuestion(): void
    {
        if ($this->canGoNext) {
            $this->currentQuestionIndex++;
        }
    }

    public function previousQuestion(): void
    {
        if ($this->canGoPrevious) {
            $this->currentQuestionIndex--;
        }
    }

    public function submit(): void
    {
        // Validate all required questions
        $this->validate([
            'responses.*' => 'nullable',
        ]);

        foreach ($this->survey->questions as $index => $question) {
            if ($question->is_required && empty($this->responses[$index])) {
                $this->addError("responses.{$index}", 'Esta pregunta es obligatoria.');

                return;
            }
        }

        // Save responses
        foreach ($this->survey->questions as $index => $question) {
            if (! empty($this->responses[$index])) {
                SurveyResponse::create([
                    'survey_id' => $this->survey->id,
                    'survey_question_id' => $question->id,
                    'voter_id' => $this->voterId,
                    'response_value' => is_array($this->responses[$index])
                        ? json_encode($this->responses[$index])
                        : $this->responses[$index],
                    'answered_by' => auth()->id(),
                    'responded_at' => now(),
                ]);
            }
        }

        $this->completed = true;
    }
}; ?>

<div class="max-w-3xl mx-auto py-8 px-4">
    @if($completed)
        <!-- Completed State -->
        <div class="text-center py-12">
            <flux:icon.check-circle class="w-16 h-16 mx-auto text-green-500 mb-4" />
            <flux:heading size="lg" class="mb-2">¡Encuesta completada!</flux:heading>
            <flux:text>Gracias por tu tiempo. Tus respuestas han sido guardadas exitosamente.</flux:text>
        </div>
    @else
        <!-- Survey Header -->
        <div class="mb-8">
            <flux:heading size="xl" class="mb-2">{{ $this->survey->title }}</flux:heading>
            @if($this->survey->description)
                <flux:text class="text-zinc-500">{{ $this->survey->description }}</flux:text>
            @endif

            <!-- Progress Bar -->
            <div class="mt-6">
                <div class="flex justify-between items-center mb-2">
                    <flux:text size="sm" class="text-zinc-500">
                        Pregunta {{ $currentQuestionIndex + 1 }} de {{ $this->survey->questions->count() }}
                    </flux:text>
                    <flux:text size="sm" class="text-zinc-500">{{ $this->progress }}%</flux:text>
                </div>
                <div class="w-full bg-zinc-200 rounded-full h-2">
                    <div class="bg-blue-500 h-2 rounded-full transition-all duration-300"
                         style="width: {{ $this->progress }}%"></div>
                </div>
            </div>
        </div>

        @if($this->currentQuestion)
            <!-- Question Card -->
            <div class="bg-white dark:bg-zinc-800 rounded-lg shadow-sm p-6 mb-6">
                <div class="mb-6">
                    <flux:heading size="lg" class="mb-2">
                        {{ $this->currentQuestion->question_text }}
                        @if($this->currentQuestion->is_required)
                            <span class="text-red-500">*</span>
                        @endif
                    </flux:heading>
                    @if($this->currentQuestion->description)
                        <flux:text size="sm" class="text-zinc-500">{{ $this->currentQuestion->description }}</flux:text>
                    @endif
                </div>

                <!-- Question Type Based Input -->
                @switch($this->currentQuestion->question_type)
                    @case(QuestionType::YES_NO)
                        <div class="space-y-3">
                            <flux:radio wire:model.live="responses.{{ $currentQuestionIndex }}"
                                       name="question_{{ $currentQuestionIndex }}"
                                       value="Sí"
                                       label="Sí" />
                            <flux:radio wire:model.live="responses.{{ $currentQuestionIndex }}"
                                       name="question_{{ $currentQuestionIndex }}"
                                       value="No"
                                       label="No" />
                        </div>
                        @break

                    @case(QuestionType::SCALE)
                        @php
                            $config = $this->currentQuestion->configuration ?? [];
                            $min = $config['min'] ?? 1;
                            $max = $config['max'] ?? 5;
                        @endphp
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <flux:text size="sm">{{ $min }}</flux:text>
                                <flux:text size="sm">{{ $max }}</flux:text>
                            </div>
                            <div class="flex gap-2">
                                @for($i = $min; $i <= $max; $i++)
                                    <button type="button"
                                            wire:click="$set('responses.{{ $currentQuestionIndex }}', '{{ $i }}')"
                                            class="flex-1 py-3 px-4 rounded-lg border-2 transition-all
                                                   {{ $this->responses[$currentQuestionIndex] == $i
                                                      ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                                      : 'border-zinc-300 dark:border-zinc-600 hover:border-blue-300' }}">
                                        <flux:text class="font-semibold">{{ $i }}</flux:text>
                                    </button>
                                @endfor
                            </div>
                        </div>
                        @break

                    @case(QuestionType::SINGLE_CHOICE)
                        @php
                            $options = $this->currentQuestion->configuration['options'] ?? [];
                        @endphp
                        <div class="space-y-3">
                            @foreach($options as $option)
                                <flux:radio wire:model.live="responses.{{ $currentQuestionIndex }}"
                                           name="question_{{ $currentQuestionIndex }}"
                                           value="{{ $option }}"
                                           label="{{ $option }}" />
                            @endforeach
                        </div>
                        @break

                    @case(QuestionType::MULTIPLE_CHOICE)
                        @php
                            $options = $this->currentQuestion->configuration['options'] ?? [];
                        @endphp
                        <div class="space-y-3">
                            @foreach($options as $index => $option)
                                <flux:checkbox wire:model.live="responses.{{ $currentQuestionIndex }}.{{ $index }}"
                                              value="{{ $option }}"
                                              label="{{ $option }}" />
                            @endforeach
                        </div>
                        @break

                    @case(QuestionType::TEXT)
                        <flux:textarea wire:model.live="responses.{{ $currentQuestionIndex }}"
                                      rows="4"
                                      placeholder="Escribe tu respuesta aquí..." />
                        @break
                @endswitch

                @error("responses.{$currentQuestionIndex}")
                    <flux:text size="sm" class="text-red-500 mt-2">{{ $message }}</flux:text>
                @enderror
            </div>

            <!-- Navigation Buttons -->
            <div class="flex justify-between items-center">
                <flux:button variant="ghost"
                            wire:click="previousQuestion"
                            :disabled="!$this->canGoPrevious"
                            icon="arrow-left">
                    Anterior
                </flux:button>

                @if($this->canSubmit)
                    <flux:button variant="primary"
                                wire:click="submit"
                                icon-trailing="check">
                        Enviar encuesta
                    </flux:button>
                @else
                    <flux:button variant="primary"
                                wire:click="nextQuestion"
                                :disabled="!$this->canGoNext"
                                icon-trailing="arrow-right">
                        Siguiente
                    </flux:button>
                @endif
            </div>
        @endif
    @endif
</div>
