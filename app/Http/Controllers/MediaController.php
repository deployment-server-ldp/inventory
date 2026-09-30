<?php

namespace App\Http\Controllers;

use App\Models\SparePartImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Serves part images from private storage to authenticated users only (no public symlink needed). */
class MediaController extends Controller
{
    public function partImage(SparePartImage $image, string $variant): BinaryFileResponse
    {
        $path = $variant === 'thumb' ? $image->thumb_path : $image->path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $image->mime,
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
