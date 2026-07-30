<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\VoterStatus;
use App\Models\ValidationHistory;
use App\Models\Voter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemediateMisrejectedCensusVoters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'census:remediate-misrejected {--campaign=1 : Campaign ID to remediate} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revierte a pendiente de revisión los apoyos rechazados incorrectamente contra el censo huérfano (census_records), campaña por campaña';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $campaignId = (int) $this->option('campaign');
        $dryRun = (bool) $this->option('dry-run');

        $voters = Voter::query()
            ->where('campaign_id', $campaignId)
            ->where('status', VoterStatus::REJECTED_CENSUS->value)
            ->get();

        if (! $dryRun) {
            DB::transaction(function () use ($voters) {
                foreach ($voters as $voter) {
                    $previousStatus = $voter->status;

                    $voter->update([
                        'status' => VoterStatus::PENDING_REVIEW,
                    ]);

                    ValidationHistory::create([
                        'voter_id' => $voter->id,
                        'previous_status' => $previousStatus,
                        'new_status' => VoterStatus::PENDING_REVIEW,
                        'validated_by' => null,
                        'validation_type' => 'census',
                        'notes' => 'Corrección de datos: el rechazo anterior se basó en la tabla '.
                            'census_records huérfana (no un import real del censo), no en una '.
                            'ausencia real. Revertido a pendiente de revisión para reingresar al '.
                            'ciclo normal de validación/reconciliación.',
                    ]);
                }
            });
        }

        $this->info(sprintf(
            'Campaña %d: %d apoyo(s) %s.',
            $campaignId,
            $voters->count(),
            $dryRun ? 'serían revertidos a pendiente de revisión (dry-run, sin cambios)' : 'revertidos a pendiente de revisión'
        ));

        return self::SUCCESS;
    }
}
