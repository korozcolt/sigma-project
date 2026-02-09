<?php

use App\Enums\CampaignScope;
use App\Enums\ElectionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('election_type')->default(ElectionType::OTHER->value)->after('name');
            $table->index('election_type');
        });

        DB::table('campaigns')->select('id', 'scope')->orderBy('id')->chunkById(100, function ($campaigns) {
            foreach ($campaigns as $campaign) {
                $scope = CampaignScope::tryFrom($campaign->scope ?? '');
                $type = $scope ? ElectionType::fromScope($scope)->value : ElectionType::OTHER->value;
                DB::table('campaigns')->where('id', $campaign->id)->update(['election_type' => $type]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['election_type']);
            $table->dropColumn('election_type');
        });
    }
};
