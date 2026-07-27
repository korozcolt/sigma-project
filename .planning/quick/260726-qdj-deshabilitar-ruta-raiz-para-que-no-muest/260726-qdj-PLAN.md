---
phase: quick-260726-qdj
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - routes/web.php
  - tests/Feature/HomeRouteTest.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "Visiting '/' returns an empty response (no welcome view, no content)"
    - "Visiting '/admin' still loads the Filament login flow normally"
  artifacts:
    - path: "routes/web.php"
      provides: "Root route ('/') returns a blank response instead of the welcome view"
    - path: "tests/Feature/HomeRouteTest.php"
      provides: "Automated regression coverage for the blank '/' response and unaffected '/admin'"
      exports: []
  key_links:
    - from: "routes/web.php"
      to: "tests/Feature/HomeRouteTest.php"
      via: "GET / assertion"
      pattern: "response\\(''"
---

<objective>
Disable the root route ("/") so it no longer renders the Laravel `welcome` view or any content, while leaving `/admin` (Filament login) and every other route fully functional. Client request from sigma-betha-app: the root URL should show nothing at all.

Purpose: Remove an unintended public-facing default Laravel page from a production political-operations app; the app's real entry point is `/admin`.
Output: `routes/web.php` root route returns a blank 200 response; `tests/Feature/HomeRouteTest.php` proves `/` is blank and `/admin` is unaffected.
</objective>

<execution_context>
@$HOME/.claude/get-shit-done/workflows/execute-plan.md
@$HOME/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@routes/web.php

<interfaces>
Current root route (routes/web.php, lines 12-14):
```php
Route::get('/', function () {
    return view('welcome');
})->name('home');
```

The `home` route name is used elsewhere in the app (redirects, links) — it MUST be preserved so no other code breaks. Only the closure's return value changes.

Filament admin panel is registered at path `admin` (app/Providers/Filament/AdminPanelProvider.php:45 — `->path('admin')`). It is a separate panel provider, entirely independent of `routes/web.php`, so changing the `/` closure cannot affect `/admin`'s registration or behavior.

No other file in the codebase references the `welcome` view (confirmed via grep of routes/ and resources/views/) — safe to stop rendering it without deleting `resources/views/welcome.blade.php` (other blade partials/layouts do not extend or include it).
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Blank the root route and add regression test</name>
  <files>routes/web.php, tests/Feature/HomeRouteTest.php</files>
  <behavior>
    - Test 1: `GET /` returns HTTP 200 with an empty response body (no welcome markup, no Laravel/Vite boilerplate).
    - Test 2: `GET /admin` (unauthenticated) still returns a normal Filament response — either a 200 login page render or a redirect (302) into the panel's login route — proving the panel is untouched.
    - Test 3 (guard against regression): `GET /` body does not contain any recognizable welcome-view string (e.g. does not contain `<html` or the app name banner), confirming it's truly blank and not just an empty-looking view.
  </behavior>
  <action>
    In `routes/web.php`, replace the root route's closure body so it returns a blank response instead of `view('welcome')`, keeping the `->name('home')` binding intact:
    ```php
    Route::get('/', function () {
        return response('', 200);
    })->name('home');
    ```
    Do not delete `resources/views/welcome.blade.php` (per constraint) — it's simply no longer rendered by this route.

    Create `tests/Feature/HomeRouteTest.php` following this repo's Pest style (see `tests/Feature/MaintenanceKillSwitchTest.php` for conventions: `declare(strict_types=1);`, plain `test()` calls, no unnecessary `RefreshDatabase` unless a test touches the DB — these tests don't need it since they hit unauthenticated public routes):
    ```php
    <?php

    declare(strict_types=1);

    test('the root route returns a blank response', function () {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('', false); // no-op placeholder; real assertion below
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
    ```
    Remove the placeholder `assertSee('', false)` line if it causes a false pass/no-op — rely on `expect($response->getContent())->toBe('');` as the primary blank-body assertion instead.
  </action>
  <verify>
    <automated>php artisan test --filter=HomeRouteTest</automated>
  </verify>
  <done>`php artisan test --filter=HomeRouteTest` passes all 3 tests: root returns 200 with empty body, no welcome markup leaks, and `/admin` still responds normally (200 or 302).</done>
</task>

</tasks>

<verification>
- `php artisan test --filter=HomeRouteTest` passes.
- `php artisan route:list --no-interaction | grep -E "GET.*/ "` still shows the `home` named route registered (not removed, just returns blank content).
- Manual sanity: `php artisan serve` then visit `/` in a browser — page is blank; visit `/admin` — Filament login page renders normally.
- `vendor/bin/pint --dirty` run before finishing, per project convention.
</verification>

<success_criteria>
- Visiting `/` produces no visible content (empty 200 response), replacing the previous Laravel welcome page.
- `/admin` and all other existing routes are unaffected — no regressions.
- A committed automated test (`tests/Feature/HomeRouteTest.php`) locks in both behaviors so this cannot silently regress.
- `resources/views/welcome.blade.php` remains on disk, untouched, simply unused by this route.
</success_criteria>

<output>
After completion, create `.planning/quick/260726-qdj-deshabilitar-ruta-raiz-para-que-no-muest/260726-qdj-SUMMARY.md`
</output>
