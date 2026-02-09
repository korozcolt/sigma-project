@php
    use App\Models\Campaign;
    use App\Services\CampaignContext;

    if (! CampaignContext::isSuperAdmin()) {
        return;
    }

    $campaigns = Campaign::query()->orderBy('name')->get(['id', 'name', 'status']);
    $currentCampaignId = CampaignContext::currentCampaignId();
    $allSelected = CampaignContext::allowsAllCampaigns();
@endphp

<form method="POST" action="{{ route('campaign-context.update') }}" class="flex items-center gap-2">
    @csrf
    <label class="text-xs text-gray-500">Campaña</label>
    <select name="campaign_id" class="rounded-lg border-gray-300 text-sm">
        <option value="all" @selected($allSelected)>Todas</option>
        @foreach ($campaigns as $campaign)
            <option value="{{ $campaign->id }}" @selected($currentCampaignId === $campaign->id)>
                {{ $campaign->name }}
            </option>
        @endforeach
    </select>
    <button type="submit" class="rounded-lg bg-primary-600 px-3 py-1 text-xs font-medium text-white">Cambiar</button>
</form>
