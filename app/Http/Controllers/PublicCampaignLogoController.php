<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicCampaignLogoController extends Controller
{
    public function __invoke(string $filename): StreamedResponse
    {
        $filename = trim($filename);

        if ($filename === '' || ! preg_match('/\A[a-zA-Z0-9._-]+\z/', $filename)) {
            abort(404);
        }

        $path = "campaign-logos/{$filename}";

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path, headers: [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
