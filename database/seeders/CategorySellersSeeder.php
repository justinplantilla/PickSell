<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * One approved seller per category, each with five active products, spread across Metro Manila
 * and CALABARZON. The email names the category and the town (electronics.makati@picksell.ph).
 *
 *   php artisan db:seed --class=CategorySellersSeeder
 *
 * Every seller signs in with Seller@1234. Products have no images; add them from the seller
 * dashboard. Safe to run again: sellers and products are matched by email and name and updated.
 */
class CategorySellersSeeder extends Seeder
{
    private const PASSWORD = 'Seller@1234';

    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->sellers() as $data) {
                $products = $data['products'];
                unset($data['products']);

                $seller = User::firstOrNew(['email' => $data['email']]);
                $seller->fill($data + [
                    'role' => 'seller',
                    'status' => 'approved',
                    'seller_type' => 'individual',
                    'password' => Hash::make(self::PASSWORD),
                ]);
                // Saving resolves the PSGC codes from the address (see User::booted).
                $seller->forceFill(['email_verified_at' => $seller->email_verified_at ?? now()])->save();

                if (! $seller->barangay_code) {
                    throw new RuntimeException("{$data['barangay']}, {$data['municipality']} is not in the location tables. Run php artisan pickrush:build-hubs --import first.");
                }

                foreach ($products as [$name, $price, $discount, $stock, $weight, $description]) {
                    Product::updateOrCreate(
                        ['seller_id' => $seller->id, 'name' => $name],
                        [
                            'category' => $seller->line_of_business,
                            'description' => $description,
                            'price' => $price,
                            'discount' => $discount,
                            'stock' => $stock,
                            'weight_kg' => $weight,
                            'voucher_code' => null,
                            'voucher_discount' => 0,
                            'is_featured' => false,
                            'status' => 'active',
                        ]
                    );
                }

                $this->command?->info("{$seller->business_name} ({$seller->email}) — {$seller->municipality}, {$seller->province}");
            }
        });

        $this->command?->info('Password for all: '.self::PASSWORD);
    }

    /** Products are [name, price, discount %, stock, weight kg, description]. */
    private function sellers(): array
    {
        return [
            [
                'email' => 'electronics.makati@picksell.ph',
                'business_name' => 'Makati Gadget Hub',
                'line_of_business' => 'Electronics',
                'store_description' => 'Everyday gadgets and accessories, shipped from Makati.',
                'first_name' => 'Paolo', 'last_name' => 'Santiago', 'middle_initial' => 'R', 'sex' => 'Male',
                'birthday' => '1993-04-12', 'age' => 33, 'contact_no' => '09175550101', 'tin' => '501-101-201-001',
                'province' => 'Metro Manila', 'municipality' => 'Makati', 'barangay' => 'Poblacion',
                'street' => 'J.P. Rizal Ave', 'house_no' => '5120', 'postal_code' => '1210',
                'pickup_lat' => 14.5649, 'pickup_lng' => 121.0313,
                'products' => [
                    ['True Wireless Earbuds', 1299, 15, 40, 0.20, 'Bluetooth 5.3 earbuds with a charging case and up to 24 hours of playback.'],
                    ['20000mAh Power Bank', 1499, 10, 30, 0.50, 'Dual USB-C fast-charging power bank with an LED battery display.'],
                    ['65W GaN Wall Charger', 999, 12, 35, 0.15, 'Compact three-port charger for laptops, tablets and phones.'],
                    ['Mechanical Gaming Keyboard', 2499, 18, 15, 1.10, 'RGB backlit keyboard with hot-swappable blue switches.'],
                    ['1080p Webcam with Mic', 1199, 10, 25, 0.30, 'Full HD webcam with a built-in microphone for calls and streaming.'],
                ],
            ],
            [
                'email' => 'fashion.pasig@picksell.ph',
                'business_name' => 'Kapitolyo Threads',
                'line_of_business' => 'Fashion',
                'store_description' => 'Casual everyday wear made for the Manila heat.',
                'first_name' => 'Bianca', 'last_name' => 'Mendoza', 'middle_initial' => 'L', 'sex' => 'Female',
                'birthday' => '1997-09-03', 'age' => 29, 'contact_no' => '09175550102', 'tin' => '501-101-201-002',
                'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'Kapitolyo',
                'street' => 'East Capitol Dr', 'house_no' => '27', 'postal_code' => '1603',
                'pickup_lat' => 14.5730, 'pickup_lng' => 121.0594,
                'products' => [
                    ['Oversized Cotton Tee', 399, 10, 60, 0.25, '100% cotton heavyweight tee in a relaxed oversized fit.'],
                    ['High-Waist Wide Leg Pants', 799, 15, 30, 0.45, 'Flowy wide leg pants with an elastic back waistband.'],
                    ['Linen Button-Down Shirt', 899, 12, 25, 0.30, 'Breathable linen blend shirt for warm days.'],
                    ['Pleated Midi Skirt', 649, 10, 20, 0.35, 'Satin-finish pleated skirt that goes from office to weekend.'],
                    ['Canvas Bucket Hat', 299, 5, 45, 0.10, 'Reversible bucket hat in washed canvas.'],
                ],
            ],
            [
                'email' => 'homeliving.marikina@picksell.ph',
                'business_name' => 'Marikina Home Nook',
                'line_of_business' => 'Home & Living',
                'store_description' => 'Practical home essentials for small Filipino homes.',
                'first_name' => 'Ramon', 'last_name' => 'Bautista', 'middle_initial' => 'D', 'sex' => 'Male',
                'birthday' => '1988-01-25', 'age' => 38, 'contact_no' => '09175550103', 'tin' => '501-101-201-003',
                'province' => 'Metro Manila', 'municipality' => 'Marikina', 'barangay' => 'Concepcion Uno',
                'street' => 'Bayan-Bayanan Ave', 'house_no' => '88', 'postal_code' => '1807',
                'pickup_lat' => 14.6502, 'pickup_lng' => 121.1029,
                'products' => [
                    ['Stackable Food Containers (Set of 5)', 549, 10, 40, 0.80, 'BPA-free airtight containers that nest for easy storage.'],
                    ['Microfiber Bath Towel', 399, 8, 50, 0.40, 'Quick-dry, extra absorbent towel, 70 x 140 cm.'],
                    ['Non-Stick Frying Pan 26cm', 899, 15, 20, 1.00, 'Granite-coated pan that works on gas and induction.'],
                    ['Collapsible Laundry Basket', 349, 5, 35, 0.60, 'Foldable basket with handles; flattens for storage.'],
                    ['Blackout Window Curtain', 699, 12, 25, 0.90, 'Thermal blackout curtain panel with grommet top, 140 x 220 cm.'],
                ],
            ],
            [
                'email' => 'beauty.mandaluyong@picksell.ph',
                'business_name' => 'Glow Lab Mandaluyong',
                'line_of_business' => 'Beauty',
                'store_description' => 'Skincare and beauty basics for everyday glow.',
                'first_name' => 'Patricia', 'last_name' => 'Gonzales', 'middle_initial' => 'A', 'sex' => 'Female',
                'birthday' => '1996-06-18', 'age' => 30, 'contact_no' => '09175550104', 'tin' => '501-101-201-004',
                'province' => 'Metro Manila', 'municipality' => 'Mandaluyong', 'barangay' => 'Plainview',
                'street' => 'Sierra Madre St', 'house_no' => '14', 'postal_code' => '1550',
                'pickup_lat' => 14.5770, 'pickup_lng' => 121.0395,
                'products' => [
                    ['Niacinamide Brightening Serum 30ml', 499, 10, 40, 0.10, '10% niacinamide serum for even skin tone and smaller-looking pores.'],
                    ['SPF 50 Daily Sunscreen Gel', 449, 8, 50, 0.10, 'Lightweight, non-greasy sunscreen gel for daily wear.'],
                    ['Lip & Cheek Tint', 249, 5, 60, 0.05, 'Buildable matte tint for lips and cheeks.'],
                    ['Gentle Foaming Cleanser 150ml', 349, 10, 45, 0.20, 'Low-pH cleanser that removes dirt without drying the skin.'],
                    ['Hydrating Sheet Mask (Pack of 10)', 399, 15, 30, 0.30, 'Hyaluronic acid sheet masks for a quick moisture boost.'],
                ],
            ],
            [
                'email' => 'sports.santarosa@picksell.ph',
                'business_name' => 'Sta. Rosa Sports Depot',
                'line_of_business' => 'Sports',
                'store_description' => 'Gear for runners, gym-goers and weekend players.',
                'first_name' => 'Miguel', 'last_name' => 'Castillo', 'middle_initial' => 'T', 'sex' => 'Male',
                'birthday' => '1991-11-07', 'age' => 35, 'contact_no' => '09175550105', 'tin' => '501-101-201-005',
                'province' => 'Laguna', 'municipality' => 'Santa Rosa', 'barangay' => 'Balibago',
                'street' => 'Old National Highway', 'house_no' => '210', 'postal_code' => '4026',
                'pickup_lat' => 14.2905, 'pickup_lng' => 121.1060,
                'products' => [
                    ['Adjustable Dumbbell 10kg', 1899, 10, 12, 10.50, 'Single dumbbell with removable plates, adjustable up to 10 kg.'],
                    ['Size 7 Basketball', 899, 12, 25, 0.70, 'Indoor/outdoor composite leather basketball.'],
                    ['Speed Jump Rope', 299, 5, 50, 0.20, 'Ball-bearing jump rope with an adjustable steel cable.'],
                    ['Badminton Racket Set', 1299, 15, 18, 0.90, 'Two aluminum rackets with three shuttlecocks and a carry bag.'],
                    ['Quick-Dry Running Shorts', 449, 10, 40, 0.15, 'Lightweight shorts with an inner liner and a zip pocket.'],
                ],
            ],
            [
                'email' => 'foodgrocery.lucena@picksell.ph',
                'business_name' => 'Lucena Pasalubong Pantry',
                'line_of_business' => 'Food & Grocery',
                'store_description' => 'Quezon province pasalubong and pantry staples.',
                'first_name' => 'Teresita', 'last_name' => 'Villareal', 'middle_initial' => 'C', 'sex' => 'Female',
                'birthday' => '1985-03-30', 'age' => 41, 'contact_no' => '09175550106', 'tin' => '501-101-201-006',
                'province' => 'Quezon', 'municipality' => 'Lucena', 'barangay' => 'Ibabang Dupay',
                'street' => 'Maharlika Highway', 'house_no' => '63', 'postal_code' => '4301',
                'pickup_lat' => 13.9447, 'pickup_lng' => 121.6234,
                'products' => [
                    ['Pancit Habhab Dried Noodles 1kg', 189, 0, 60, 1.05, 'Lucban-style dried miki noodles for authentic pancit habhab.'],
                    ['Uraro Arrowroot Cookies', 160, 5, 50, 0.30, 'Melt-in-your-mouth arrowroot cookies, a Quezon classic.'],
                    ['Macapuno Preserve Jar 340g', 220, 0, 40, 0.50, 'Sweet macapuno coconut strings in syrup, great for desserts.'],
                    ['Tablea Pure Cacao 250g', 180, 5, 45, 0.30, 'Pure roasted cacao tablets for tsokolate and champorado.'],
                    ['Organic Coco Sugar 500g', 210, 8, 35, 0.55, 'Low-GI coconut sap sugar from local farms.'],
                ],
            ],
            [
                'email' => 'books.bacoor@picksell.ph',
                'business_name' => 'Bacoor Book Nook',
                'line_of_business' => 'Books',
                'store_description' => 'Filipino reads, school references and journals.',
                'first_name' => 'Joshua', 'last_name' => 'Navarro', 'middle_initial' => 'E', 'sex' => 'Male',
                'birthday' => '1994-08-21', 'age' => 32, 'contact_no' => '09175550107', 'tin' => '501-101-201-007',
                'province' => 'Cavite', 'municipality' => 'Bacoor', 'barangay' => 'Molino III',
                'street' => 'Molino Blvd', 'house_no' => '402', 'postal_code' => '4102',
                'pickup_lat' => 14.4065, 'pickup_lng' => 120.9775,
                'products' => [
                    ['Noli Me Tangere (English Edition)', 395, 10, 30, 0.50, "Jose Rizal's classic novel in a modern English translation."],
                    ['English-Filipino Dictionary', 450, 5, 25, 0.80, 'Pocket-friendly bilingual dictionary for students.'],
                    ['Philippine History Reviewer', 520, 12, 20, 0.70, 'Concise reviewer for civil service and college entrance exams.'],
                    ["Children's Tagalog Storybook Set", 650, 15, 18, 1.00, 'Set of five illustrated Tagalog stories for young readers.'],
                    ['Hardcover Dotted Journal A5', 349, 8, 40, 0.40, '160-page dotted journal with a ribbon marker and elastic band.'],
                ],
            ],
            [
                'email' => 'toys.antipolo@picksell.ph',
                'business_name' => 'Antipolo Toy Chest',
                'line_of_business' => 'Toys',
                'store_description' => 'Fun and learning toys for kids of all ages.',
                'first_name' => 'Kristine', 'last_name' => 'Aquino', 'middle_initial' => 'P', 'sex' => 'Female',
                'birthday' => '1992-12-02', 'age' => 33, 'contact_no' => '09175550108', 'tin' => '501-101-201-008',
                'province' => 'Rizal', 'municipality' => 'Antipolo', 'barangay' => 'San Roque',
                'street' => 'M.L. Quezon St', 'house_no' => '19', 'postal_code' => '1870',
                'pickup_lat' => 14.5873, 'pickup_lng' => 121.1760,
                'products' => [
                    ['Building Blocks Set (500 pcs)', 999, 15, 20, 1.20, 'Compatible bricks with a storage box for endless builds.'],
                    ['Remote Control Off-Road Car', 1499, 12, 15, 1.10, 'Rechargeable 4WD RC car with a 2.4 GHz remote.'],
                    ['Wooden Shape Sorter Puzzle', 449, 10, 30, 0.60, 'Montessori-style shape sorter for toddlers.'],
                    ['Plush Carabao Stuffed Toy', 399, 5, 35, 0.35, 'Soft and huggable carabao plush, 30 cm.'],
                    ['Kids Art & Craft Kit', 599, 10, 25, 0.90, 'Crayons, markers, clay and stickers in one carry case.'],
                ],
            ],
        ];
    }
}
