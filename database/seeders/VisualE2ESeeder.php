<?php

namespace Database\Seeders;

use App\Enums\CampaignStatus;
use App\Enums\ElectionType;
use App\Models\Campaign;
use App\Models\Department;
use App\Models\ElectionEvent;
use App\Models\Invitation;
use App\Models\MessageBatch;
use App\Models\MessageTemplate;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Enums\QuestionType;

class VisualE2ESeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $department = Department::query()->first() ?? Department::factory()->create();
        $municipality = Municipality::query()->where('department_id', $department->id)->first()
            ?? Municipality::factory()->create(['department_id' => $department->id]);
        $neighborhood = Neighborhood::query()->where('municipality_id', $municipality->id)->first()
            ?? Neighborhood::factory()->create(['municipality_id' => $municipality->id]);

        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'ing.korozco@gmail.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Admin123'),
                'document_number' => (string) random_int(10000000, 99999999),
                'phone' => '300' . random_int(1000000, 9999999),
            ]
        );

        $superAdmin->forceFill([
            'name' => 'Administrador',
            'password' => Hash::make('Admin123'),
        ])->save();

        if (! $superAdmin->hasRole('super_admin')) {
            $superAdmin->syncRoles(['super_admin']);
        }

        $campaign = Campaign::query()->firstOrCreate(
            ['name' => 'Campaña Visual E2E'],
            [
                'election_type' => ElectionType::PRESIDENT->value,
                'status' => CampaignStatus::ACTIVE,
                'candidate_name' => 'Candidato Visual',
                'description' => 'Campaña para pruebas visuales E2E.',
                'start_date' => now()->subDays(7),
                'end_date' => now()->addDays(7),
                'election_date' => now()->addDays(7),
                'department_id' => $department->id,
                'municipality_id' => $municipality->id,
                'settings' => [],
                'created_by' => $superAdmin->id,
            ]
        );

        $users = [
            'admin_campaign' => [
                'email' => 'admin.campaign@sigma.test',
                'name' => 'Admin Campaign',
            ],
            'coordinator' => [
                'email' => 'coordinator@sigma.test',
                'name' => 'Coordinador',
            ],
            'leader' => [
                'email' => 'leader@sigma.test',
                'name' => 'Lider',
            ],
            'reviewer' => [
                'email' => 'reviewer@sigma.test',
                'name' => 'Revisor',
            ],
        ];

        foreach ($users as $role => $data) {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('Admin123'),
                    'document_number' => (string) random_int(10000000, 99999999),
                    'phone' => '300' . random_int(1000000, 9999999),
                ]
            );

            $user->forceFill([
                'name' => $data['name'],
                'password' => Hash::make('Admin123'),
            ])->save();

            if (! $user->hasRole($role)) {
                $user->syncRoles([$role]);
            }

            if (! $user->campaigns()->whereKey($campaign->id)->exists()) {
                $user->campaigns()->attach($campaign->id, [
                    'assigned_at' => now(),
                    'assigned_by' => $superAdmin?->id,
                ]);
            }
        }

        $adminCampaign = User::query()->where('email', 'admin.campaign@sigma.test')->first();

        Voter::query()->firstOrCreate(
            ['document_number' => '10000001'],
            [
                'campaign_id' => $campaign->id,
                'first_name' => 'Votante',
                'last_name' => 'Visual',
                'phone' => '3000000001',
                'status' => 'confirmed',
                'municipality_id' => $municipality->id,
                'neighborhood_id' => $neighborhood->id,
                'registered_by' => $adminCampaign?->id ?? $superAdmin->id,
            ]
        );

        $survey = Survey::query()->firstOrCreate(
            ['title' => 'Encuesta Visual'],
            [
                'campaign_id' => $campaign->id,
                'description' => 'Encuesta para pruebas visuales',
                'is_active' => true,
            ]
        );

        if (! $survey->questions()->exists()) {
            SurveyQuestion::factory()->create([
                'survey_id' => $survey->id,
                'question_text' => '¿Confirma su voto?',
                'question_type' => QuestionType::YES_NO,
                'is_required' => true,
                'order' => 1,
            ]);
        }

        $template = MessageTemplate::query()->firstOrCreate(
            ['name' => 'Recordatorio Visual'],
            [
                'campaign_id' => $campaign->id,
                'type' => 'reminder',
                'channel' => 'sms',
                'content' => 'Hola {nombre}, recuerde votar.',
                'is_active' => true,
                'created_by' => $adminCampaign?->id ?? $superAdmin->id,
            ]
        );

        MessageBatch::query()->firstOrCreate(
            ['name' => 'Envio Visual'],
            [
                'campaign_id' => $campaign->id,
                'template_id' => $template?->id,
                'type' => 'reminder',
                'channel' => 'sms',
                'status' => 'pending',
                'total_recipients' => 1,
                'created_by' => $adminCampaign?->id ?? $superAdmin->id,
            ]
        );

        ElectionEvent::query()->firstOrCreate(
            ['name' => 'Evento Visual'],
            [
                'campaign_id' => $campaign->id,
                'type' => 'simulation',
                'date' => now()->format('Y-m-d'),
                'is_active' => false,
            ]
        );

        Invitation::query()->firstOrCreate(
            ['campaign_id' => $campaign->id, 'invited_email' => 'leader.invited@sigma.test'],
            [
                'token' => Str::random(32),
                'invited_by_user_id' => $adminCampaign?->id ?? $superAdmin->id,
                'invited_name' => 'Invitado Visual',
                'target_role' => 'LEADER',
                'expires_at' => now()->addDays(7),
            ]
        );
    }
}
