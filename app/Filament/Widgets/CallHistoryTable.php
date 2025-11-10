<?php

namespace App\Filament\Widgets;

use App\Models\VerificationCall;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

class CallHistoryTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                VerificationCall::query()
                    ->where('caller_id', Auth::id())
                    ->with(['voter', 'survey'])
                    ->latest('call_date')
            )
            ->heading('Historial de Llamadas')
            ->description('Últimas llamadas realizadas')
            ->columns([
                TextColumn::make('voter.full_name')
                    ->label('Votante')
                    ->searchable(['first_name', 'last_name'])
                    ->description(fn ($record) => $record->voter->document_number),

                TextColumn::make('voter.phone')
                    ->label('Teléfono')
                    ->icon('heroicon-m-phone')
                    ->copyable(),

                TextColumn::make('call_result')
                    ->label('Resultado')
                    ->badge(),

                IconColumn::make('survey_completed')
                    ->label('Encuesta')
                    ->boolean()
                    ->trueIcon('heroicon-m-check-circle')
                    ->falseIcon('heroicon-m-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('call_duration')
                    ->label('Duración')
                    ->formatStateUsing(fn ($state) => gmdate('i:s', (int) $state))
                    ->icon('heroicon-m-clock'),

                TextColumn::make('attempt_number')
                    ->label('Intento')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => "#$state"),

                TextColumn::make('call_date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn ($record) => $record->call_date->diffForHumans()),

                TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->notes)
                    ->toggleable(),
            ])
            ->defaultSort('call_date', 'desc')
            ->paginated([10, 25]);
    }
}
