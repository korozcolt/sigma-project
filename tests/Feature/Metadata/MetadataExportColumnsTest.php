<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Exports\AnnotatorsExport;
use App\Exports\CoordinatorsExport;
use App\Exports\LeadersExport;
use App\Exports\WitnessesExport;
use App\Models\Campaign;
use App\Models\MetadataKey;
use App\Models\User;
use App\Services\MetadataAssignmentService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    collect(UserRole::values())->each(
        fn ($role) => Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])
    );

    $this->campaign = Campaign::factory()->create(['status' => 'active']);
});

function metadataExportUser(?string $role = null): User
{
    $user = User::factory()->create([
        'document_number' => (string) fake()->unique()->numerify('##########'),
        'phone' => fake()->numerify('3#########'),
    ]);
    $user->campaigns()->attach(test()->campaign->id);

    if ($role !== null) {
        $user->assignRole($role);
    }

    return $user;
}

it('appends one heading per active metadata key, blank for missing values, on the {export} export', function (string $exportClass, ?string $role) {
    $withValue = metadataExportUser($role);
    $withoutValue = metadataExportUser($role);
    $assigner = metadataExportUser();

    $key = MetadataKey::factory()->create(['label' => 'Bono Especial', 'type' => 'numeric', 'is_active' => true]);
    $inactiveKey = MetadataKey::factory()->create(['label' => 'Descontinuada', 'is_active' => false]);

    app(MetadataAssignmentService::class)->assign($withValue, $key, '50000', $assigner);

    $export = new $exportClass(queryBuilder: User::query()->whereIn('id', [$withValue->id, $withoutValue->id]));

    expect($export->headings())->toContain('Bono Especial')
        ->and($export->headings())->not->toContain('Descontinuada');

    $rows = $export->query()->get()->keyBy('id');

    $mappedWithValue = $export->map($rows[$withValue->id]);
    $mappedWithoutValue = $export->map($rows[$withoutValue->id]);

    // Numeric-typed keys are resolved via CAST(value AS DECIMAL(20,4)) in
    // withCurrentValueSelects(); sqlite (the test connection, per phpunit.xml)
    // returns a native int for this cast rather than a formatted decimal
    // string (mysql's behavior), so compare as a string to be DB-agnostic.
    expect((string) end($mappedWithValue))->toBe('50000')
        ->and(end($mappedWithoutValue))->toBe('');
})->with([
    'coordinadores' => [CoordinatorsExport::class, UserRole::COORDINATOR->value],
    'líderes' => [LeadersExport::class, UserRole::LEADER->value],
    'anotadores' => [AnnotatorsExport::class, null],
    'testigos' => [WitnessesExport::class, null],
]);

it('shows the current value, not a superseded historical one, on the {export} export', function (string $exportClass, ?string $role) {
    $user = metadataExportUser($role);
    $assigner = metadataExportUser();

    $key = MetadataKey::factory()->create(['label' => 'Estado', 'type' => 'text', 'is_active' => true]);
    $service = app(MetadataAssignmentService::class);
    $service->assign($user, $key, 'valor-viejo', $assigner);
    $service->assign($user, $key, 'valor-actual', $assigner);

    $export = new $exportClass(queryBuilder: User::query()->whereIn('id', [$user->id]));

    $row = $export->query()->get()->first();

    // end() requires a reference to a variable, not a function return value.
    $mapped = $export->map($row);

    expect(end($mapped))->toBe('valor-actual');
})->with([
    'coordinadores' => [CoordinatorsExport::class, UserRole::COORDINATOR->value],
    'líderes' => [LeadersExport::class, UserRole::LEADER->value],
    'anotadores' => [AnnotatorsExport::class, null],
    'testigos' => [WitnessesExport::class, null],
]);
