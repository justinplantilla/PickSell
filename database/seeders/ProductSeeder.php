<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $seller = User::where('role', 'seller')
            ->where('status', 'approved')
            ->where('email', 'seller@picksell.ph')
            ->first();

        if (!$seller) {
            $this->command->warn('Approved demo seller not found. Skipping product seeder.');
            return;
        }

        Product::where('seller_id', $seller->id)
            ->whereIn('name', [
                'USB-C Fast Charging Hub',
                'Everyday Canvas Tote Bag',
                'Compact Air Fryer 3.5L',
                'Insulated Gym Duffel Bag',
            ])
            ->delete();

        $products = [
            ['name' => 'Wireless Noise-Cancelling Headphones', 'category' => 'Electronics', 'price' => 3499, 'discount' => 20, 'stock' => 18, 'image' => 'products/electronics-headphones.jpg', 'is_featured' => true],
            ['name' => 'Smart Watch Series 5', 'category' => 'Electronics', 'price' => 2890, 'discount' => 15, 'stock' => 24, 'image' => 'products/electronics-watch.jpg', 'is_featured' => true],
            ['name' => 'Ergonomic Wireless Mouse', 'category' => 'Electronics', 'price' => 899, 'discount' => 30, 'stock' => 35, 'image' => 'products/electronics-mouse.jpg', 'is_featured' => true],
            ['name' => 'Compact Laptop Workstation', 'category' => 'Electronics', 'price' => 1190, 'discount' => 8, 'stock' => 31, 'image' => 'products/electronics-charger.jpg'],
            ['name' => 'Portable Bluetooth Speaker', 'category' => 'Electronics', 'price' => 1590, 'discount' => 12, 'stock' => 20, 'image' => 'products/electronics-speaker.jpg'],
            ['name' => 'Everyday Training Sneakers', 'category' => 'Fashion', 'price' => 650, 'discount' => 10, 'stock' => 42, 'image' => 'products/fashion-tote.jpg', 'is_featured' => true],
            ['name' => 'Linen Blend Oversized Shirt', 'category' => 'Fashion', 'price' => 899, 'discount' => 15, 'stock' => 26, 'image' => 'products/fashion-shirt.jpg'],
            ['name' => 'Classic Leather Crossbody Bag', 'category' => 'Fashion', 'price' => 1490, 'discount' => 18, 'stock' => 14, 'image' => 'products/fashion-bag.jpg'],
            ['name' => 'Lightweight Training Sneakers', 'category' => 'Fashion', 'price' => 2390, 'discount' => 15, 'stock' => 14, 'image' => 'products/fashion-sneakers.jpg', 'is_featured' => true],
            ['name' => 'Minimalist Cotton Cap', 'category' => 'Fashion', 'price' => 450, 'discount' => 10, 'stock' => 30, 'image' => 'products/fashion-cap.jpg'],
            ['name' => 'Portable Espresso Coffee Maker', 'category' => 'Home & Living', 'price' => 2199, 'discount' => 10, 'stock' => 12, 'image' => 'products/home-coffee.jpg', 'is_featured' => true],
            ['name' => 'Portable Fruit Blender', 'category' => 'Home & Living', 'price' => 4250, 'discount' => 25, 'stock' => 9, 'image' => 'products/home-air-fryer.jpg'],
            ['name' => 'Minimalist Desk Lamp', 'category' => 'Home & Living', 'price' => 1250, 'discount' => 12, 'stock' => 20, 'image' => 'products/home-lamp.jpg'],
            ['name' => 'Classic Ceramic Mug Set', 'category' => 'Home & Living', 'price' => 780, 'discount' => 18, 'stock' => 27, 'image' => 'products/home-mugs.jpg'],
            ['name' => 'Woven Storage Basket', 'category' => 'Home & Living', 'price' => 990, 'discount' => 10, 'stock' => 22, 'image' => 'products/home-basket.jpg'],
            ['name' => 'Hydrating Facial Care Set', 'category' => 'Beauty', 'price' => 1590, 'discount' => 22, 'stock' => 16, 'image' => 'products/beauty-skincare.jpg', 'is_featured' => true],
            ['name' => 'Daily Glow Face Serum', 'category' => 'Beauty', 'price' => 699, 'discount' => 10, 'stock' => 25, 'image' => 'products/beauty-serum.jpg'],
            ['name' => 'Natural Makeup Brush Set', 'category' => 'Beauty', 'price' => 890, 'discount' => 15, 'stock' => 19, 'image' => 'products/beauty-brushes.jpg'],
            ['name' => 'Aloe Soothing Body Lotion', 'category' => 'Beauty', 'price' => 540, 'discount' => 8, 'stock' => 28, 'image' => 'products/beauty-lotion.jpg'],
            ['name' => 'Fresh Citrus Fragrance Mist', 'category' => 'Beauty', 'price' => 780, 'discount' => 12, 'stock' => 21, 'image' => 'products/beauty-fragrance.jpg'],
            ['name' => 'Stainless Steel Water Bottle', 'category' => 'Sports', 'price' => 990, 'discount' => 20, 'stock' => 25, 'image' => 'products/sports-bottle.jpg', 'is_featured' => true],
            ['name' => 'Adjustable Yoga Mat', 'category' => 'Sports', 'price' => 720, 'discount' => 15, 'stock' => 18, 'image' => 'products/sports-yoga.jpg'],
            ['name' => 'Everyday Running Shoes', 'category' => 'Sports', 'price' => 2390, 'discount' => 15, 'stock' => 14, 'image' => 'products/sports-running.jpg'],
            ['name' => 'Everyday Sports Backpack', 'category' => 'Sports', 'price' => 1290, 'discount' => 10, 'stock' => 17, 'image' => 'products/sports-duffel.jpg'],
            ['name' => 'Resistance Band Set', 'category' => 'Sports', 'price' => 590, 'discount' => 10, 'stock' => 32, 'image' => 'products/sports-bands.jpg'],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['seller_id' => $seller->id, 'name' => $product['name']],
                array_merge($product, [
                    'seller_id' => $seller->id,
                    'description' => 'A quality PickSell find from our demo catalog.',
                    'voucher_code' => null,
                    'voucher_discount' => 0,
                    'image' => $product['image'],
                    'is_featured' => $product['is_featured'] ?? false,
                    'status' => 'active',
                ])
            );
        }

        $this->command->info(count($products) . ' demo products seeded.');
    }
}
