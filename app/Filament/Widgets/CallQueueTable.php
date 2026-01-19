<?php

namespace App\Filament\Widgets;

use App\Enums\CallResult;
use App\Models\CallAssignment;
use App\Models\Survey;
use App\Models\VerificationCall;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CallQueueTable extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $userId = Auth::id();

        return $table
            ->query(
                CallAssignment::query()
                    ->with([
                        'voter.municipality',
                        'voter.neighborhood',
                        'verificationCalls' => fn (Builder $query) => $query->latest('call_date')->limit(1),
                    ])
                    ->when($userId, fn (Builder $query) => $query->forCaller($userId))
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->orderedByPriority()
                    ->oldest('assigned_at')
            )
            ->heading('Cola de Llamadas Pendientes')
            ->description('Votantes asignados a tu cola (usa "Cargar 5" para asignar)')
            ->columns([
                TextColumn::make('voter.full_name')
                    ->label('Votante')
                    ->searchable(['voter.first_name', 'voter.last_name'])
                    ->sortable()
                    ->description(fn (CallAssignment $record) => $record->voter?->document_number),

                TextColumn::make('voter.phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-phone'),

                TextColumn::make('voter.municipality.name')
                    ->label('Municipio')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('voter.neighborhood.name')
                    ->label('Barrio')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('last_attempt')
                    ->label('Último Intento')
                    ->getStateUsing(function (CallAssignment $record): string {
                        $lastCall = $record->verificationCalls->first();
                        if (! $lastCall) {
                            return 'Sin llamadas previas';
                        }

                        return $lastCall->call_date->diffForHumans().' - '.$lastCall->call_result->getLabel();
                    })
                    ->badge()
                    ->color(function (CallAssignment $record): string {
                        $lastCall = $record->verificationCalls->first();
                        if (! $lastCall) {
                            return 'gray';
                        }

                        return match ($lastCall->call_result->value) {
                            'NO_ANSWER', 'BUSY' => 'warning',
                            'CALLBACK_REQUESTED' => 'info',
                            default => 'gray'
                        };
                    }),
            ])
            ->recordActions([
                Action::make('register_call')
                    ->label('Registrar llamada')
                    ->icon('heroicon-m-phone')
                    ->color('primary')
                    ->modalHeading('Registrar llamada')
                    ->form([
                        Select::make('call_result')
                            ->label('Resultado')
                            ->options(CallResult::class)
                            ->required(),
                        TextInput::make('call_duration')
                            ->label('Duración (segundos)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->maxLength(1000),
                        Select::make('survey_id')
                            ->label('Encuesta (opcional)')
                            ->options(fn (CallAssignment $record) => Survey::query()
                                ->where('campaign_id', $record->campaign_id)
                                ->orderBy('title')
                                ->pluck('title', 'id'))
                            ->searchable()
                            ->preload(),
                    ])
                    ->action(function (CallAssignment $record, array $data): void {
                        $record->markInProgress();

                        $attemptNumber = VerificationCall::query()
                            ->where('voter_id', $record->voter_id)
                            ->count() + 1;

                        $call = VerificationCall::create([
                            'voter_id' => $record->voter_id,
                            'assignment_id' => $record->id,
                            'caller_id' => Auth::id(),
                            'attempt_number' => $attemptNumber,
                            'call_date' => now(),
                            'call_duration' => (int) $data['call_duration'],
                            'call_result' => $data['call_result'],
                            'notes' => $data['notes'] ?? null,
                            'survey_id' => $data['survey_id'] ?? null,
                            'survey_completed' => false,
                        ]);

                        $result = CallResult::from($data['call_result']);

                        if ($result->isInvalidNumber() || $result === CallResult::CONFIRMED || $result === CallResult::ANSWERED) {
                            $record->markCompleted();
                        } else {
                            $record->update(['status' => 'pending']);
                        }

                        if (
                            ! empty($data['survey_id'])
                            && $result->isSuccessfulContact()
                        ) {
                            redirect()->route('surveys.apply', [
                                'surveyId' => $data['survey_id'],
                                'voter_id' => $record->voter_id,
                                'call_id' => $call->id,
                            ])->send();

                            return;
                        }

                        Notification::make()
                            ->title('Llamada registrada')
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated([10, 25, 50]);
    }
}
