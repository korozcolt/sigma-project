<?php

namespace App\Filament\Schemas;

use App\Models\MetadataKey;
use App\Models\User;
use App\Services\MetadataAssignmentService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Illuminate\Support\Collection;

class MetadataAssignment
{
    /**
     * @return array<int, mixed>
     */
    public static function modalSchema(): array
    {
        return [
            Select::make('metadata_key_id')
                ->label('Llave')
                ->options(fn (): array => app(MetadataAssignmentService::class)->activeKeyOptions())
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('value', null)),

            TextInput::make('value')
                ->label('Valor')
                ->required()
                ->maxLength(255)
                ->visible(fn (Get $get): bool => static::typeOf($get('metadata_key_id')) === 'text'),

            TextInput::make('value')
                ->label('Valor')
                ->required()
                ->numeric()
                ->step(0.01)
                ->visible(fn (Get $get): bool => static::typeOf($get('metadata_key_id')) === 'numeric'),

            DatePicker::make('value')
                ->label('Valor')
                ->required()
                ->native(false)
                ->displayFormat('d/m/Y')
                ->format('Y-m-d')
                ->visible(fn (Get $get): bool => static::typeOf($get('metadata_key_id')) === 'date'),

            Select::make('value')
                ->label('Valor')
                ->required()
                ->options(fn (Get $get): array => static::optionsOf($get('metadata_key_id')))
                ->visible(fn (Get $get): bool => static::typeOf($get('metadata_key_id')) === 'select'),
        ];
    }

    protected static function typeOf(int|string|null $keyId): ?string
    {
        return app(MetadataAssignmentService::class)->findActiveKey($keyId)?->type;
    }

    /** @return array<string, string> */
    protected static function optionsOf(int|string|null $keyId): array
    {
        $options = app(MetadataAssignmentService::class)->findActiveKey($keyId)?->options ?? [];

        return array_combine($options, $options);
    }

    public static function section(): Section
    {
        return Section::make('Metadata')
            ->key('metadataAssignmentSection')
            ->description('Valores del catálogo asignados a este usuario.')
            ->visibleOn('edit')
            ->schema([
                View::make('filament.components.metadata-current-values')
                    ->viewData(fn (?User $record): array => [
                        'currentValues' => $record
                            ? app(MetadataAssignmentService::class)->currentValues($record)
                            : collect(),
                    ]),
            ])
            ->headerActions([
                Action::make('assignMetadata')
                    ->label('Asignar metadata')
                    ->icon('heroicon-o-tag')
                    ->modalHeading('Asignar metadata')
                    ->modalSubmitActionLabel('Asignar')
                    ->schema(static::modalSchema())
                    ->action(function (array $data, User $record): void {
                        app(MetadataAssignmentService::class)->assign(
                            subject: $record,
                            key: MetadataKey::findOrFail($data['metadata_key_id']),
                            value: (string) $data['value'],
                            assignedBy: auth()->user(),
                        );

                        Notification::make()->title('Metadata asignada')->success()->send();
                    }),
            ]);
    }

    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('assignMetadata')
            ->label('Asignar metadata')
            ->icon('heroicon-o-tag')
            ->modalHeading('Asignar metadata a los seleccionados')
            ->modalSubmitActionLabel('Asignar')
            ->schema(static::modalSchema())
            ->action(function (Collection $records, array $data): void {
                $assigned = app(MetadataAssignmentService::class)->assignMany(
                    subjects: $records,
                    key: MetadataKey::findOrFail($data['metadata_key_id']),
                    value: (string) $data['value'],
                    assignedBy: auth()->user(),
                );

                Notification::make()
                    ->title("Metadata asignada a {$assigned} usuario(s)")
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
