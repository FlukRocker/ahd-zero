<?php

namespace App\Services\Chat;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Chat image attachments.
 *
 * The upload is never trusted: type comes from the bytes (finfo), dimensions are
 * capped BEFORE GD decodes anything, and the image is always re-encoded, which
 * drops EXIF and any polyglot payload (and flattens animated GIFs to one frame).
 * The stored name is server-random. Files live on the private disk and are
 * served by ChatUploadController, never straight from the web root.
 *
 * Stored bodies reference an image as `[img]<name>[/img]`. Users may not type
 * that tag (the controller rejects it), so every such token was appended here.
 */
class ChatImage
{
    public const MAX_BYTES = 3 * 1024 * 1024;

    public const MAX_SIDE = 4096;

    public const MAX_PIXELS = 16_000_000;

    /** Longest side stored; bigger uploads are scaled down. */
    public const STORE_SIDE = 1280;

    public const NAME_PATTERN = '[a-f0-9]{32}\.(?:webp|jpg)';

    private const DIR = 'chat-uploads';

    private const TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** @throws InvalidArgumentException with a user-facing message */
    public function store(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if ($path === false || $file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('รูปใหญ่เกินไป (สูงสุด 3 MB)');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! in_array($mime, self::TYPES, true)) {
            throw new InvalidArgumentException('รองรับเฉพาะรูป JPG, PNG, GIF, WebP');
        }

        $info = @getimagesize($path);

        if ($info === false || $info[0] < 1 || $info[1] < 1
            || $info[0] > self::MAX_SIDE || $info[1] > self::MAX_SIDE || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('ขนาดรูปไม่ถูกต้องหรือใหญ่เกินไป');
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            default => @imagecreatefromwebp($path),
        };

        if ($source === false) {
            throw new InvalidArgumentException('อ่านไฟล์รูปไม่ได้');
        }

        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::STORE_SIDE / max($width, $height));
        $canvas = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));

        // Flatten transparency onto white so the JPEG fallback has no black fringe.
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, imagesx($canvas), imagesy($canvas), $width, $height);

        $webp = function_exists('imagewebp');
        $name = bin2hex(random_bytes(16)).($webp ? '.webp' : '.jpg');

        ob_start();
        $webp ? imagewebp($canvas, null, 82) : imagejpeg($canvas, null, 85);
        $bytes = (string) ob_get_clean();

        Storage::disk('local')->put(self::DIR.'/'.$name, $bytes);

        return $name;
    }

    public static function token(string $name): string
    {
        return '[img]'.$name.'[/img]';
    }

    /** @return array{0: string, 1: ?string} body without the token, and the image file name */
    public static function split(string $body): array
    {
        if (preg_match('~\[img\]('.self::NAME_PATTERN.')\[/img\]~', $body, $m) !== 1) {
            return [$body, null];
        }

        return [trim(str_replace($m[0], '', $body)), $m[1]];
    }

    public static function url(string $name): string
    {
        return '/chat-uploads/'.$name;
    }

    public function delete(string $body): void
    {
        [, $name] = self::split($body);

        if ($name !== null) {
            Storage::disk('local')->delete(self::DIR.'/'.$name);
        }
    }

    public function path(string $name): ?string
    {
        if (preg_match('~^'.self::NAME_PATTERN.'$~', $name) !== 1) {
            return null;
        }

        $relative = self::DIR.'/'.$name;

        return Storage::disk('local')->exists($relative) ? Storage::disk('local')->path($relative) : null;
    }
}
