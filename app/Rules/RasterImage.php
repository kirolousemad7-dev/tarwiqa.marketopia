<?php

namespace App\Rules;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;

class RasterImage
{
    /**
     * @return array<int, mixed>
     */
    public static function rule(bool $required = false, string $max = '5mb'): array
    {
        return [
            $required ? 'required' : 'nullable',
            File::types(['jpg', 'jpeg', 'png', 'webp'])->max($max),
            self::content(),
        ];
    }

    public static function content(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $mime = strtolower((string) $value->getMimeType());
            $head = strtolower((string) file_get_contents($value->getRealPath(), false, null, 0, 1024));
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];

            if (! in_array($mime, $allowed, true) || str_contains($mime, 'svg') || str_contains($head, '<svg')) {
                $fail('The '.$attribute.' must be a JPEG, PNG, or WebP image.');
            }
        };
    }
}
