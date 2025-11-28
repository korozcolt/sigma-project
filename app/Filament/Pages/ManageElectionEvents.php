<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Campaign;
use App\Models\ElectionEvent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ManageElectionEvents extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Gestión de Eventos';

    protected static ?string $title = 'Gestión de Eventos Electorales';

    protected static string|\UnitEnum|null $navigationGroup = 'Jornada Electoral';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.manage-election-events';

    public ?ElectionEvent $activeEvent = null;

    public array $upcomingEvents = [];

    public array $pastEvents = [];

    public function mount(): void
    {
        $this->loadEvents();
    }

    public function loadEvents(): void
    {
        $this->activeEvent = ElectionEvent::where('is_active', true)->first();

        $this->upcomingEvents = ElectionEvent::where('is_active', false)
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->limit(5)
            ->get()
            ->toArray();

        $this->pastEvents = ElectionEvent::where('is_active', false)
            ->where('date', '<', now()->toDateString())
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_simulation')
                ->label('Crear Simulacro')
                ->icon(Heroicon::OutlinedBeaker)
                ->color('info')
                ->form([
                    Select::make('campaign_id')
                        ->label('Campaña')
                        ->options(Campaign::pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    TextInput::make('name')
                        ->label('Nombre del Simulacro')
                        ->placeholder('Ej: Simulacro #1')
                        ->required()
                        ->maxLength(255),

                    DatePicker::make('date')
                        ->label('Fecha')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()),

                    TimePicker::make('start_time')
                        ->label('Hora de Inicio (Opcional)')
                        ->seconds(false),

                    TimePicker::make('end_time')
                        ->label('Hora de Fin (Opcional)')
                        ->seconds(false),

                    TextInput::make('simulation_number')
                        ->label('Número de Simulacro')
                        ->numeric()
                        ->minValue(1)
                        ->default(fn () => ElectionEvent::where('type', 'simulation')->max('simulation_number') + 1),

                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    ElectionEvent::create([
                        'campaign_id' => $data['campaign_id'],
                        'name' => $data['name'],
                        'type' => 'simulation',
                        'date' => $data['date'],
                        'start_time' => $data['start_time'] ?? null,
                        'end_time' => $data['end_time'] ?? null,
                        'simulation_number' => $data['simulation_number'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'is_active' => false,
                    ]);

                    Notification::make()
                        ->title('Simulacro creado exitosamente')
                        ->success()
                        ->send();

                    $this->loadEvents();
                }),

            Action::make('create_real_event')
                ->label('Crear Día D Real')
                ->icon(Heroicon::OutlinedCalendar)
                ->color('danger')
                ->form([
                    Select::make('campaign_id')
                        ->label('Campaña')
                        ->options(Campaign::pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    TextInput::make('name')
                        ->label('Nombre del Evento')
                        ->placeholder('Ej: Día D - Elecciones 2025')
                        ->required()
                        ->maxLength(255),

                    DatePicker::make('date')
                        ->label('Fecha de Elección')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->minDate(now()),

                    TimePicker::make('start_time')
                        ->label('Hora de Inicio')
                        ->seconds(false)
                        ->default('08:00'),

                    TimePicker::make('end_time')
                        ->label('Hora de Fin')
                        ->seconds(false)
                        ->default('18:00'),

                    Textarea::make('notes')
                        ->label('Notas')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    ElectionEvent::create([
                        'campaign_id' => $data['campaign_id'],
                        'name' => $data['name'],
                        'type' => 'real',
                        'date' => $data['date'],
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'notes' => $data['notes'] ?? null,
                        'is_active' => false,
                    ]);

                    Notification::make()
                        ->title('Día D Real creado exitosamente')
                        ->success()
                        ->send();

                    $this->loadEvents();
                }),
        ];
    }

    public function activateEvent(int $eventId): void
    {
        $event = ElectionEvent::findOrFail($eventId);

        if (! $event->canActivate()) {
            Notification::make()
                ->title('No se puede activar este evento')
                ->body('Solo se pueden activar eventos para el día de hoy.')
                ->danger()
                ->send();

            return;
        }

        if ($event->activate()) {
            Notification::make()
                ->title('Evento activado')
                ->body("El evento '{$event->name}' ha sido activado exitosamente.")
                ->success()
                ->send();

            $this->loadEvents();
        } else {
            Notification::make()
                ->title('Error al activar')
                ->danger()
                ->send();
        }
    }

    public function deactivateEvent(int $eventId): void
    {
        $event = ElectionEvent::findOrFail($eventId);

        if ($event->deactivate()) {
            Notification::make()
                ->title('Evento desactivado')
                ->body("El evento '{$event->name}' ha sido desactivado.")
                ->warning()
                ->send();

            $this->loadEvents();
        }
    }

    public function deleteEvent(int $eventId): void
    {
        $event = ElectionEvent::findOrFail($eventId);

        if ($event->is_active) {
            Notification::make()
                ->title('No se puede eliminar')
                ->body('No puedes eliminar un evento activo. Primero desactívalo.')
                ->danger()
                ->send();

            return;
        }

        $event->delete();

        Notification::make()
            ->title('Evento eliminado')
            ->success()
            ->send();

        $this->loadEvents();
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['super_admin', 'admin_campaign']) ?? false;
    }
}
