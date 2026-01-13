<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\VoterStatus;
use App\Filament\Widgets\DiaDStatsOverview;
use App\Models\Campaign;
use App\Models\ElectionEvent;
use App\Models\ValidationHistory;
use App\Models\Voter;
use App\Models\VoteRecord;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class DiaD extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Jornada Electoral (Día D)';

    protected static ?string $title = 'Jornada Electoral (Día D)';

    protected static string|\UnitEnum|null $navigationGroup = 'Jornada Electoral';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.dia-d';

    public string $documentNumber = '';

    public ?Voter $voter = null;

    public bool $canMarkVoted = false;

    public bool $canMarkDidNotVote = false;

    public ?int $voterId = null;

    public array $voterData = [];

    public array $stats = [
        'total' => 0,
        'confirmed' => 0,
        'voted' => 0,
        'did_not_vote' => 0,
    ];

    public function mount(): void
    {
        $this->refreshStats();
        $this->updateActionPermissions();
    }

    public function refreshStats(): void
    {
        $campaign = Campaign::where('status', 'active')->first();

        if (! $campaign) {
            $this->stats = [
                'total' => 0,
                'confirmed' => 0,
                'voted' => 0,
                'did_not_vote' => 0,
            ];

            return;
        }

        $this->stats['total'] = Voter::forCampaign($campaign->id)->count();
        $this->stats['confirmed'] = Voter::forCampaign($campaign->id)->where('status', VoterStatus::CONFIRMED->value)->count();
        $this->stats['voted'] = Voter::forCampaign($campaign->id)->voted()->count();
        $this->stats['did_not_vote'] = Voter::forCampaign($campaign->id)->didNotVote()->count();
    }

    public function searchVoter(): void
    {
        $campaign = Campaign::where('status', 'active')->first();

        $this->voter = null;
        $this->voterId = null;
        $this->voterData = [];
        $this->updateActionPermissions();

        if (! $campaign) {
            Notification::make()
                ->title('No hay campaña activa')
                ->danger()
                ->send();

            return;
        }

        if (empty(trim($this->documentNumber))) {
            Notification::make()
                ->title('Ingrese un número de documento')
                ->warning()
                ->send();

            return;
        }

        $this->voter = Voter::query()
            ->where('campaign_id', $campaign->id)
            ->where('document_number', trim($this->documentNumber))
            ->first();

        if (! $this->voter) {
            Notification::make()
                ->title('Votante no encontrado en la campaña activa')
                ->warning()
                ->send();

            return;
        }

        $this->voterId = $this->voter->id;
        $this->fillVoterData($this->voter);
        $this->updateActionPermissions();
    }

    public function markVoted(): void
    {
        if (! $this->voterId || ! ($voter = Voter::find($this->voterId))) {
            Notification::make()->title('Primero busque un votante')->warning()->send();

            return;
        }

        $campaign = Campaign::where('status', 'active')->first();
        $activeEvent = ElectionEvent::where('is_active', true)->first();

        if (! $activeEvent) {
            Notification::make()
                ->title('No hay ningún evento electoral activo en este momento')
                ->danger()
                ->send();

            return;
        }

        // Verificar si ya existe un registro de voto para este votante en este evento
        $existingRecord = VoteRecord::where('voter_id', $voter->id)
            ->where('election_event_id', $activeEvent->id)
            ->first();

        if ($existingRecord) {
            $eventType = $activeEvent->isSimulation() ? 'simulacro' : 'evento electoral';
            Notification::make()
                ->title("Este votante ya tiene un registro de voto en este {$eventType}")
                ->warning()
                ->send();

            return;
        }

        $previous = $voter->status;
        $voter->update([
            'status' => VoterStatus::VOTED,
            'voted_at' => now(),
        ]);

        // Crear registro detallado del voto con referencia al evento
        VoteRecord::create([
            'voter_id' => $voter->id,
            'campaign_id' => $campaign->id,
            'election_event_id' => $activeEvent->id,
            'recorded_by' => Auth::id(),
            'voted_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'notes' => "Voto registrado en {$activeEvent->name}",
        ]);

        ValidationHistory::create([
            'voter_id' => $voter->id,
            'previous_status' => $previous,
            'new_status' => VoterStatus::VOTED,
            'validated_by' => Auth::id(),
            'validation_type' => 'election',
            'notes' => "Marcado en Día D como VOTÓ - {$activeEvent->name}",
        ]);

        Notification::make()->title('Votante marcado como VOTÓ')->success()->send();
        $this->refreshStats();
        $this->fillVoterData($voter->fresh());
        $this->updateActionPermissions();
    }

    public function markDidNotVote(): void
    {
        if (! $this->voterId || ! ($voter = Voter::find($this->voterId))) {
            Notification::make()->title('Primero busque un votante')->warning()->send();

            return;
        }

        $previous = $voter->status;
        $voter->update([
            'status' => VoterStatus::DID_NOT_VOTE,
            'voted_at' => null,
        ]);

        ValidationHistory::create([
            'voter_id' => $voter->id,
            'previous_status' => $previous,
            'new_status' => VoterStatus::DID_NOT_VOTE,
            'validated_by' => Auth::id(),
            'validation_type' => 'election',
            'notes' => 'Marcado en Día D como NO VOTÓ',
        ]);

        Notification::make()->title('Votante marcado como NO VOTÓ')->success()->send();
        $this->refreshStats();
        $this->fillVoterData($voter->fresh());
        $this->updateActionPermissions();
    }

    private function updateActionPermissions(): void
    {
        if (! $this->voterId || ! ($voter = Voter::find($this->voterId))) {
            $this->canMarkVoted = false;
            $this->canMarkDidNotVote = false;

            return;
        }

        $this->canMarkVoted = $voter->status !== VoterStatus::VOTED;
        $this->canMarkDidNotVote = $voter->status !== VoterStatus::DID_NOT_VOTE;
    }

    private function fillVoterData(Voter $voter): void
    {
        $this->voterData = [
            'id' => $voter->id,
            'full_name' => $voter->full_name,
            'document_number' => $voter->document_number,
            'phone' => $voter->phone,
            'municipality' => $voter->municipality?->name,
            'status_label' => $voter->status->getLabel(),
            'status_value' => $voter->status->value,
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DiaDStatsOverview::class,
        ];
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['coordinator', 'leader', 'admin_campaign', 'super_admin']) ?? false;
    }
}
