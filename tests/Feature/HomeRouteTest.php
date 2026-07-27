<?php

declare(strict_types=1);

test('the root route returns a blank response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    expect($response->getContent())->toBe('');
});

test('the root route does not render the welcome view', function () {
    $response = $this->get('/');

    $response->assertDontSee('Laravel', false);
    $response->assertDontSee('<html', false);
});

test('the admin panel still loads normally', function () {
    $response = $this->get('/admin');

    expect($response->status())->toBeIn([200, 302]);
});
