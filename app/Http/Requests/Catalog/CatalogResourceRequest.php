<?php

namespace App\Http\Requests\Catalog;

use App\Rules\RasterImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CatalogResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return can_access_page('catalog.manage');
    }

    public function rules(): array
    {
        $resource = $this->route('resource');
        $table = match ($resource) {
            'service-types' => 'service_types',
            'service-categories' => 'service_categories',
            'packages' => 'packages',
            default => abort(404),
        };
        $id = $this->route('id');
        $rules = [
            'governorate_id' => ['nullable', 'integer', Rule::exists('governorates', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', Rule::unique($table, 'slug')->ignore($id)],
            'is_active' => ['required', 'boolean'],
        ];

        if ($resource !== 'packages') {
            $rules['subtitle'] = ['nullable', 'string', 'max:255'];
            $rules['logo'] = RasterImage::rule();
        }
        if ($resource === 'service-types') {
            $rules['widget_type'] = ['nullable', Rule::in(['core_services', 'commerce', 'support', 'payments'])];
            $rules['visibility'] = ['nullable', Rule::in(['public', 'internal', 'pilot_only'])];
            $rules['primary_owner'] = ['nullable', 'string', 'max:255'];
            $rules['sla_group'] = ['nullable', 'string', 'max:255'];
            $rules['description'] = ['nullable', 'string', 'max:5000'];
        }
        if ($resource === 'service-categories') {
            $rules['type_id'] = ['nullable', 'integer', Rule::exists('service_types', 'id')->whereNull('deleted_at')];
        }
        if ($resource === 'packages') {
            $rules += [
                'description' => ['nullable', 'string', 'max:5000'],
                'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
                'discount_value' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
                'discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
                'starts_at' => ['nullable', 'date'],
                'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            ];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $title = trim((string) $this->input('title'));
        $this->merge([
            'title' => $title,
            'subtitle' => filled($this->input('subtitle')) ? trim((string) $this->input('subtitle')) : null,
            'slug' => Str::slug($this->input('slug') ?: $title),
            'is_active' => $this->boolean('is_active'),
            'discount_value' => $this->input('discount_value', 0),
        ]);
    }
}
