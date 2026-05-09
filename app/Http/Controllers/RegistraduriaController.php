<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegistraduriaController extends Controller
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.registraduria.url', 'http://localhost:5757');
    }

    public function lookup(Request $request): \Illuminate\Http\JsonResponse
    {
        $cedula = $request->input('cedula', '');

        if (blank($cedula)) {
            return response()->json(['error' => 'El campo cedula es requerido.'], 422);
        }

        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/lookup", ['cedula' => $cedula]);

            if (! $response->successful()) {
                Log::error('RegistraduriaController: lookup failed', ['status' => $response->status()]);

                return response()->json(['error' => 'El servicio de Registraduría no está disponible.'], 502);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            Log::error('RegistraduriaController: lookup exception', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'El servicio de Registraduría no está disponible. Inicia el servicio Python primero.'], 503);
        }
    }

    public function result(string $id): \Illuminate\Http\JsonResponse
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/result/{$id}");

            if ($response->status() === 404) {
                return response()->json(['error' => 'Sesión no encontrada.'], 404);
            }

            if (! $response->successful()) {
                return response()->json(['error' => 'Error comunicándose con el servicio.'], 502);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Servicio no disponible.'], 503);
        }
    }

    public function screenshot(string $id): Response
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/screenshot/{$id}");

            if ($response->status() === 404) {
                abort(404, 'Sesión no encontrada.');
            }

            if (! $response->successful()) {
                abort(502, 'No se pudo obtener el screenshot.');
            }

            return response($response->body(), 200)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->header('Pragma', 'no-cache');
        } catch (\Exception $e) {
            abort(503, 'Servicio no disponible.');
        }
    }

    public function click(string $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $x = $request->input('x');
        $y = $request->input('y');

        if ($x === null || $y === null) {
            return response()->json(['error' => 'Se requieren x e y.'], 422);
        }

        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/click/{$id}", [
                'x' => (float) $x,
                'y' => (float) $y,
            ]);

            if (! $response->successful()) {
                return response()->json(['error' => 'Error al enviar clic.'], 502);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Servicio no disponible.'], 503);
        }
    }

    public function viewport(string $id): \Illuminate\Http\JsonResponse
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/viewport/{$id}");

            if (! $response->successful()) {
                return response()->json(['error' => 'Error al obtener viewport.'], 502);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Servicio no disponible.'], 503);
        }
    }
}
