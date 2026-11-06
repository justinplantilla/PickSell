<div class="grid-2">
    <div class="form-group">
        <label class="form-label">Product Name *</label>
        <input type="text" name="name" class="form-control" required>
    </div>
    <div class="form-group">
        @php
            $categories = ['Electronics', 'Fashion', 'Home & Living', 'Sports', 'Beauty', 'Food & Grocery', 'Books', 'Toys', 'Others'];
            $registeredCategory = auth()->user()->line_of_business;
            if ($registeredCategory && !in_array($registeredCategory, $categories, true)) {
                array_unshift($categories, $registeredCategory);
            }
            $selectedCategory = old('category', $registeredCategory);
        @endphp
        <label class="form-label">Category *</label>
        <select name="category" class="form-control" required>
            <option value="">Select category</option>
            @foreach($categories as $category)
                <option value="{{ $category }}" {{ $selectedCategory === $category ? 'selected' : '' }}>{{ $category }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control"></textarea>
</div>
<div class="grid-2">
    <div class="form-group">
        <label class="form-label">Price (₱) *</label>
        <input type="number" name="price" class="form-control" step="0.01" min="0" required>
    </div>
    <div class="form-group">
        <label class="form-label">Discount (%)</label>
        <input type="number" name="discount" class="form-control" step="0.01" min="0" max="100" value="0">
    </div>
</div>
<div class="grid-2">
    <div class="form-group">
        <label class="form-label">Voucher Code</label>
        <input type="text" name="voucher_code" class="form-control" placeholder="e.g. SAVE10">
    </div>
    <div class="form-group">
        <label class="form-label">Voucher Discount (%)</label>
        <input type="number" name="voucher_discount" class="form-control" step="0.01" min="0" max="100" value="0">
    </div>
</div>
<div class="grid-2">
    <div class="form-group">
        <label class="form-label">Stock *</label>
        <input type="number" name="stock" class="form-control" min="0" required>
    </div>
    <div class="form-group">
        <label class="form-label">Packed weight (kg) *</label>
        <input type="number" name="weight_kg" class="form-control" step="0.01" min="0.01" max="1000" required placeholder="e.g. 0.75">
        <small class="form-hint">One unit in its packaging. Shipping is priced on this.</small>
    </div>
</div>

<div class="form-group product-variations" data-product-variations>
    <input type="hidden" name="variations_managed" value="1" disabled data-variations-managed>
    <div class="product-gallery-head">
        <span class="form-label">Variations</span>
        <span class="product-gallery-count">Optional</span>
    </div>
    <div class="form-group product-variations-type" data-variation-type-wrap hidden>
        <label class="form-label" for="variation-type-{{ !empty($edit) ? 'edit' : 'add' }}">Option name *</label>
        <input type="text" id="variation-type-{{ !empty($edit) ? 'edit' : 'add' }}" name="variation_type" class="form-control" maxlength="50" placeholder="e.g. Size or Color" data-variation-type>
    </div>
    <div class="product-variation-rows" data-variation-rows></div>
    <button type="button" class="btn btn-outline btn-sm" data-add-variation>+ Add option</button>
    <small class="product-images-hint">For products sold in options, e.g. Size: S, M, L. Each option has its own stock (the product's stock becomes their total) and can have its own price; leave the price blank to use the product's.</small>
</div>

<div class="form-group product-gallery"data-product-gallery data-max="{{ \App\Models\Product::MAX_IMAGES }}" data-upload-url="{{ route('seller.inventory.images.store') }}" data-discard-url="{{ url('/seller/inventory/images') }}">
    <div class="product-gallery-head">
        <span class="form-label" id="gallery-label-{{ !empty($edit) ? 'edit' : 'add' }}">Product Images</span>
        <span class="product-gallery-count" data-gallery-count>0 / {{ \App\Models\Product::MAX_IMAGES }}</span>
    </div>
    <ul class="product-gallery-grid" data-gallery-grid aria-labelledby="gallery-label-{{ !empty($edit) ? 'edit' : 'add' }}"></ul>
    <input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden data-gallery-input>
    <div data-gallery-fields hidden></div>
    <small class="product-images-hint">Up to {{ \App\Models\Product::MAX_IMAGES }} images · JPG, PNG or WEBP · 2MB each. The first image is the cover unless you choose another. Use the arrow buttons to reorder.</small>
    <p class="product-gallery-message" data-gallery-message></p>
    <p class="product-gallery-sr" data-gallery-live role="status" aria-live="polite" aria-atomic="true"></p>
</div>
