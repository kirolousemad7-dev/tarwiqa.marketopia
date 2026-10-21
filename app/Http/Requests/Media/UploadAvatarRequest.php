<?php

namespace App\Http\Requests\Media;

use App\Rules\RasterImage;
use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return can_access_page('profile.media.manage');
    }

    public function rules(): array
    {
        return [
            'avatar' => RasterImage::rule(required: true),
        ];
    }
}
