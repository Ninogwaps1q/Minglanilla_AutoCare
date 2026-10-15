<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicImageController extends Controller
{
    public function __invoke(string $directory, string $filename): StreamedResponse
    {
        $path = $directory.'/'.$filename;
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($disk->path($path));
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return $disk->response($path, headers: [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
