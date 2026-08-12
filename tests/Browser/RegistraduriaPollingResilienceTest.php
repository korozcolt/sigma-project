<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

it('keeps polling through transient non-2xx failures and surfaces the real success result instead of a false error', function () {
    $browserUser = User::factory()->withoutTwoFactor()->create([
        'email' => 'browser-test@example.com',
        'password' => 'password',
    ]);

    loginRealBrowserUser($browserUser);

    Route::middleware(['web', 'auth'])->get('/__test/registraduria-polling-transient', fn () => Blade::render(<<<'BLADE'
<!DOCTYPE html>
<html>
<head>@fluxScripts</head>
<body>
@include('filament.registraduria-browser', ['sessionId' => 'test-session-1', 'isOpen' => true])
</body>
</html>
BLADE));

    $baseUrl = config('services.registraduria.url');

    Http::fake([
        "{$baseUrl}/result/test-session-1" => Http::sequence()
            ->push(['error' => 'Servicio no disponible.'], 503)
            ->push(['error' => 'Servicio no disponible.'], 503)
            ->push([
                'status' => 'done',
                'data' => [
                    'puesto_nombre' => 'IE TEST',
                    'mesa_numero' => '5',
                    'municipio' => 'Sincelejo',
                    'departamento' => 'Sucre',
                    'direccion' => 'Calle Falsa 123',
                ],
            ], 200),
    ]);

    $page = visit('/__test/registraduria-polling-transient');

    $status = null;
    for ($i = 0; $i < 12; $i++) {
        $page->wait(1);
        $status = $page->script("Alpine.\$data(document.querySelector('[x-data]')).status");
        if (in_array($status, ['done', 'error'], true)) {
            break;
        }
    }

    expect($status)->toBe('done');
});

it('still treats a genuine HTTP-200 terminal error response as an immediate stop (unchanged behavior)', function () {
    $browserUser = User::factory()->withoutTwoFactor()->create([
        'email' => 'browser-test@example.com',
        'password' => 'password',
    ]);

    loginRealBrowserUser($browserUser);

    Route::middleware(['web', 'auth'])->get('/__test/registraduria-polling-error', fn () => Blade::render(<<<'BLADE'
<!DOCTYPE html>
<html>
<head>@fluxScripts</head>
<body>
@include('filament.registraduria-browser', ['sessionId' => 'test-session-2', 'isOpen' => true])
</body>
</html>
BLADE));

    $baseUrl = config('services.registraduria.url');

    Http::fake([
        "{$baseUrl}/result/test-session-2" => Http::response([
            'status' => 'error',
            'error' => 'session_expired',
        ], 200),
    ]);

    $page = visit('/__test/registraduria-polling-error');

    $status = null;
    for ($i = 0; $i < 6; $i++) {
        $page->wait(1);
        $status = $page->script("Alpine.\$data(document.querySelector('[x-data]')).status");
        if ($status === 'error') {
            break;
        }
    }

    expect($status)->toBe('error');
});
