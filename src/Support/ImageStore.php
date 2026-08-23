<?php

namespace Alle80\Griglia\Support;

use Alle80\Griglia\Models\Attachment;
use Alle80\Griglia\Models\Todo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Store an image uploaded as the attachment of a todo, resizing it
 * with GD within MAX_SIDE px (smartphone photos are huge) and
 * re-encoding it: that normalizes the format and drops the EXIF metadata.
 */
class ImageStore
{
    public const MAX_SIDE = 1600;

    // No WebP: the container's GD cannot decode it
    public const ALLOWED = ['image/jpeg', 'image/png', 'image/gif'];

    public static function store(Todo $todo, UploadedFile $file): Attachment
    {
        $mime = $file->getMimeType();

        if (! in_array($mime, self::ALLOWED, true)) {
            throw new RuntimeException('Formato non supportato: '.$mime);
        }
        // Decompression bombs: refuse huge pixel counts BEFORE decoding (a tiny PNG can be 20000×20000)
        $info = @getimagesize($file->getRealPath());
        if (! $info || empty($info[0]) || empty($info[1])) {
            throw new RuntimeException('Immagine non leggibile');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new RuntimeException(__('griglia::t.image_too_large', ['mp' => (int) (self::MAX_PIXELS / 1_000_000)]));
        }

        [$data, $ext, $w, $h, $outMime] = self::process($file->getRealPath(), $mime);

        $path = sprintf('attachments/%d/%s.%s', $todo->id, Str::ulid(), $ext);
        Storage::disk(config('griglia.attachments_disk', 'local'))->put($path, $data);

        return $todo->attachments()->create([
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName() ?: 'immagine', 200, ''),
            'mime' => $outMime,
            'size' => strlen($data),
            'width' => $w,
            'height' => $h,
        ]);
    }

    /** Max pixels accepted (width × height) before decoding: 40 megapixel. */
    public const MAX_PIXELS = 40_000_000;

    /** @return array{0:string,1:string,2:int,3:int,4:string} [bytes, ext, width, height, mime] */
    private static function process(string $realPath, string $mime): array
    {
        // GIFs (potentially animated) go through untouched
        if ($mime === 'image/gif') {
            [$w, $h] = getimagesize($realPath);

            return [file_get_contents($realPath), 'gif', $w, $h, 'image/gif'];
        }

        $src = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($realPath),
            'image/png' => imagecreatefrompng($realPath),
        };

        if (! $src) {
            throw new RuntimeException('Immagine non leggibile');
        }

        // Phone photos arrive rotated through EXIF: straighten before resizing
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($realPath);
            $src = match ($exif['Orientation'] ?? 1) {
                3 => imagerotate($src, 180, 0),
                6 => imagerotate($src, -90, 0),
                8 => imagerotate($src, 90, 0),
                default => $src,
            };
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, self::MAX_SIDE / max($w, $h));

        if ($scale < 1) {
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
            [$w, $h] = [$nw, $nh];
        }

        // PNG stays PNG (transparency), everything else becomes JPEG at quality 85
        ob_start();
        if ($mime === 'image/png') {
            imagepng($src, null, 6);
            $out = ['png', 'image/png'];
        } else {
            imageinterlace($src, true);
            imagejpeg($src, null, 85);
            $out = ['jpg', 'image/jpeg'];
        }
        $data = ob_get_clean();
        imagedestroy($src);

        return [$data, $out[0], $w, $h, $out[1]];
    }
}
