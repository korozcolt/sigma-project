<?php

use App\Models\Campaign;
use App\Models\Department;
use App\Models\Municipality;
use App\Models\Neighborhood;
use App\Models\TerritorialAssignment;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;

it('can create a territorial assignment for neighborhood', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $neighborhood = Neighborhood::factory()->create();
    $assignedBy = User::factory()->create();

    $assignment = TerritorialAssignment::factory()->create([
        'user_id' => $user->id,
        'campaign_id' => $campaign->id,
        'neighborhood_id' => $neighborhood->id,
        'assigned_by' => $assignedBy->id,
    ]);

    expect($assignment)->toBeInstanceOf(TerritorialAssignment::class);
    expect($assignment->user_id)->toBe($user->id);
    expect($assignment->campaign_id)->toBe($campaign->id);
    expect($assignment->neighborhood_id)->toBe($neighborhood->id);
    expect($assignment->assigned_by)->toBe($assignedBy->id);

    assertDatabaseHas('territorial_assignments', [
        'user_id' => $user->id,
        'campaign_id' => $campaign->id,
        'neighborhood_id' => $neighborhood->id,
    ]);
});

it('can create assignment for municipality', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $municipality = Municipality::factory()->create();

    $assignment = TerritorialAssignment::factory()->forMunicipality($municipality->id)->create([
        'user_id' => $user->id,
        'campaign_id' => $campaign->id,
    ]);

    expect($assignment->municipality_id)->toBe($municipality->id);
    expect($assignment->department_id)->toBeNull();
    expect($assignment->neighborhood_id)->toBeNull();
});

it('can create assignment for department', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $department = Department::factory()->create();

    $assignment = TerritorialAssignment::factory()->forDepartment($department->id)->create([
        'user_id' => $user->id,
        'campaign_id' => $campaign->id,
    ]);

    expect($assignment->department_id)->toBe($department->id);
    expect($assignment->municipality_id)->toBeNull();
    expect($assignment->neighborhood_id)->toBeNull();
});

it('casts assigned_at to Carbon', function () {
    $assignment = TerritorialAssignment::factory()->create([
        'assigned_at' => '2025-01-15 10:30:00',
    ]);

    expect($assignment->assigned_at)->toBeInstanceOf(Carbon\Carbon::class);
    expect($assignment->assigned_at->format('Y-m-d H:i:s'))->toBe('2025-01-15 10:30:00');
});

it('has user relationship', function () {
    $assignment = TerritorialAssignment::factory()->create();

    expect($assignment->user())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve user', function () {
    $user = User::factory()->create(['name' => 'Test User']);
    $assignment = TerritorialAssignment::factory()->create(['user_id' => $user->id]);

    $assignment->load('user');

    expect($assignment->user->id)->toBe($user->id);
    expect($assignment->user->name)->toBe('Test User');
});

it('has campaign relationship', function () {
    $assignment = TerritorialAssignment::factory()->create();

    expect($assignment->campaign())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve campaign', function () {
    $campaign = Campaign::factory()->create(['name' => 'Campaña 2025']);
    $assignment = TerritorialAssignment::factory()->create(['campaign_id' => $campaign->id]);

    $assignment->load('campaign');

    expect($assignment->campaign->id)->toBe($campaign->id);
    expect($assignment->campaign->name)->toBe('Campaña 2025');
});

it('has department relationship', function () {
    $assignment = TerritorialAssignment::factory()->create();

    expect($assignment->department())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve department', function () {
    $department = Department::factory()->create(['name' => 'Antioquia']);
    $assignment = TerritorialAssignment::factory()->forDepartment($department->id)->create();

    $assignment->load('department');

    expect($assignment->department->id)->toBe($department->id);
    expect($assignment->department->name)->toBe('Antioquia');
});

