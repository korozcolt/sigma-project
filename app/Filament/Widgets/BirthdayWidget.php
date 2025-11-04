<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Voter;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class BirthdayWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Voter::query()
                    ->whereMonth('birth_date', now()->month)
                    ->whereYear('birth_date', '<=', now()->year)
                    ->orderByRaw('DAY(birth_date) ASC')
            )
            ->heading('🎂 Cumpleaños del Mes')
            ->description('Votantes que cumplen años este mes')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('Documento')
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('age')
                    ->label('Edad')
                    ->getStateUsing(function ($record) {
                        $birthDate = \Carbon\Carbon::parse($record->birth_date);
                        $thisYearBirthday = $birthDate->copy()->year(now()->year);

                        return $thisYearBirthday->isPast() || $thisYearBirthday->isToday()
                            ? $birthDate->age + 1 .' años'
                            : $birthDate->age.' años';
                    }),

                Tables\Columns\TextColumn::make('days_until_birthday')
                    ->label('Días')
                    ->getStateUsing(function ($record) {
                        $birthDate = \Carbon\Carbon::parse($record->birth_date);
                        $thisYearBirthday = $birthDate->copy()->year(now()->year);

                        if ($thisYearBirthday->isPast()) {
                            return '¡Ya pasó!';
                        }

                        if ($thisYearBirthday->isToday()) {
                            return '🎉 ¡Hoy!';
                        }

                        $daysUntil = $thisYearBirthday->diffInDays(now());

                        return "En {$daysUntil} días";
                    })
                    ->badge()
                    ->color(fn ($record) => \Carbon\Carbon::parse($record->birth_date)->copy()->year(now()->year)->isToday() ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('municipality.name')
                    ->label('Municipio')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('neighborhood.name')
                    ->label('Barrio')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('send_message')
                    ->label('Enviar Mensaje')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->color('success')
                    ->url(fn ($record) => route('filament.admin.resources.messages.create', [
                        'voter_id' => $record->id,
                        'type' => 'birthday',
                    ])),
            ])
            ->defaultSort('birth_date', 'asc')
            ->poll('30s');
    }
}
