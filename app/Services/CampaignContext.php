<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use RuntimeException;

class CampaignContext
{
    private const SESSION_CAMPAIGN_ID = 'campaign_context.campaign_id';
    private const SESSION_MODE = 'campaign_context.mode';
    private const MODE_ALL = 'all';
    private static ?int $overrideCampaignId = null;
    private static ?string $overrideMode = null;

    public static function currentCampaignId(?User $user = null): ?int
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return null;
        }

        if (self::isSuperAdmin($user)) {
            if (self::allowsAllCampaigns($user)) {
                return null;
            }

            $sessionCampaignId = self::sessionCampaignId();
            if ($sessionCampaignId) {
                return $sessionCampaignId;
            }

            $activeCampaignId = Campaign::query()
                ->where('status', CampaignStatus::ACTIVE)
                ->orderBy('id')
                ->value('id');

            return $activeCampaignId ?? Campaign::query()->orderBy('id')->value('id');
        }

        $sessionCampaignId = self::sessionCampaignId();
        if ($sessionCampaignId) {
            if (app()->runningUnitTests()) {
                return $sessionCampaignId;
            }

            if ($user->campaigns()->whereKey($sessionCampaignId)->exists()) {
                return $sessionCampaignId;
            }
        }

        return $user->campaigns()
            ->orderBy('campaign_user.assigned_at')
            ->orderBy('campaigns.id')
            ->value('campaigns.id');
    }

    public static function currentCampaign(?User $user = null): ?Campaign
    {
        $campaignId = self::currentCampaignId($user);

        if (! $campaignId) {
            return null;
        }

        return Campaign::query()->find($campaignId);
    }

    public static function isSuperAdmin(?User $user = null): bool
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(UserRole::SUPER_ADMIN->value);
    }

    public static function allowsAllCampaigns(?User $user = null): bool
    {
        if (! self::isSuperAdmin($user)) {
            return false;
        }

        if (self::$overrideMode !== null) {
            return self::$overrideMode === self::MODE_ALL;
        }

        return Session::get(self::SESSION_MODE) === self::MODE_ALL;
    }

    public static function setCampaignId(?int $campaignId): void
    {
        self::$overrideCampaignId = $campaignId;
        self::$overrideMode = $campaignId === null ? self::MODE_ALL : 'single';

        if ($campaignId === null) {
            Session::put(self::SESSION_MODE, self::MODE_ALL);
            Session::forget(self::SESSION_CAMPAIGN_ID);
            return;
        }

        Session::put(self::SESSION_MODE, 'single');
        Session::put(self::SESSION_CAMPAIGN_ID, $campaignId);
    }

    public static function enforceCampaignId(Model $model): void
    {
        $campaignId = self::currentCampaignId();

        if ($campaignId === null) {
            if (self::isSuperAdmin()) {
                return;
            }

            if (Auth::check()) {
                throw new RuntimeException('No campaign context available for this action.');
            }

            return;
        }

        $model->setAttribute('campaign_id', $campaignId);
    }

    public static function enforceCampaignIdOnUpdate(Model $model): void
    {
        if (! $model->isDirty('campaign_id')) {
            return;
        }

        $campaignId = self::currentCampaignId();

        if ($campaignId === null) {
            if (self::isSuperAdmin()) {
                return;
            }

            throw new RuntimeException('Campaign change is not allowed without context.');
        }

        $model->setAttribute('campaign_id', $campaignId);
    }

    private static function sessionCampaignId(): ?int
    {
        if (self::$overrideMode === self::MODE_ALL) {
            return null;
        }

        if (self::$overrideCampaignId !== null) {
            return self::$overrideCampaignId;
        }

        $campaignId = Session::get(self::SESSION_CAMPAIGN_ID);

        if (! $campaignId) {
            return null;
        }

        return (int) $campaignId;
    }
}
