<?php

declare(strict_types=1);

use App\Models\NationalIdentityRecord;
use App\Services\IdentityLookupService;

test('findByDocumentNumber returns null when no record matches the cedula', function () {
    expect(app(IdentityLookupService::class)->findByDocumentNumber('9999999999'))->toBeNull();
});

test('findByDocumentNumber returns the matching record', function () {
    $record = NationalIdentityRecord::factory()->create(['cedula' => '1053006255']);

    $found = app(IdentityLookupService::class)->findByDocumentNumber('1053006255');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($record->id);
});
