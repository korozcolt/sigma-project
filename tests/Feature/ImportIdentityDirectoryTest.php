<?php

declare(strict_types=1);

use App\Models\NationalIdentityRecord;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/identity/identity-sample.csv');
    Storage::fake('local');
});

test('imports clean cedulas and discards the conflicting one', function () {
    $this->artisan('identity:import-directory', ['--path' => $this->fixture])->assertSuccessful();

    expect(NationalIdentityRecord::count())->toBe(3)
        ->and(NationalIdentityRecord::where('cedula', '1000000001')->exists())->toBeTrue()
        ->and(NationalIdentityRecord::where('cedula', '1000000002')->exists())->toBeTrue()
        ->and(NationalIdentityRecord::where('cedula', '1000000003')->exists())->toBeTrue()
        ->and(NationalIdentityRecord::where('cedula', '1000000004')->exists())->toBeFalse();
});

test('writes a conflicts report file listing every variant row of the discarded cedula', function () {
    $this->artisan('identity:import-directory', ['--path' => $this->fixture])->assertSuccessful();

    $files = collect(Storage::disk('local')->files())
        ->filter(fn (string $f) => str_starts_with($f, 'identity-import-conflicts-'));

    expect($files)->toHaveCount(1);

    $content = Storage::disk('local')->get($files->first());

    expect($content)->toContain('cedula,nombre1,nombre2,apellido1,apellido2')
        ->and($content)->toContain('1000000004,Ana,,Torres,Vidal')
        ->and($content)->toContain('1000000004,"Ana Maria",,Torres,Vidal');
});

test('skips rows with a blank cedula, nombre1, or apellido1', function () {
    $this->artisan('identity:import-directory', ['--path' => $this->fixture])
        ->expectsOutputToContain('Filas vacías omitidas')
        ->assertSuccessful();

    expect(NationalIdentityRecord::count())->toBe(3);
});

test('dry-run reports counts without writing any rows', function () {
    $this->artisan('identity:import-directory', ['--path' => $this->fixture, '--dry-run' => true])->assertSuccessful();

    expect(NationalIdentityRecord::count())->toBe(0);
});

test('re-running the import is idempotent with no duplicate cedula rows', function () {
    $this->artisan('identity:import-directory', ['--path' => $this->fixture])->assertSuccessful();
    $this->artisan('identity:import-directory', ['--path' => $this->fixture])->assertSuccessful();

    expect(NationalIdentityRecord::count())->toBe(3);
});
