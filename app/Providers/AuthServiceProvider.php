<?php

namespace App\Providers;

use App\Models\Invitation;
use App\Models\User;
use App\Policies\InvitationPolicy;
use App\Services\CampaignContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Invitation::class => InvitationPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function ($user, string $ability, array $arguments = []) {
            if (CampaignContext::isSuperAdmin($user)) {
                return null;
            }

            $campaignId = CampaignContext::currentCampaignId($user);
            if (! $campaignId) {
                return null;
            }

            $model = $arguments[0] ?? null;

            if ($model instanceof User) {
                return $model->campaigns()->whereKey($campaignId)->exists() ? null : false;
            }

            if ($model instanceof Model && $model->getAttribute('campaign_id')) {
                return (int) $model->getAttribute('campaign_id') === (int) $campaignId ? null : false;
            }

            return null;
        });
    }
}
