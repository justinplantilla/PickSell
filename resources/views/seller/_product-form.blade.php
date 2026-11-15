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
{{-- A scheduled sale: between the two dates its discount replaces the regular one. --}}
<details class="product-sale" data-product-sale>
    <summary class="form-label">Schedule a sale <span class="cart-muted-text">(optional)</span></summary>
    <div class="grid-3">
        <div class="form-group">
            <label class="form-label" for="sale-discount-{{ !empty($edit) ? 'edit' : 'add' }}">Sale discount (%)</label>
            <input type="number" id="sale-discount-{{ !empty($edit) ? 'edit' : 'add' }}" name="sale_discount" class="form-control" step="0.01" min="1" max="100" placeholder="e.g. 20">
        </div>
        <div class="form-group">
            <label class="form-label" for="sale-start-{{ !empty($edit) ? 'edit' : 'add' }}">Starts</label>
            <input type="datetime-local" id="sale-start-{{ !empty($edit) ? 'edit' : 'add' }}" name="sale_starts_at" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label" for="sale-end-{{ !empty($edit) ? 'edit' : 'add' }}">Ends</label>
            <input type="datetime-local" id="sale-end-{{ !empty($edit) ? 'edit' : 'add' }}" name="sale_ends_at" class="form-control">
        </div>
    </div>
    <small class="form-hint">During the sale buyers get this discount instead of the regular one. It starts and ends by itself; leave the discount empty for no sale.</small>
</details>
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
        <small class="form-hint" data-variation-stock-hint hidden>This is now the total of the options' stock. Set each option's stock under Variations.</small>
    </div>
    <div class="form-group">
        <label class="form-label">Packed weight (kg) *</label>
        <input type="number" name="weight_kg" class="form-control" step="0.01" min="0.01" max="1000" required placeholder="e.g. 0.75">
        <small class="form-hint">One unit in its packaging. Shipping is priced on this.</small>
    </div>
    <div class="form-group">
        <label class="form-label">Pre-order (days to ship)</label>
        <input type="number" name="preorder_days" class="form-control" min="{{ \App\Models\Order::PREORDER_MIN_DAYS }}" max="{{ \App\Models\Order::PREORDER_MAX_DAYS }}" step="1" placeholder="Leave empty if in stock">
        <small class="form-hint">Only for made-to-order or incoming stock: buyers see it is a pre-order. Otherwise you have {{ \App\Models\Order::SHIP_WITHIN_HOURS }} hours from preparing to hand the parcel to the courier, or the order is cancelled automatically.</small>
    </div>
</div>

<div class="form-group product-variations" data-product-variations data-max-names="{{ \App\Models\ProductVariation::MAX_OPTION_NAMES }}" data-max-values="{{ \App\Models\ProductVariation::MAX_VALUES_PER_OPTION }}" data-max-combinations="{{ \App\Models\ProductVariation::MAX_COMBINATIONS }}">
    <input type="hidden" name="variations_managed" value="1" disabled data-variations-managed>
    <div class="product-gallery-head">
        <span class="form-label">Variations</span>
        <span class="product-gallery-count">Optional</span>
    </div>
    <div class="option-name-blocks" data-option-names></div>
    <button type="button" class="btn btn-outline btn-sm" data-add-option-name>+ Add option (e.g. Color or Size)</button>
    <div class="variation-combos" data-combos hidden>
        <div class="product-gallery-head">
            <span class="form-label">Combinations</span>
            <span class="product-gallery-count" data-combo-count></span>
        </div>
        <div class="variation-bulk">
            <span>Set all</span>
            <input type="number" class="form-control" min="0" step="0.01" placeholder="Price" aria-label="Price for every combination" data-apply-price>
            <input type="number" class="form-control" min="0" placeholder="Stock" aria-label="Stock for every combination" data-apply-stock>
            <button type="button" class="btn btn-outline btn-sm" data-apply-all>Apply</button>
        </div>
        <div class="product-variation-head" aria-hidden="true"><span>Combination</span><span>Price (₱)</span><span>Stock</span></div>
        <div class="product-variation-rows" data-variation-rows></div>
    </div>
    <p class="product-variations-message" role="status" data-variations-message></p>
    <small class="product-images-hint">For products sold in options, e.g. Color and Size: up to {{ \App\Models\ProductVariation::MAX_OPTION_NAMES }} options with up to {{ \App\Models\ProductVariation::MAX_VALUES_PER_OPTION }} values each. Every combination (Red / M) gets its own stock, and the product's stock becomes their total. Leave a price blank to use the product's.</small>
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
