<?php

namespace App\Services;

use App\Models\CallAssignment;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Collection;

class CallAssignmentService
{
    /**
     * Assign voters to a caller with balanced distribution
     */
    public function assignVoters(
        Campaign $campaign,
        User $caller,
        Collection $voters,
        User $assignedBy,
        string $priority = 'medium'
    ): Collection {
        $assignments = collect();

        foreach ($voters as $voter) {
            $assignment = $this->assignVoter(
                voter: $voter,
                caller: $caller,
                campaign: $campaign,
                assignedBy: $assignedBy,
                priority: $priority
            );

            $assignments->push($assignment);
        }

        return $assignments;
    }

    /**
     * Assign a single voter to a caller
     */
    public function assignVoter(
        Voter $voter,
        User $caller,
        Campaign $campaign,
        User $assignedBy,
        string $priority = 'medium'
    ): CallAssignment {
        return CallAssignment::create([
            'voter_id' => $voter->id,
            'assigned_to' => $caller->id,
            'assigned_by' => $assignedBy->id,
            'campaign_id' => $campaign->id,
            'status' => 'pending',
            'priority' => $priority,
            'assigned_at' => now(),
        ]);
    }

    /**
     * Auto-assign voters to callers with balanced load distribution
     */
    public function autoAssignVoters(
        Campaign $campaign,
        Collection $voters,
        Collection $callers,
        User $assignedBy,
        string $priority = 'medium'
    ): Collection {
        if ($callers->isEmpty()) {
            throw new \InvalidArgumentException('No callers available for assignment');
        }

        // Get current workload for each caller
        $workload = $this->getCallerWorkload($campaign, $callers);

        // Sort callers by workload (ascending) to balance distribution
        $callersByWorkload = $workload->sortBy('pending_count')->pluck('caller');

        $assignments = collect();
        $callerIndex = 0;

        foreach ($voters as $voter) {
            $caller = $callersByWorkload->get($callerIndex);

            $assignment = $this->assignVoter(
                voter: $voter,
                caller: $caller,
                campaign: $campaign,
                assignedBy: $assignedBy,
                priority: $priority
            );

            $assignments->push($assignment);

            // Round-robin to next caller
            $callerIndex = ($callerIndex + 1) % $callersByWorkload->count();
        }

        return $assignments;
    }

    /**
     * Get workload statistics for callers in a campaign
     */
    public function getCallerWorkload(Campaign $campaign, Collection $callers): Collection
    {
        return $callers->map(function (User $caller) use ($campaign) {
            $pending = CallAssignment::query()
                ->forCampaign($campaign->id)
                ->forCaller($caller->id)
                ->pending()
                ->count();

            $inProgress = CallAssignment::query()
                ->forCampaign($campaign->id)
                ->forCaller($caller->id)
                ->inProgress()
                ->count();

            $completed = CallAssignment::query()
                ->forCampaign($campaign->id)
                ->forCaller($caller->id)
                ->completed()
                ->count();

            return [
                'caller' => $caller,
                'pending_count' => $pending,
                'in_progress_count' => $inProgress,
                'completed_count' => $completed,
                'total_count' => $pending + $inProgress + $completed,
            ];
        });
    }

    /**
     * Reassign pending assignments from one caller to another
     */
    public function reassignPending(User $fromCaller, User $toCaller, Campaign $campaign): int
    {
        $assignments = CallAssignment::query()
            ->forCampaign($campaign->id)
            ->forCaller($fromCaller->id)
            ->pending()
            ->get();

        foreach ($assignments as $assignment) {
            $assignment->reassign($toCaller->id);
        }

        return $assignments->count();
    }

    /**
     * Get next assignment for a caller (prioritized queue)
     */
    public function getNextAssignment(User $caller, Campaign $campaign): ?CallAssignment
    {
        return CallAssignment::query()
            ->forCampaign($campaign->id)
            ->forCaller($caller->id)
            ->pending()
            ->orderedByPriority()
            ->oldest('assigned_at')
            ->first();
    }

    /**
     * Get caller's queue with priority ordering
     */
    public function getCallerQueue(User $caller, Campaign $campaign, int $limit = 50): Collection
    {
        return CallAssignment::query()
            ->with(['voter', 'campaign'])
            ->forCampaign($campaign->id)
            ->forCaller($caller->id)
            ->where('status', '!=', 'completed')
            ->orderedByPriority()
            ->oldest('assigned_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark assignment as completed and update voter if needed
     */
    public function completeAssignment(CallAssignment $assignment): void
    {
        $assignment->markCompleted();
    }

    /**
     * Get assignments requiring follow-up
     */
    public function getFollowUpAssignments(Campaign $campaign, int $days = 7): Collection
    {
        return CallAssignment::query()
            ->with(['voter', 'verificationCalls'])
            ->forCampaign($campaign->id)
            ->whereHas('verificationCalls', function ($query) use ($days) {
                $query->needsFollowUp()
                    ->where('call_date', '>=', now()->subDays($days));
            })
            ->where('status', '!=', 'completed')
            ->get();
    }

    /**
     * Generate statistics for a campaign's call assignments
     */
    public function getCampaignStatistics(Campaign $campaign): array
    {
        $total = CallAssignment::forCampaign($campaign->id)->count();
        $pending = CallAssignment::forCampaign($campaign->id)->pending()->count();
        $inProgress = CallAssignment::forCampaign($campaign->id)->inProgress()->count();
        $completed = CallAssignment::forCampaign($campaign->id)->completed()->count();

        $completionRate = $total > 0 ? ($completed / $total) * 100 : 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'completion_rate' => round($completionRate, 2),
        ];
    }

    /**
     * Bulk update priority for assignments
     */
    public function updatePriority(Collection $assignments, string $priority): int
    {
        $count = 0;

        foreach ($assignments as $assignment) {
            $assignment->update(['priority' => $priority]);
            $count++;
        }

        return $count;
    }
}
