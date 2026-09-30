<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\SparePart;
use App\Models\SparePartImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores part images on the private "local" disk with random names. Every upload is decoded and
 * re-encoded with GD — this strips metadata and any embedded payload — and a thumbnail is created.
 */
class ImageService
{
    public const DIR = 'parts';

    private const THUMB = 240;

    private const MAX_DIMENSION = 1600;

    public static function rules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.(int) config('spims.image_max_kb')];
    }

    public function store(SparePart $part, UploadedFile $file, bool $primary = false): SparePartImage
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new StockException('The uploaded file is not a valid JPEG, PNG or WebP image.');
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file->getRealPath()),
        };
        if (! $src) {
            throw new StockException('The image could not be processed. Please upload a different file.');
        }

        [$ext, $mime] = match ($info[2]) {
            IMAGETYPE_PNG => ['png', 'image/png'],
            IMAGETYPE_WEBP => ['webp', 'image/webp'],
            default => ['jpg', 'image/jpeg'],
        };
        $name = Str::uuid()->toString();
        $dir = self::DIR.'/'.$part->id;
        $full = $this->resize($src, self::MAX_DIMENSION);
        $thumb = $this->resize($src, self::THUMB);
        $path = "{$dir}/{$name}.{$ext}";
        $thumbPath = "{$dir}/{$name}_thumb.{$ext}";
        Storage::disk('local')->put($path, $this->encode($full, $ext));
        Storage::disk('local')->put($thumbPath, $this->encode($thumb, $ext));
        imagedestroy($src);

        if ($primary) {
            $part->images()->where('is_primary', true)->update(['is_primary' => false]);
        }

        return $part->images()->create([
            'path' => $path,
            'thumb_path' => $thumbPath,
            'original_name' => Str::limit($file->getClientOriginalName(), 180, ''),
            'mime' => $mime,
            'size' => Storage::disk('local')->size($path),
            'is_primary' => $primary,
            'sort_order' => (int) $part->images()->max('sort_order') + 1,
            'uploaded_by' => Auth::id(),
        ]);
    }

    public function makePrimary(SparePartImage $image): void
    {
        SparePartImage::where('spare_part_id', $image->spare_part_id)->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
    }

    /** Only non-primary images may be removed; the primary image can only be replaced. */
    public function delete(SparePartImage $image): void
    {
        if ($image->is_primary) {
            throw new StockException('The primary image cannot be removed — upload a replacement instead.');
        }
        Storage::disk('local')->delete([$image->path, $image->thumb_path]);
        $image->delete();
    }

    private function resize(\GdImage $src, int $max): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    private function encode(\GdImage $img, string $ext): string
    {
        ob_start();
        match ($ext) {
            'png' => imagepng($img, null, 6),
            'webp' => imagewebp($img, null, 85),
            default => (function () use ($img) {
                $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
                imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
                imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
                imagejpeg($bg, null, 85);
                imagedestroy($bg);
            })(),
        };
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
