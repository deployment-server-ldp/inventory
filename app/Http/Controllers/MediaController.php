<?php

namespace App\Http\Controllers;

use App\Models\SparePartImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Serves part images from private storage to authenticated users only (no public symlink needed). */
class MediaController extends Controller
{
    public function partImage(SparePartImage $image, string $variant): Response
    {
        $path = $variant === 'thumb' ? $image->thumb_path : $image->path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            // File missing on disk (e.g. images not yet copied to a new server): neutral placeholder instead of a broken image.
            return response(self::placeholderSvg(), 200, [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'private, no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $image->mime,
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function placeholderSvg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240"><rect width="240" height="240" fill="#eef2f7"/>'
            .'<g fill="none" stroke="#94a3b8" stroke-width="8"><rect x="50" y="62" width="140" height="116" rx="10"/><circle cx="95" cy="102" r="14"/>'
            .'<path d="M58 170l46-46 30 30 22-22 34 34"/></g><text x="120" y="215" text-anchor="middle" font-family="sans-serif" font-size="16" fill="#64748b">Image not uploaded</text></svg>';
    }
}
