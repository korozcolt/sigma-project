<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PublicCampaignLogoController extends Controller
{
    public function __invoke(string $filename): Response
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

