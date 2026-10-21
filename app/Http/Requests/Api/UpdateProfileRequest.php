<?php

namespace App\Http\Requests\Api;

use App\Rules\RasterImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user())],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($this->user())],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female', 'other'])],
            'dob' => ['sometimes', 'nullable', 'date', 'before:today'],
            'governorate_id' => ['sometimes', 'nullable', 'integer', 'exists:governorates,id'],
            'locale' => ['sometimes', Rule::in(['en', 'ar'])], 'timezone' => ['sometimes', 'timezone'],
            'avatar' => ['sometimes', ...RasterImage::rule()],
        ];
    }
}