it('has municipality relationship', function () {
    $assignment = TerritorialAssignment::factory()->create();

    expect($assignment->municipality())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve municipality', function () {
    $municipality = Municipality::factory()->create(['name' => 'Medellín']);
    $assignment = TerritorialAssignment::factory()->forMunicipality($municipality->id)->create();

    $assignment->load('municipality');

    expect($assignment->municipality->id)->toBe($municipality->id);
    expect($assignment->municipality->name)->toBe('Medellín');
});

it('has neighborhood relationship', function () {
    $assignment = TerritorialAssignment::factory()->create();

    expect($assignment->neighborhood())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve neighborhood', function () {
    $neighborhood = Neighborhood::factory()->create(['name' => 'El Poblado']);
    $assignment = TerritorialAssignment::factory()->forNeighborhood($neighborhood->id)->create();

    $assignment->load('neighborhood');

    expect($assignment->neighborhood->id)->toBe($neighborhood->id);
    expect($assignment->neighborhood->name)->toBe('El Poblado');
});

it('has assignedBy relationship', function () {
    $assignment = TerritorialAssignment::factory()->create();

    expect($assignment->assignedBy())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

it('can retrieve assignedBy user', function () {
    $assignedBy = User::factory()->create(['name' => 'Admin User']);
    $assignment = TerritorialAssignment::factory()->create(['assigned_by' => $assignedBy->id]);

    $assignment->load('assignedBy');

    expect($assignment->assignedBy->id)->toBe($assignedBy->id);
    expect($assignment->assignedBy->name)->toBe('Admin User');
});

it('user can have multiple territorial assignments', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $assignment1 = TerritorialAssignment::factory()->forNeighborhood()->create([
        'user_id' => $user->id,
        'campaign_id' => $campaign->id,
    ]);

    $assignment2 = TerritorialAssignment::factory()->forMunicipality()->create([
        'user_id' => $user->id,
        'campaign_id' => $campaign->id,
    ]);

    $user->load('territorialAssignments');

    expect($user->territorialAssignments)->toHaveCount(2);
});

it('can update territorial assignment', function () {
    $neighborhood1 = Neighborhood::factory()->create(['name' => 'Original']);
    $neighborhood2 = Neighborhood::factory()->create(['name' => 'Updated']);

    $assignment = TerritorialAssignment::factory()->forNeighborhood($neighborhood1->id)->create();

    $assignment->update(['neighborhood_id' => $neighborhood2->id]);

    expect($assignment->fresh()->neighborhood_id)->toBe($neighborhood2->id);
});

it('can delete territorial assignment', function () {
    $assignment = TerritorialAssignment::factory()->create();
    $id = $assignment->id;

    $assignment->delete();

    expect(TerritorialAssignment::find($id))->toBeNull();
});

it('deleting user cascades delete territorial assignments', function () {
    $user = User::factory()->create();
    $assignment = TerritorialAssignment::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect(TerritorialAssignment::find($assignment->id))->toBeNull();
});

it('deleting campaign cascades delete territorial assignments', function () {
    $campaign = Campaign::factory()->create();
    $assignment = TerritorialAssignment::factory()->create(['campaign_id' => $campaign->id]);

    $campaign->forceDelete(); // Force delete to bypass soft deletes

    expect(TerritorialAssignment::find($assignment->id))->toBeNull();
});

it('deleting department sets assignment department_id to null', function () {
    $department = Department::factory()->create();
    $assignment = TerritorialAssignment::factory()->forDepartment($department->id)->create();

    $department->delete();

    expect($assignment->fresh()->department_id)->toBeNull();
});

it('deleting municipality sets assignment municipality_id to null', function () {
    $municipality = Municipality::factory()->create();
    $assignment = TerritorialAssignment::factory()->forMunicipality($municipality->id)->create();

    $municipality->delete();

    expect($assignment->fresh()->municipality_id)->toBeNull();
});

it('deleting neighborhood sets assignment neighborhood_id to null', function () {
    $neighborhood = Neighborhood::factory()->create();
    $assignment = TerritorialAssignment::factory()->forNeighborhood($neighborhood->id)->create();

    $neighborhood->delete();

    expect($assignment->fresh()->neighborhood_id)->toBeNull();
});
