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
        <label class="form-label">Product Image</label>
        <input type="file" name="image" class="form-control" accept="image/*">
    </div>
</div>
