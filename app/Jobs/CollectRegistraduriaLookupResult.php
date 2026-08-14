<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PollingPlaceSource;
use App\Models\RegistraduriaLiveSession;
use App\Models\Voter;
use App\Services\LiveSourceAdapter;
use App\Services\PollingPlaceResolutionResult;
use App\Services\PollingPlaceResolver;
use App\Services\VoterValidationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Background collector for a live/2captcha lookup that PollingPlaceResolver's
 * synchronous ~40s cascade window (attemptLiveAutomated()) gave up on too early.
 * The real Python microservice + 2captcha solve can take up to ~150s, and the
 * submission was ALREADY PAID FOR — this job keeps re-checking
 * GET /result/{session_id} on the real queue worker (never blocking
 * schedule:run/dispatchSync) until a definitive outcome arrives or the
 * RegistraduriaLiveSession's expiry window passes.
 *
 * Dispatched only for real, container-resolvable adapters (never anonymous
 * test doubles — see PollingPlaceResolver::isDispatchableAdapter()).
 *
 * See .planning/debug/resolved/2captcha-duplicate-spend.md.
 */
class CollectRegistraduriaLookupResult implements ShouldQueue
{
    use Queueable;

    private const RECHECK_DELAY_SECONDS = 90;

    private const MAX_ATTEMPTS_BEFORE_EXHAUSTION = 5;

    public function __construct(public readonly string $documentNumber) {}

    public function handle(PollingPlaceResolver $resolver, VoterValidationService $validationService): void
    {
        $session = RegistraduriaLiveSession::query()
            ->where('document_number', $this->documentNumber)
            ->first();

        // Already resolved/released by another path (e.g. the interactive modal's
        // own fast-path handler, or a previous run of this same job) — nothing to do.
        if (! $session || ! $session->session_id || ! $session->adapter_class) {
            return;
        }

        try {
            $adapter = app($session->adapter_class);
        } catch (\Throwable $e) {
            Log::warning('registraduria.collector.adapter_unresolvable', [
                'document_number' => $this->documentNumber,
                'adapter_class' => $session->adapter_class,
                'error' => $e->getMessage(),
            ]);
            $session->delete();

            return;
        }

        if (! $adapter instanceof LiveSourceAdapter) {
            $session->delete();

            return;
        }

        try {
            $result = $adapter->getResult($session->session_id);
        } catch (\Throwable $e) {
            Log::warning('registraduria.collector.get_result_failed', [
                'document_number' => $this->documentNumber,
                'error' => $e->getMessage(),
            ]);
            $result = ['status' => 'error', 'data' => null, 'error' => $e->getMessage()];
        }

        $status = $result['status'] ?? 'error';

        if ($status === 'done') {
            $data = $result['data'] ?? null;

            if (is_array($data) && filled($data['puesto_nombre'] ?? null)) {
                $this->persistSuccess($resolver, $validationService, $session, $data);
            } else {
                $this->recordGenuineFailure($validationService, $session, 'not_found');
            }

            $session->delete();

            return;
        }

        if (in_array($status, ['waiting_captcha', 'error'], true)) {
            $this->recordGenuineFailure($validationService, $session, $status);
            $session->delete();

            return;
        }

        // Still pending/solving_captcha/waiting_result.
        if (now()->greaterThanOrEqualTo($session->expires_at)) {
            Log::warning('registraduria.collector.window_expired', [
                'document_number' => $this->documentNumber,
                'started_at' => $session->created_at,
            ]);
            $this->recordGenuineFailure($validationService, $session, 'window_expired');
            $session->delete();

            return;
        }

        static::dispatch($this->documentNumber)->delay(now()->addSeconds(self::RECHECK_DELAY_SECONDS));
    }

    /**
     * @param  array<string, string>  $data
     */
    private function persistSuccess(
        PollingPlaceResolver $resolver,
        VoterValidationService $validationService,
        RegistraduriaLiveSession $session,
        array $data,
    ): void {
        $resolver->persistPermanentLookup($this->documentNumber, $data, $session->campaign_id);

        if (! $session->voter_id) {
            return;
        }

        $voter = Voter::find($session->voter_id);

        if (! $voter) {
            return;
        }

        $result = new PollingPlaceResolutionResult(
            source: PollingPlaceSource::LIVE,
            fields: $data,
            pollingPlaceId: $resolver->resolveOrCreatePollingPlace($data)?->id,
            tableNumber: ltrim($data['mesa_numero'] ?? '', '0') ?: null,
        );

        $resolver->persist($voter, $result, isExplicitOverride: false, resolvedVia: $session->resolved_via ?? 'reconciliation');

        $voter->update([
            'reconciliation_attempts' => 0,
            'reconciliation_exhausted_at' => null,
        ]);

        // Mirrors ReconcileFallbackPollingPlaces's upgrade branch: reuse this
        // already-fetched (already-paid-for) LIVE result to also sync `status`,
        // instead of waiting on the separate census:reconcile-validation cron job.
        $validationService->updateVoterStatus($voter->fresh(), found: true);
    }

    private function recordGenuineFailure(VoterValidationService $validationService, RegistraduriaLiveSession $session, string $reason): void
    {
        if (! $session->voter_id) {
            return;
        }

        $voter = Voter::find($session->voter_id);

        if (! $voter) {
            return;
        }

        $attempts = $voter->reconciliation_attempts + 1;
        $isExhausted = $attempts >= self::MAX_ATTEMPTS_BEFORE_EXHAUSTION;

        $voter->update([
            'reconciliation_attempts' => $attempts,
            'reconciliation_exhausted_at' => $isExhausted ? now() : null,
        ]);

        Log::info('registraduria.collector.genuine_failure', [
            'voter_id' => $voter->id,
            'reason' => $reason,
            'attempts' => $attempts,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('registraduria.collector.failed', [
            'document_number' => $this->documentNumber,
            'error' => $exception->getMessage(),
        ]);

        RegistraduriaLiveSession::where('document_number', $this->documentNumber)->delete();
    }
}
