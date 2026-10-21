@if($errors->any())<div class="catalog-notice danger" role="alert"><strong>Please correct the form:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<aside class="surface-card" id="product-preview"><h2>Live preview</h2><strong data-preview-title>{{ $product?->title ?: 'Product name' }}</strong><p data-preview-price>{{ $product?->base_price ?? '0.00' }} EGP</p><p data-preview-category>Category</p><p data-preview-status>Active</p></aside>
<script>
document.currentScript.closest('form')?.addEventListener('input', (event) => {
    const form = event.currentTarget;
    const title = form.querySelector('[name=title]')?.value || 'Product name';
    const price = form.querySelector('[name=base_price]')?.value || '0.00';
    const category = form.querySelector('[name=category_id]')?.selectedOptions?.[0]?.textContent || 'Category';
    const active = form.querySelector('[name=is_active][type=checkbox]')?.checked;
    const preview = document.getElementById('product-preview');
    if (!preview) return;
    preview.querySelector('[data-preview-title]').textContent = title;
    preview.querySelector('[data-preview-price]').textContent = `${price} EGP`;
    preview.querySelector('[data-preview-category]').textContent = category;
    preview.querySelector('[data-preview-status]').textContent = active ? 'Active' : 'Inactive';
});
</script>
<div class="service-form-grid"><label class="field"><span>Category</span><select name="category_id"><option value="">Uncategorized</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id', $product?->category_id) === (string)$category->id)>{{ $category->title }}</option>@endforeach</select></label><label class="field"><span>City</span><select name="city_id"><option value="">Available in all cities</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected((string)old('city_id', $product?->city_id) === (string)$city->id)>{{ $city->name }}</option>@endforeach</select></label><label class="field"><span>Title</span><input name="title" value="{{ old('title', $product?->title) }}" maxlength="160" required></label><label class="field"><span>Slug</span><input name="slug" value="{{ old('slug', $product?->slug) }}" placeholder="Generated from title"></label><label class="field"><span>Base price (EGP)</span><input name="base_price" type="number" min="0" step="0.01" value="{{ old('base_price', $product?->base_price) }}" required></label><label class="field"><span>YouTube URL</span><input name="youtube_url" type="url" value="{{ old('youtube_url', $product?->youtube_url) }}" placeholder="https://youtube.com/watch?v=..."></label><label class="field wide"><span>Description</span><textarea name="description" rows="5">{{ old('description', $product?->description) }}</textarea></label><label class="field wide image-upload-field"><span>Product logo</span><input name="logo" type="file" accept="image/jpeg,image/png,image/webp"><small>JPEG, PNG or WebP. Maximum 5 MB.</small></label><label class="field wide"><span>Availability</span><span class="switch-row"><input name="is_active" type="hidden" value="0"><input name="is_active" type="checkbox" value="1" @checked((bool)old('is_active', $product?->is_active ?? true))><span>Active and visible to customers</span></span></label></div><div class="form-actions catalog-form-actions"><a class="ui-button ghost" href="{{ route('admin.products.index') }}">Cancel</a><button class="ui-button primary" type="submit">Save Product</button></div>
