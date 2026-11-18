<?php

declare(strict_types=1);

namespace Tardis\Media;

/**
 * Resizes and re-encodes raster images with PHP's GD extension.
 *
 * Processing is optional: without GD, or for a format GD cannot read (SVG,
 * a corrupt file), every method answers null and the caller keeps the
 * original bytes. Images are never enlarged.
 */
class ImageProcessor
{
    /** Formats GD can read and write back in the same format. */
    private const FORMATS = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefromstring');
    }

    public function supports(?string $mimeType): bool
    {
        return $this->available() && in_array($mimeType, self::FORMATS, true);
    }

    /**
     * Fit the image inside $width x $height, keeping its proportions. With
     * $crop, fill the box exactly instead, cutting the overflow from the
     * centre. A missing side is free.
     *
     * @return string|null the encoded image, or null when it could not be processed
     */
    public function resize(string $contents, string $mimeType, ?int $width, ?int $height, int $quality = 90, bool $crop = false): ?string
    {
        if (! $this->supports($mimeType) || ($width === null && $height === null)) {
            return null;
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        try {
            [$srcW, $srcH] = [imagesx($source), imagesy($source)];
            [$x, $y, $cutW, $cutH, $dstW, $dstH] = $this->geometry($srcW, $srcH, $width, $height, $crop);

            $target = imagecreatetruecolor($dstW, $dstH);

            if (in_array($mimeType, ['image/png', 'image/webp', 'image/gif'], true)) {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
            }

            imagecopyresampled($target, $source, 0, 0, $x, $y, $dstW, $dstH, $cutW, $cutH);

            try {
                return $this->encode($target, $mimeType, $quality);
            } finally {
                imagedestroy($target);
            }
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int} source x, y, cut width, cut height, target width, target height
     */
    private function geometry(int $srcW, int $srcH, ?int $width, ?int $height, bool $crop): array
    {
        $width = $width !== null && $width > 0 ? $width : null;
        $height = $height !== null && $height > 0 ? $height : null;

        if ($crop && $width !== null && $height !== null) {
            $dstW = min($width, $srcW);
            $dstH = min($height, $srcH);
            $scale = max($dstW / $srcW, $dstH / $srcH);
            $cutW = (int) round($dstW / $scale);
            $cutH = (int) round($dstH / $scale);

            return [intdiv($srcW - $cutW, 2), intdiv($srcH - $cutH, 2), $cutW, $cutH, $dstW, $dstH];
        }

        $scale = min(1, $width !== null ? $width / $srcW : 1, $height !== null ? $height / $srcH : 1);

        return [0, 0, $srcW, $srcH, max(1, (int) round($srcW * $scale)), max(1, (int) round($srcH * $scale))];
    }

    private function encode(\GdImage $image, string $mimeType, int $quality): ?string
    {
        $quality = max(1, min(100, $quality));

        ob_start();

        $ok = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, null, $quality),
            'image/png' => imagepng($image, null, (int) round((100 - $quality) * 9 / 100)),
            'image/webp' => imagewebp($image, null, $quality),
            'image/gif' => imagegif($image),
            default => false,
        };

        $bytes = (string) ob_get_clean();

        return $ok && $bytes !== '' ? $bytes : null;
    }
}
