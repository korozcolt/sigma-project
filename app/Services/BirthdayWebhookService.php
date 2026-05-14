<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Models\Voter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BirthdayWebhookService
{
    public function dispatch(Campaign $campaign, Carbon $colombia): void
    {
        $url = $campaign->settings['birthday_webhook_url'] ?? null;

        if (! $url) {
            Log::warning('No webhook URL configured for campaign', ['campaign_id' => $campaign->id]);

            return;
        }

        $voters = Voter::where('campaign_id', $campaign->id)
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $colombia->month)
            ->whereDay('birth_date', $colombia->day)
            ->get();

        $users = $campaign->users()
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', $colombia->month)
            ->whereDay('birth_date', $colombia->day)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['coordinator', 'leader']))
            ->get();

        if ($voters->isEmpty() && $users->isEmpty()) {
            Log::info('No birthdays today for campaign', ['campaign_id' => $campaign->id]);

            return;
        }

        $people = [];

        foreach ($voters as $voter) {
            $people[] = [
                'type' => 'voter',
                'id' => $voter->id,
                'full_name' => $voter->full_name,
                'first_name' => $voter->first_name,
                'last_name' => $voter->last_name,
                'document_number' => $voter->document_number,
                'phone' => $voter->phone,
                'birth_date' => Carbon::parse($voter->birth_date)->format('Y-m-d'),
                'age' => $colombia->diffInYears(Carbon::parse($voter->birth_date)),
            ];
        }

        foreach ($users as $user) {
            $nameParts = explode(' ', $user->name, 2);
            $people[] = [
                'type' => $user->hasRole('coordinator') ? 'coordinator' : 'leader',
                'id' => $user->id,
                'full_name' => $user->name,
                'first_name' => $nameParts[0] ?? $user->name,
                'last_name' => $nameParts[1] ?? '',
                'document_number' => $user->document_number,
                'phone' => $user->phone,
                'birth_date' => Carbon::parse($user->birth_date)->format('Y-m-d'),
                'age' => $colombia->diffInYears(Carbon::parse($user->birth_date)),
            ];
        }

        $payload = [
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'candidate_name' => $campaign->settings['candidate_name'] ?? $campaign->name,
            'date' => $colombia->format('Y-m-d'),
            'dispatched_at' => $colombia->toIso8601String(),
            'total' => count($people),
            'people' => $people,
        ];

        Http::timeout(30)->post($url, $payload)->throw();
    }
}
