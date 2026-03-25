# Testing Patterns

**Analysis Date:** 2026-03-24

## Test Framework

**Runner:**
- Pest PHP 4.1
- Configuration: `phpunit.xml` in project root

**Assertion Library:**
- Pest's `expect()` syntax (chains like `expect($value)->toBeTrue()`)
- Inherits classic PHPUnit assertions if needed

**Run Commands:**
```bash
php artisan test                      # Run all tests natively via Laravel
vendor/bin/pest                       # Run directly with Pest
vendor/bin/pest --parallel            # Run tests in parallel
vendor/bin/pest --coverage            # Coverage report
vendor/bin/pest tests/Feature/X.php   # Single file
```

## Test File Organization

**Location:**
- Tests live in the `tests/` directory at the project root.
- Separated into `Feature/` and `Unit/` folders.

**Naming:**
- Files typically end with `Test.php` (e.g., `ExampleTest.php`)

**Structure:**
```
tests/
  Feature/
    ExampleTest.php
  Unit/
    ExampleTest.php
  Pest.php                # Pest global configurations and helpers
  TestCase.php            # Base PHPUnit class extended by tests
```

## Test Structure

**Suite Organization:**
```php
// Pest uses a functional testing style

it('can perform an action', function () {
    // arrange
    $user = User::factory()->create();

    // act
    $response = $this->actingAs($user)->get('/dashboard');

    // assert
    $response->assertStatus(200);
});

test('a specific condition', function () {
    expect(true)->toBeTrue();
});
```

**Patterns:**
- Use `beforeEach()` at the top of the file for shared setup
- Uses Laravel's `RefreshDatabase` implicitly in `Feature` tests configured in `Pest.php`

## Mocking

**Framework:**
- Mockery (comes with Laravel)
- Built-in Laravel facada fakes (Bus, Queue, Mail, Event)

**Patterns:**
```php
// Mocking Laravel Facades
Mail::fake();
Mail::assertSent(OrderShipped::class);

// Mocking Classes
$mock = Mockery::mock(Service::class);
$mock->shouldReceive('handle')->once()->andReturn(true);
$this->app->instance(Service::class, $mock);
```

**What to Mock:**
- External APIs, strict 3rd party integrations (Stripe, SendGrid)
- Dispatched Jobs, Mails, Events, and Notifications

**What NOT to Mock:**
- Internal database models (use database factories instead)

## Fixtures and Factories

**Test Data:**
```php
// Using Laravel Factories
$users = User::factory()->count(3)->create();

// State modifications
$admin = User::factory()->admin()->create();
```

**Location:**
- Factories are defined in `database/factories/`.
- Seeders are in `database/seeders/` but usually factories are preferred in unit/feature tests.

## Coverage

**Requirements:**
- No strict coverage target defined natively.

**Configuration:**
- Run via `php artisan test --coverage` (Requires PCOV or Xdebug).

## Test Types

**Unit Tests:**
- Scope: Independent classes or methods isolated from Laravel environment.
- Location: `tests/Unit/`
- Speed: Extremely fast.

**Feature Tests:**
- Scope: Testing complete HTTP requests, database states, and broad features.
- Setup: Bootstraps the full Laravel application and migrates a testing DB (usually sqlite in memory).
- Location: `tests/Feature/`

**E2E Tests:**
- Framework: Playwright (`@playwright/test` found in `package.json`)
- Scope: Browser-based full flow assertions.

## Common Patterns

**HTTP Testing:**
```php
it('loads the homepage', function () {
    $this->get('/')->assertOk();
});
```

**Livewire Testing:**
```php
use function Pest\Livewire\livewire;

it('can render component', function () {
    livewire(MyComponent::class)
        ->set('title', 'Hello')
        ->call('save')
        ->assertHasNoErrors();
});
```

---

*Testing analysis: 2026-03-24*
*Update when test patterns change*
