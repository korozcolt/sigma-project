<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema as SchemaType;
use Filament\Support\Enums\FontFamily;

class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;

    public function infolist(SchemaType $schema): SchemaType
    {
        return $schema->schema([
            Components\TextEntry::make('created_at')
                ->label('Fecha')
                ->dateTime('d/m/Y H:i:s'),

            Components\TextEntry::make('user.name')
                ->label('Usuario')
                ->placeholder('Sistema'),

            Components\TextEntry::make('action')
                ->label('Acción')
                ->badge(),

            Components\TextEntry::make('auditable_type')
                ->label('Modelo')
                ->placeholder('—')
                ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),

            Components\TextEntry::make('auditable_id')
                ->label('ID Registro')
                ->placeholder('—'),

            Components\TextEntry::make('campaign_id')
                ->label('Campaña ID')
                ->placeholder('—'),

            Components\TextEntry::make('ip_address')
                ->label('IP')
                ->placeholder('—'),

            Components\TextEntry::make('user_agent')
                ->label('User Agent')
                ->placeholder('—')
                ->columnSpanFull(),

            Components\TextEntry::make('old_values')
                ->label('Valores Anteriores')
                ->state(fn (AuditLog $record): string => $record->old_values
                    ? json_encode($record->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : '—')
                ->fontFamily(FontFamily::Mono)
                ->columnSpanFull(),

            Components\TextEntry::make('new_values')
                ->label('Valores Nuevos')
                ->state(fn (AuditLog $record): string => $record->new_values
                    ? json_encode($record->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : '—')
                ->fontFamily(FontFamily::Mono)
                ->columnSpanFull(),
        ]);
    }
}
