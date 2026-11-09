<?php

namespace App\Http\Controllers;

use App\Services\Chat\ChatImage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Streams a chat image with a fixed content type; the name is regex-gated. */
class ChatUploadController extends Controller
{
    public function show(string $file, ChatImage $images): BinaryFileResponse
    {
        $path = $images->path($file);

        abort_if($path === null, 404);

        $response = response()->file($path, [
            'Content-Type' => str_ends_with($file, '.webp') ? 'image/webp' : 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
            // private: a shared cache must not keep a deleted image (or this response's session cookie).
            'Cache-Control' => 'private, max-age=3600',
        ]);

        // BinaryFileResponse defaults to public; force private after construction.
        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }
}
