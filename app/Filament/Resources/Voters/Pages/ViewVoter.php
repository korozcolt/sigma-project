<?php

namespace App\Filament\Resources\Voters\Pages;

use App\Enums\UserRole;
use App\Enums\VoterStatus;
use App\Filament\Resources\Voters\VoterResource;
use App\Models\Voter;
use Filament\Actions\EditAction;
use Filament\Infolists\Components;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema as SchemaType;
use Illuminate\Database\Eloquent\Model;

class ViewVoter extends ViewRecord
{
    protected static string $resource = VoterResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->loadMissing('registeredBy.coordinator.areaCoordinator');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(SchemaType $schema): SchemaType
    {
        return $schema->schema([
            Components\TextEntry::make('first_name')
                ->label('Nombres'),
            Components\TextEntry::make('last_name')
                ->label('Apellidos'),
            Components\TextEntry::make('document_number')
                ->label('Documento'),
            Components\TextEntry::make('phone')
                ->label('Teléfono'),
            Components\TextEntry::make('status')
                ->label('Estado')
                ->badge(),
            Components\TextEntry::make('municipality.name')
                ->label('Municipio'),
            Components\TextEntry::make('neighborhood.name')
                ->label('Barrio'),
            Components\TextEntry::make('campaign.name')
                ->label('Campaña'),

            Components\TextEntry::make('lider')
                ->label('Líder')
                ->state(fn (Voter $record): string => $this->resolveLiderLabel($record)),

            Components\TextEntry::make('coordinador')
                ->label('Coordinador')
                ->state(fn (Voter $record): string => $this->resolveCoordinadorLabel($record)),

            Components\TextEntry::make('articulador')
                ->label('Articulador')
                ->state(fn (Voter $record): string => $this->resolveArticuladorLabel($record)),

            Components\TextEntry::make('census_validated_at')
                ->label('Validado contra Censo')
                ->dateTime('d/m/Y H:i')
                ->placeholder('Aún no validado contra el censo'),

            Components\TextEntry::make('polling_place_source')
                ->label('Fuente del Puesto de Votación')
                ->badge()
                ->placeholder('Sin resolver'),

            Components\TextEntry::make('polling_place_resolved_at')
                ->label('Actualizado el')
                ->dateTime('d/m/Y H:i')
                ->placeholder('Sin resolver'),

            Components\TextEntry::make('pollingPlace.name')
                ->label('Puesto de Votación')
                ->placeholder('Sin resolver'),

            Components\TextEntry::make('pollingPlace.municipality.name')
                ->label('Municipio del Puesto de Votación')
                ->placeholder('Sin resolver'),

            Components\TextEntry::make('polling_table_number')
                ->label('Mesa')
                ->placeholder('Sin resolver'),

            Components\TextEntry::make('last_validation_source')
                ->label('Fuente de Última Validación')
                ->state(fn (Voter $record): string => $this->latestValidationSource($record))
                ->badge()
                ->color('info'),

            Components\TextEntry::make('next_step')
                ->label('Próxima Acción Recomendada')
                ->state(fn (Voter $record): string => $this->nextStepGuidance($record))
                ->badge()
                ->color('warning'),

            Components\TextEntry::make('missing_data')
                ->label('Datos Faltantes')
                ->state(fn (Voter $record): string => $this->missingDataSummary($record))
                ->color(fn (Voter $record): string => $this->missingDataSummary($record) === 'Sin datos faltantes' ? 'success' : 'danger'),
        ]);
    }

    private function resolveLiderLabel(Voter $record): string
    {
        $registrador = $record->registeredBy;

        if ($registrador?->hasRole(UserRole::LEADER->value)) {
            return $registrador->name;
        }

        return 'N/A';
    }

    private function resolveCoordinadorLabel(Voter $record): string
    {
        $registrador = $record->registeredBy;

        if ($registrador?->hasRole(UserRole::COORDINATOR->value)) {
            return $registrador->name;
        }

        return $registrador?->coordinator?->name ?? 'N/A';
    }

    private function resolveArticuladorLabel(Voter $record): string
    {
        $registrador = $record->registeredBy;

        if ($registrador?->hasRole(UserRole::COORDINATOR->value)) {
            return $registrador->areaCoordinator?->name ?? 'N/A';
        }

        return $registrador?->coordinator?->areaCoordinator?->name ?? 'N/A';
    }

    private function latestValidationSource(Voter $record): string
    {
        $latest = $record->validationHistories()->latest()->first();

        if (! $latest) {
            return 'Sin validaciones registradas';
        }

        return match ($latest->validation_type) {
            'census' => 'Censo Electoral',
            'call' => 'Llamada de Verificación',
            'election' => 'Jornada Electoral (Día D)',
            'territory' => 'Reconciliación Territorial',
            default => $latest->validation_type,
        };
    }

    private function nextStepGuidance(Voter $record): string
    {
        return match ($record->status) {
            VoterStatus::PENDING_REVIEW => 'Validar contra el censo electoral para continuar el flujo.',
            VoterStatus::REJECTED_CENSUS => 'Revisar y corregir el documento del apoyo antes de re-intentar la validación.',
            VoterStatus::CENSUS_NOT_FOUND => 'Pendiente de reconciliación en segundo plano — no se encontró en el censo electoral ni en los registros de identidad nacional.',
            VoterStatus::VERIFIED_CENSUS => 'Asignar a un revisor para verificación telefónica.',
            VoterStatus::VERIFIED_REGISTRADURIA => 'Verificado directamente por la Registraduría — asignar a un revisor si requiere verificación adicional.',
            VoterStatus::CORRECTION_REQUIRED => 'Corregir los datos señalados y volver a intentar la validación.',
            VoterStatus::VERIFIED_CALL => 'Confirmar asistencia para el día de la jornada electoral.',
            VoterStatus::CONFIRMED => 'Listo para el Día D — sin acciones pendientes.',
            VoterStatus::VOTED => 'Proceso completo — el apoyo ya ejerció su voto.',
            VoterStatus::DID_NOT_VOTE => 'Sin acciones pendientes — el apoyo no asistió a votar.',
            VoterStatus::DUPLICATE => 'Resolver la cédula duplicada desde el panel de administración.',
            VoterStatus::REJECTED_OUT_OF_SCOPE => 'Revisar el alcance territorial de la campaña — el apoyo quedó fuera del municipio/departamento definido.',
        };
    }

    private function missingDataSummary(Voter $record): string
    {
        $missing = collect([
            'phone' => 'Teléfono',
            'email' => 'Email',
            'neighborhood_id' => 'Barrio',
            'birth_date' => 'Fecha de nacimiento',
        ])->filter(fn (string $label, string $field) => blank($record->{$field}))->values();

        return $missing->isEmpty() ? 'Sin datos faltantes' : $missing->implode(', ');
    }
}
