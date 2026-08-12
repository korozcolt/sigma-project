<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Browser');

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('E2E');

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
})->in('E2E');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

// Real Pest v4 Browser tests run every request through the real Laravel HTTP kernel
// (Pest\Browser\Drivers\LaravelHttpServer), so actingAs() never authenticates the
// real browser session — only a genuine /login form submission does. Shared here
// (not per-test-file) so multiple Browser test files can use it without a PHP
// "cannot redeclare function" fatal when the whole Browser suite runs together.
function loginRealBrowserUser(User $user, string $password = 'password'): void
{
    $page = visit('/login');
    $page->type('email', $user->email);
    $page->type('password', $password);
    $page->click('Ingresar');
    $page->wait(1);
}
