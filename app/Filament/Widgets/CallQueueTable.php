<?php

namespace App\Filament\Widgets;

use App\Enums\CallResult;
use App\Filament\Pages\CallCenter;
use App\Models\Voter;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CallQueueTable extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Voter::query()
                    ->whereDoesntHave('verificationCalls', function ($query) {
                        $query->whereIn('call_result', [
                            CallResult::ANSWERED->value,
                            CallResult::CONFIRMED->value,
                        ]);
                    })
                    ->orWhereHas('verificationCalls', function ($query) {
                        $query->whereIn('call_result', [
                            CallResult::NO_ANSWER->value,
                            CallResult::BUSY->value,
                            CallResult::CALLBACK_REQUESTED->value,
                        ])
                            ->where('attempt_number', '<', 3);
                    })
                    ->whereNotNull('phone')
                    ->with(['municipality', 'neighborhood', 'registeredBy', 'verificationCalls' => function ($query) {
                        $query->latest()->limit(1);
                    }])
                    ->orderByRaw('COALESCE((SELECT MAX(call_date) FROM verification_calls WHERE verification_calls.voter_id = voters.id), voters.created_at) ASC')
            )
            ->heading('Cola de Llamadas Pendientes')
            ->description('Votantes pendientes de verificación telefónica')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Votante')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->description(fn ($record) => $record->document_number),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-phone'),

                TextColumn::make('municipality.name')
                    ->label('Municipio')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('neighborhood.name')
                    ->label('Barrio')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('verificationCalls')
                    ->label('Último Intento')
                    ->getStateUsing(function ($record) {
                        $lastCall = $record->verificationCalls->first();
                        if (! $lastCall) {
                            return 'Sin llamadas previas';
                        }

                        return $lastCall->call_date->diffForHumans().' - '.$lastCall->call_result->getLabel();
                    })
                    ->badge()
                    ->color(function ($record) {
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
                Action::make('call')
                    ->label('Llamar')
                    ->icon('heroicon-m-phone')
                    ->color('primary')
                    ->url(fn ($record) => CallCenter::getUrl(['voter' => $record->id])),
            ])
            ->paginated([10, 25, 50]);
    }
}
