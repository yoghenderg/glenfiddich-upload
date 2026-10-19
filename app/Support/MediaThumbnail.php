<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

final class MediaThumbnail
{
    public static function path(Media $media): string
    {
        return 'thumbnails/'.$media->public_id.'.jpg';
    }

    public static function generate(Media $media): ?string
    {
        $disk = Storage::disk($media->disk);
        $target = self::path($media);
        if ($media->type !== 'image' || ! $disk->exists($media->path)) {
            return null;
        }
        if ($disk->exists($target)) {
            return $target;
        }
        if (! extension_loaded('gd')) {
            return null;
        }
        $source = $disk->path($media->path);
        $dimensions = @getimagesize($source);
        if (! $dimensions) {
            return null;
        }
        // Avoid exhausting PHP memory when decoding very large uploads.
        $memory = trim(ini_get('memory_limit'));
        $limit = (int) $memory * match (strtolower(substr($memory, -1))) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };
        if ($limit > 0 && memory_get_usage(true) + $dimensions[0] * $dimensions[1] * 8 + 16 * 1024 * 1024 > $limit) {
            return null;
        }
        $photo = match ($media->mime_type) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };
        if (! $photo) {
            return null;
        }
        try {
            if ($media->mime_type === 'image/jpeg' && function_exists('exif_read_data')) {
                $orientation = (int) (@exif_read_data($source)['Orientation'] ?? 1);
                if (in_array($orientation, [2, 4, 5, 7], true)) {
                    imageflip($photo, in_array($orientation, [4, 5], true) ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
                }
                $angle = match ($orientation) { 3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0 };
                if ($angle) {
                    $rotated = imagerotate($photo, $angle, 0);
                    if ($rotated) {
                        imagedestroy($photo);
                        $photo = $rotated;
                    }
                }
            }
            $scale = min(1, 800 / max(imagesx($photo), imagesy($photo)));
            $width = max(1, (int) round(imagesx($photo) * $scale));
            $height = max(1, (int) round(imagesy($photo) * $scale));
            $preview = imagecreatetruecolor($width, $height);
            try {
                imagefill($preview, 0, 0, imagecolorallocate($preview, 255, 255, 255));
                imagecopyresampled($preview, $photo, 0, 0, 0, 0, $width, $height, imagesx($photo), imagesy($photo));
                ob_start();
                try {
                    imagejpeg($preview, null, 78);
                    $bytes = ob_get_contents();
                } finally {
                    ob_end_clean();
                }
                return $disk->put($target, $bytes) ? $target : null;
            } finally {
                imagedestroy($preview);
            }
        } finally {
            imagedestroy($photo);
        }
    }
}
