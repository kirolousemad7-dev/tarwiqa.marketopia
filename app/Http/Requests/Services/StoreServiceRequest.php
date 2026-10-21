<?php

namespace App\Http\Requests\Services;

use App\Rules\RasterImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return can_access_page('services.manage');
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', Rule::exists('service_categories', 'id')->whereNull('deleted_at')],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', 'unique:services,slug'],
            'description' => ['nullable', 'string', 'max:5000'],
            'base_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_active' => ['required', 'boolean'],
            'logo' => RasterImage::rule(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $title = trim((string) $this->input('title'));
        $this->merge([
            'title' => $title,
            'slug' => Str::slug($this->input('slug') ?: $title),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
