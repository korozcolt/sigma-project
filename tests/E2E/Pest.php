<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('E2E');

beforeAll(function () {
    if (! filter_var(env('E2E_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        $this->markTestSkipped('E2E tests disabled. Set E2E_ENABLED=true to run.');
    }
});
