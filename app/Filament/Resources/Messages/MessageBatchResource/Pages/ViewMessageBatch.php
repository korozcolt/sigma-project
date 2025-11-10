<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\MessageBatchResource\Pages;

use App\Filament\Resources\Messages\MessageBatchResource;
use Filament\Actions;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema as SchemaType;

class ViewMessageBatch extends ViewRecord
{
    protected static string $resource = MessageBatchResource::class;

    protected static ?string $title = 'Ver Envío Masivo';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Editar')
                ->visible(fn ($record) => $record->status === 'pending'),
        ];
    }

    public function infolist(SchemaType $schema): SchemaType
    {
        return $schema
            ->schema([
                Components\Section::make('Información General')
                    ->schema([
                        Components\TextEntry::make('name')
                            ->label('Nombre'),
                        Components\TextEntry::make('campaign.name')
                            ->label('Campaña'),
                        Components\TextEntry::make('template.name')
                            ->label('Plantilla'),
                        Components\TextEntry::make('type')
                            ->label('Tipo')
                            ->badge(),
                        Components\TextEntry::make('channel')
                            ->label('Canal')
                            ->badge(),
                        Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge(),
                    ])->columns(3),

                Components\Section::make('Estadísticas')
                    ->schema([
                        Components\TextEntry::make('total_recipients')
                            ->label('Total de Destinatarios'),
                        Components\TextEntry::make('sent_count')
                            ->label('Enviados'),
                        Components\TextEntry::make('failed_count')
                            ->label('Fallidos'),
                        Components\TextEntry::make('delivered_count')
                            ->label('Entregados'),
                        Components\TextEntry::make('progress_percentage')
                            ->label('Progreso')
                            ->getStateUsing(fn ($record) => $record->getProgressPercentage().'%'),
                        Components\TextEntry::make('success_rate')
                            ->label('Tasa de Éxito')
                            ->getStateUsing(fn ($record) => $record->getSuccessRate().'%'),
                    ])->columns(3),

                Components\Section::make('Fechas')
                    ->schema([
                        Components\TextEntry::make('scheduled_for')
                            ->label('Programado Para')
                            ->dateTime('d/m/Y H:i'),
                        Components\TextEntry::make('started_at')
                            ->label('Iniciado')
                            ->dateTime('d/m/Y H:i'),
                        Components\TextEntry::make('completed_at')
                            ->label('Completado')
                            ->dateTime('d/m/Y H:i'),
                    ])->columns(3),

                Components\Section::make('Filtros Aplicados')
                    ->schema([
                        Components\KeyValueEntry::make('filters')
                            ->label(''),
                    ])
                    ->collapsed(),
            ]);
    }
}
