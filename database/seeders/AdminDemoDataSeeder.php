<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\BranchRider;
use App\Models\Complaint;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\LogisticsBranch;
use App\Models\LogisticsException;
use App\Models\Municipality;
use App\Models\Order;
use App\Models\ParcelScan;
use App\Models\Product;
use App\Models\ProductModerationLog;
use App\Models\ProductReview;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Models\RiderBarangay;
use App\Models\User;
use App\Services\Orders\OrderLifecycleService;
use App\Services\Finance\FinancialLedgerService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminDemoDataSeeder extends Seeder
{
    private User $admin;

    private array $users = [];

    private array $locations = [];

    private array $branches = [];

    private array $products = [];

    public function run(): void
    {
        $productionSeedExplicitlyAllowed = app()->environment('production')
            && filter_var(getenv('ADMIN_DEMO_DATA_ALLOW_PRODUCTION'), FILTER_VALIDATE_BOOLEAN);

        if (! app()->environment(['local', 'testing']) && ! $productionSeedExplicitlyAllowed) {
            throw new \LogicException(
                'Admin demo data can only be seeded in local/testing, or in production with ADMIN_DEMO_DATA_ALLOW_PRODUCTION=true.',
            );
        }

        DB::transaction(function (): void {
            $this->seedUsers();
            $this->seedLocationsAndCoverage();
            $this->seedProducts();
            $orders = $this->seedOrders();
            $this->seedReturnsAndRefunds($orders);
            $this->seedComplaintsAndExceptions($orders);
            $this->seedReviewsAndMessages($orders);
            $this->seedModerationHistory();
            $this->setDemoInventory($orders);
        });

        $this->command?->info('Admin demo data is ready.');
    }

    private function seedUsers(): void
    {
        $this->admin = $this->user('admin@picksell.ph', [
            'role' => 'admin',
            'status' => 'approved',
            'first_name' => 'PickSell',
            'last_name' => 'Admin',
            'sex' => 'Male',
            'birthday' => '1990-01-01',
            'province' => 'Metro Manila',
            'municipality' => 'Makati',
            'barangay' => 'Poblacion',
            'contact_no' => '09000000000',
            'password' => 'Admin@1234',
        ]);

        $this->users = [
            'seller_pasig' => $this->user('seller.pasig@demo.picksell.test', [
                'role' => 'seller', 'status' => 'approved', 'first_name' => 'Mika',
                'last_name' => 'Santos', 'sex' => 'Female', 'birthday' => '1992-06-14',
                'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
                'street' => 'A. Mabini Street', 'house_no' => '18', 'contact_no' => '09171230001',
                'business_name' => 'Mika Home & Finds', 'line_of_business' => 'Home & Living',
                'password' => 'DemoUser@1234',
            ]),
            'seller_taguig' => $this->user('seller.taguig@demo.picksell.test', [
                'role' => 'seller', 'status' => 'approved', 'first_name' => 'Paolo',
                'last_name' => 'Garcia', 'sex' => 'Male', 'birthday' => '1988-03-08',
                'province' => 'Metro Manila', 'municipality' => 'Taguig', 'barangay' => 'Central Signal Village',
                'street' => 'General Luna Street', 'house_no' => '27', 'contact_no' => '09171230002',
                'business_name' => 'Southline Gadgets', 'line_of_business' => 'Electronics',
                'password' => 'DemoUser@1234',
            ]),
            'buyer_pasig' => $this->user('buyer.pasig@demo.picksell.test', [
                'role' => 'buyer', 'status' => 'approved', 'first_name' => 'Andrea',
                'last_name' => 'Reyes', 'sex' => 'Female', 'birthday' => '1998-11-20',
                'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
                'street' => 'Mercedes Avenue', 'house_no' => '42', 'contact_no' => '09171230003',
                'password' => 'DemoUser@1234',
            ]),
            'buyer_taguig' => $this->user('buyer.taguig@demo.picksell.test', [
                'role' => 'buyer', 'status' => 'approved', 'first_name' => 'Luis',
                'last_name' => 'Mendoza', 'sex' => 'Male', 'birthday' => '1996-02-11',
                'province' => 'Metro Manila', 'municipality' => 'Taguig', 'barangay' => 'Central Signal Village',
                'street' => 'M. L. Quezon Street', 'house_no' => '63', 'contact_no' => '09171230004',
                'password' => 'DemoUser@1234',
            ]),
            'logistics_pasig' => $this->user('logistics.pasig@demo.picksell.test', [
                'role' => 'logistics', 'status' => 'approved', 'first_name' => 'Ramon',
                'last_name' => 'Villanueva', 'sex' => 'Male', 'birthday' => '1985-05-17',
                'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
                'contact_no' => '09171230005', 'password' => 'DemoUser@1234',
            ]),
            'logistics_taguig' => $this->user('logistics.taguig@demo.picksell.test', [
                'role' => 'logistics', 'status' => 'approved', 'first_name' => 'Lea',
                'last_name' => 'Navarro', 'sex' => 'Female', 'birthday' => '1989-09-03',
                'province' => 'Metro Manila', 'municipality' => 'Taguig', 'barangay' => 'Central Signal Village',
                'contact_no' => '09171230006', 'password' => 'DemoUser@1234',
            ]),
            'rider_pasig' => $this->user('rider.pasig@demo.picksell.test', [
                'role' => 'courier', 'status' => 'approved', 'first_name' => 'Jules',
                'last_name' => 'Aquino', 'sex' => 'Male', 'birthday' => '1994-12-02',
                'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'San Miguel',
                'contact_no' => '09171230007', 'vehicle_type' => 'Motorcycle',
                'plate_number' => 'DEMO 1001', 'delivery_area' => 'Pasig, Metro Manila',
                'password' => 'DemoUser@1234',
            ]),
            'rider_taguig' => $this->user('rider.taguig@demo.picksell.test', [
                'role' => 'courier', 'status' => 'approved', 'first_name' => 'Nina',
                'last_name' => 'Flores', 'sex' => 'Female', 'birthday' => '1997-04-19',
                'province' => 'Metro Manila', 'municipality' => 'Taguig', 'barangay' => 'Central Signal Village',
                'contact_no' => '09171230008', 'vehicle_type' => 'Motorcycle',
                'plate_number' => 'DEMO 1002', 'delivery_area' => 'Taguig, Metro Manila',
                'password' => 'DemoUser@1234',
            ]),
            'pending_seller' => $this->user('pending.seller@demo.picksell.test', [
                'role' => 'seller', 'status' => 'pending', 'first_name' => 'Carla',
                'last_name' => 'Domingo', 'sex' => 'Female', 'birthday' => '1993-07-12',
                'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'Kapitolyo',
                'contact_no' => '09171230009', 'business_name' => 'Little Market Studio',
                'line_of_business' => 'Fashion', 'password' => 'DemoUser@1234',
            ]),
            'pending_logistics' => $this->user('pending.logistics@demo.picksell.test', [
                'role' => 'logistics', 'status' => 'pending', 'first_name' => 'Marco',
                'last_name' => 'Lim', 'sex' => 'Male', 'birthday' => '1991-01-25',
                'province' => 'Metro Manila', 'municipality' => 'Pasig', 'barangay' => 'Rosario',
                'contact_no' => '09171230010', 'password' => 'DemoUser@1234',
            ]),
            'pending_rider' => $this->user('pending.rider@demo.picksell.test', [
                'role' => 'courier', 'status' => 'pending', 'first_name' => 'Arvin',
                'last_name' => 'Cruz', 'sex' => 'Male', 'birthday' => '1995-08-30',
                'province' => 'Metro Manila', 'municipality' => 'Taguig', 'barangay' => 'Central Signal Village',
                'contact_no' => '09171230011', 'vehicle_type' => 'Motorcycle',
                'plate_number' => 'DEMO 1003', 'delivery_area' => 'Taguig, Metro Manila',
                'password' => 'DemoUser@1234',
            ]),
        ];
    }

    private function user(string $email, array $attributes): User
    {
        $password = $attributes['password'];
        unset($attributes['password']);

        return User::firstOrCreate(
            ['email' => $email],
            $attributes + [
                'password' => Hash::make($password),
                'middle_initial' => null,
                'age' => 30,
                'id_upload' => null,
                'business_permit' => null,
                'or_cr_upload' => null,
            ],
        );
    }

    private function seedLocationsAndCoverage(): void
    {
        foreach (['Pasig', 'Taguig'] as $city) {
            $municipality = Municipality::firstOrCreate([
                'province' => 'Metro Manila',
                'name' => $city,
            ]);
            $this->locations[$city]['municipality'] = $municipality;

            $manager = $this->users['logistics_'.strtolower($city)];
            $this->branches[$city] = LogisticsBranch::firstOrCreate(
                ['municipality_id' => $municipality->id, 'name' => $city.' Demo Hub'],
                ['logistics_id' => $manager->id, 'address' => 'Operations Center, '.$city, 'status' => 'active'],
            );
        }

        $barangayNames = [
            'Pasig' => ['San Miguel', 'Kapitolyo', 'Pinagbuhatan'],
            'Taguig' => ['Central Signal Village', 'Ususan'],
        ];
        foreach ($barangayNames as $city => $names) {
            foreach ($names as $name) {
                $this->locations[$city]['barangays'][$name] = Barangay::firstOrCreate([
                    'municipality_id' => $this->locations[$city]['municipality']->id,
                    'name' => $name,
                ]);
            }
        }

        foreach ([
            ['Pasig', 'rider_pasig', ['San Miguel', 'Kapitolyo']],
            ['Taguig', 'rider_taguig', ['Central Signal Village', 'Ususan']],
        ] as [$city, $riderKey, $barangays]) {
            $assignment = BranchRider::firstOrCreate(
                ['branch_id' => $this->branches[$city]->id, 'user_id' => $this->users[$riderKey]->id],
                ['status' => 'active'],
            );
            foreach ($barangays as $index => $name) {
                RiderBarangay::firstOrCreate(
                    ['branch_rider_id' => $assignment->id, 'barangay_id' => $this->locations[$city]['barangays'][$name]->id],
                    ['is_primary' => $index === 0],
                );
            }
        }

        foreach ($this->users as $key => $user) {
            $city = str_contains($key, 'taguig') || $key === 'pending_rider' ? 'Taguig' : 'Pasig';
            $barangayName = $user->barangay ?: ($city === 'Pasig' ? 'San Miguel' : 'Central Signal Village');
            $barangay = $this->locations[$city]['barangays'][$barangayName] ?? null;
            if ($barangay) {
                $user->forceFill([
                    'municipality_id' => $this->locations[$city]['municipality']->id,
                    'barangay_id' => $barangay->id,
                ])->saveQuietly();
            }
        }
    }

    private function seedProducts(): void
    {
        $definitions = [
            ['seller_pasig', 'Woven Storage Basket', 'Home & Living', 990, 22],
            ['seller_pasig', 'Minimalist Desk Lamp', 'Home & Living', 1250, 18],
            ['seller_taguig', 'Wireless Noise-Cancelling Headphones', 'Electronics', 3499, 16],
            ['seller_taguig', 'Portable Bluetooth Speaker', 'Electronics', 1590, 25],
        ];

        foreach ($definitions as [$sellerKey, $name, $category, $price, $stock]) {
            $this->products[$name] = Product::firstOrCreate(
                ['seller_id' => $this->users[$sellerKey]->id, 'name' => $name],
                [
                    'description' => 'Demo catalog item used to preview connected marketplace and admin workflows.',
                    'category' => $category,
                    'price' => $price,
                    'discount' => 0,
                    'voucher_discount' => 0,
                    'stock' => $stock,
                    'status' => 'active',
                    'is_featured' => true,
                ],
            );
        }

        $archived = Product::firstOrCreate(
            ['seller_id' => $this->users['seller_pasig']->id, 'name' => 'Archived Demo Listing'],
            [
                'description' => 'Archived listing for previewing the admin moderation queue.',
                'category' => 'Home & Living',
                'price' => 750,
                'discount' => 0,
                'voucher_discount' => 0,
                'stock' => 4,
                'status' => 'archived',
                'is_featured' => false,
            ],
        );
        $this->products['archived'] = $archived;
    }

    private function seedOrders(): array
    {
        $definitions = [
            ['DEMO-2026-001', 'placed', 'Woven Storage Basket', 'buyer_pasig', 'Pasig', 'San Miguel', null, 3],
            ['DEMO-2026-002', 'preparing', 'Minimalist Desk Lamp', 'buyer_pasig', 'Pasig', 'Kapitolyo', null, 20],
            ['DEMO-2026-003', 'ready_for_pickup', 'Portable Bluetooth Speaker', 'buyer_taguig', 'Taguig', 'Ususan', null, 30],
            ['DEMO-2026-004', 'picked_up', 'Woven Storage Basket', 'buyer_pasig', 'Pasig', 'San Miguel', null, 74],
            ['DEMO-2026-005', 'at_sorting_center', 'Wireless Noise-Cancelling Headphones', 'buyer_taguig', 'Taguig', 'Central Signal Village', null, 80],
            ['DEMO-2026-006', 'sorted', 'Minimalist Desk Lamp', 'buyer_pasig', 'Pasig', 'Pinagbuhatan', null, 6],
            ['DEMO-2026-007', 'assigned_to_rider', 'Woven Storage Basket', 'buyer_pasig', 'Pasig', 'San Miguel', 'rider_pasig', 4],
            ['DEMO-2026-008', 'out_for_delivery', 'Portable Bluetooth Speaker', 'buyer_taguig', 'Taguig', 'Ususan', 'rider_taguig', 2],
            ['DEMO-2026-009', 'delivery_failed', 'Wireless Noise-Cancelling Headphones', 'buyer_taguig', 'Taguig', 'Central Signal Village', 'rider_taguig', 26],
            ['DEMO-2026-010', 'delivered', 'Woven Storage Basket', 'buyer_pasig', 'Pasig', 'Kapitolyo', 'rider_pasig', 10],
            ['DEMO-2026-011', 'completed', 'Minimalist Desk Lamp', 'buyer_pasig', 'Pasig', 'San Miguel', 'rider_pasig', 1],
            ['DEMO-2026-012', 'completed', 'Wireless Noise-Cancelling Headphones', 'buyer_taguig', 'Taguig', 'Central Signal Village', 'rider_taguig', 15],
            ['DEMO-2026-013', 'returned', 'Portable Bluetooth Speaker', 'buyer_taguig', 'Taguig', 'Ususan', 'rider_taguig', 120],
            ['DEMO-2026-014', 'cancelled', 'Woven Storage Basket', 'buyer_pasig', 'Pasig', 'San Miguel', null, 12],
        ];

        $orders = [];
        foreach ($definitions as [$number, $status, $productName, $buyerKey, $city, $barangayName, $riderKey, $ageHours]) {
            $orders[$number] = $this->seedOrder(
                $number,
                $status,
                $this->products[$productName],
                $this->users[$buyerKey],
                $city,
                $barangayName,
                $riderKey ? $this->users[$riderKey] : null,
                $ageHours,
            );
        }

        $this->seedCompletedOrderLedger($orders['DEMO-2026-011']);
        $this->seedCompletedOrderLedger($orders['DEMO-2026-012']);

        return $orders;
    }

    private function seedOrder(
        string $number,
        string $targetStatus,
        Product $product,
        User $buyer,
        string $city,
        string $barangayName,
        ?User $rider,
        int $ageHours,
    ): Order {
        $createdAt = now()->subHours($ageHours);
        Carbon::setTestNow($createdAt);
        try {
            $order = Order::firstOrCreate(
                ['order_number' => $number],
                [
                    'product_id' => $product->id,
                    'buyer_id' => $buyer->id,
                    'seller_id' => $product->seller_id,
                    'logistics_id' => $this->branches[$city]->logistics_id,
                    'courier_id' => $rider?->id,
                    'origin_branch_id' => $this->branchForSeller($product)->id,
                    'destination_branch_id' => $this->branches[$city]->id,
                    'destination_barangay_id' => $this->locations[$city]['barangays'][$barangayName]->id,
                    'product_name' => $product->name,
                    'quantity' => 1,
                    'amount' => $product->price,
                    'commission' => round((float) $product->price * 0.1, 2),
                    'commission_rate' => 10,
                    'status' => 'placed',
                    'waybill_number' => 'WB-'.$number,
                    'tracking_status' => 'Order placed',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ],
            );

            if (! $order->wasRecentlyCreated) {
                return $order;
            }

            $lifecycle = app(OrderLifecycleService::class);
            $fullPath = [
                'confirmed', 'preparing', 'ready_for_pickup', 'picked_up',
                'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery',
                'delivered', 'completed',
            ];
            if (in_array($targetStatus, ['placed', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'], true)) {
                $targetIndex = array_search($targetStatus, ['placed', ...$fullPath], true);
                $path = array_slice($fullPath, 0, $targetIndex);
            } elseif ($targetStatus === 'cancelled') {
                $path = ['cancelled'];
            } elseif (in_array($targetStatus, ['delivery_failed', 'returned'], true)) {
                $path = array_slice($fullPath, 0, array_search('out_for_delivery', $fullPath, true) + 1);
                $attempts = $targetStatus === 'returned' ? 4 : 2;
                for ($attempt = 0; $attempt < $attempts; $attempt++) {
                    $path[] = 'delivery_failed';
                    if ($attempt < $attempts - 1) {
                        $path[] = 'assigned_to_rider';
                        $path[] = 'out_for_delivery';
                    }
                }
                if ($targetStatus === 'returned') {
                    $path[] = 'returned';
                }
            } else {
                throw new \InvalidArgumentException("Unsupported demo order status [{$targetStatus}].");
            }

            foreach ($path as $step => $nextStatus) {
                Carbon::setTestNow($createdAt->copy()->addMinutes(($step + 1) * 20));
                if ($nextStatus === 'at_sorting_center') {
                    ParcelScan::firstOrCreate(
                        ['order_id' => $order->id, 'scan_type' => 'sorting_center_received'],
                        [
                            'scanned_by' => $this->users['logistics_'.strtolower($city)]->id,
                            'location' => $this->branches[$city]->name,
                            'notes' => 'Demo parcel received and label verified.',
                            'scanned_at' => now(),
                        ],
                    );
                }

                if ($nextStatus === 'returned') {
                    Delivery::where('order_id', $order->id)->update([
                        'status' => 'return_to_sender',
                        'delivery_attempts' => 4,
                    ]);
                    DeliveryLog::firstOrCreate(
                        ['delivery_id' => $order->delivery->id, 'to_status' => 'return_to_sender'],
                        [
                            'actor_id' => $rider?->id,
                            'actor_role' => 'rider',
                            'from_status' => 'delivery_failed',
                            'note' => 'Return-to-sender route started after four unsuccessful attempts.',
                            'created_at' => now(),
                        ],
                    );
                }

                $lifecycle->transition(
                    $order->fresh(),
                    $nextStatus,
                    $this->admin->id,
                    'system',
                    'Demo lifecycle event for admin preview.',
                );
            }

            if ($targetStatus === 'picked_up') {
                $order->update(['tracking_status' => 'Pickup approved by logistics']);
            }

            if ($ageHours >= 48 && in_array($targetStatus, ['picked_up', 'at_sorting_center'], true)) {
                DB::table('orders')->where('id', $order->id)->update([
                    'updated_at' => now()->subHours($ageHours),
                ]);
            }

            return $order->fresh();
        } finally {
            Carbon::setTestNow();
        }
    }

    private function branchForSeller(Product $product): LogisticsBranch
    {
        $city = $product->seller_id === $this->users['seller_taguig']->id ? 'Taguig' : 'Pasig';

        return $this->branches[$city];
    }

    private function seedCompletedOrderLedger(Order $order): void
    {
        app(FinancialLedgerService::class)->postCompletedOrder($order, $this->admin);
    }

    private function seedReturnsAndRefunds(array $orders): void
    {
        $disputedOrder = $orders['DEMO-2026-010'];
        $dispute = ReturnRequest::firstOrCreate(
            ['order_id' => $disputedOrder->id],
            [
                'buyer_id' => $disputedOrder->buyer_id,
                'seller_id' => $disputedOrder->seller_id,
                'reason' => 'damaged',
                'details' => 'The outer packaging arrived damaged. The buyer included photos with the original request.',
                'status' => 'rejected',
                'seller_note' => 'The item was inspected before dispatch; requesting Admin review of the evidence.',
                'dispute_status' => 'open',
                'quantity' => 1,
                'tracking_status' => 'not_started',
            ],
        );
        $this->returnEvent($dispute, 'requested', null, 'requested', $dispute->buyer_id, 'Buyer submitted a return request.');
        $this->returnEvent($dispute, 'seller_rejected', 'requested', 'rejected', $dispute->seller_id, 'Seller declined and buyer opened a dispute.');

        $refundedOrder = $orders['DEMO-2026-012'];
        $refundAmount = min(899.99, (float) $refundedOrder->amount);
        $commissionReversal = round($refundAmount * (float) $refundedOrder->commission / (float) $refundedOrder->amount, 2);
        $return = ReturnRequest::firstOrCreate(
            ['order_id' => $refundedOrder->id],
            [
                'buyer_id' => $refundedOrder->buyer_id,
                'seller_id' => $refundedOrder->seller_id,
                'reason' => 'wrong_item',
                'details' => 'The buyer received a different model and returned the item with its original packaging.',
                'status' => 'refund_due',
                'seller_note' => 'Return received and inspected at the seller branch.',
                'dispute_status' => 'not_open',
                'resolved_by' => $this->admin->id,
                'reviewed_by' => $this->admin->id,
                'reviewed_at' => now()->subDay(),
                'quantity' => 1,
                'refund_amount' => $refundAmount,
                'refund_due_at' => now()->subDay(),
                'received_at' => now()->subDays(2),
                'tracking_status' => 'delivered',
                'tracking_updated_at' => now()->subDay(),
                'carrier' => 'PickSell Demo Logistics',
                'tracking_number' => 'RTN-DEMO-2026-012',
                'admin_notes' => 'Demo refund approved after return inspection.',
                'admin_decision' => 'refund',
                'admin_resolved_at' => now()->subDay(),
            ],
        );
        $this->returnEvent($return, 'requested', null, 'requested', $return->buyer_id, 'Buyer requested a return for the wrong item.');
        $this->returnEvent($return, 'approved', 'requested', 'awaiting_item', $return->seller_id, 'Seller approved the return shipment.');
        $this->returnEvent($return, 'item_received', 'awaiting_item', 'received', $this->admin->id, 'Returned item received and logged.');
        $this->returnEvent($return, 'inspection_completed', 'received', 'inspected', $this->admin->id, 'Returned item inspection was completed.');
        $this->returnEvent($return, 'refund_approved', 'inspected', 'refund_due', $this->admin->id, 'Admin approved the refund after inspection.');

        $refund = Refund::firstOrCreate(
            ['return_request_id' => $return->id],
            [
                'order_id' => $refundedOrder->id,
                'requested_by' => $return->buyer_id,
                'approved_by' => $this->admin->id,
                'amount' => $refundAmount,
                'commission_reversal' => $commissionReversal,
                'seller_adjustment' => round($refundAmount - $commissionReversal, 2),
                'status' => 'approved',
                'reason' => 'Wrong item returned and inspected.',
                'decision_notes' => 'Refund approved for admin finance preview.',
                'approved_at' => now()->subDay(),
            ],
        );
        app(FinancialLedgerService::class)->postApprovedRefund($refund, $this->admin);
    }

    private function returnEvent(
        ReturnRequest $return,
        string $type,
        ?string $from,
        string $to,
        int $actorId,
        string $notes,
    ): void {
        ReturnRequestEvent::firstOrCreate(
            [
                'return_request_id' => $return->id,
                'event_type' => $type,
                'to_status' => $to,
            ],
            [
                'actor_user_id' => $actorId,
                'from_status' => $from,
                'notes' => $notes,
            ],
        );
    }

    private function seedComplaintsAndExceptions(array $orders): void
    {
        $complaintDefinitions = [
            [
                'order' => $orders['DEMO-2026-009'],
                'subject' => 'Delivery attempt needs follow-up',
                'details' => 'The buyer says the rider could not reach the building entrance. Please coordinate another attempt.',
                'status' => 'open',
            ],
            [
                'order' => $orders['DEMO-2026-010'],
                'subject' => 'Return request review',
                'details' => 'The buyer and seller disagree about the condition of the item and request Admin review.',
                'status' => 'under_review',
            ],
        ];
        foreach ($complaintDefinitions as $definition) {
            $order = $definition['order'];
            Complaint::firstOrCreate(
                ['subject' => $definition['subject']],
                [
                    'filed_by' => $order->buyer_id,
                    'against_user_id' => $order->seller_id,
                    'order_id' => $order->id,
                    'details' => $definition['details'],
                    'status' => $definition['status'],
                ],
            );
        }

        foreach ([
            [
                'order' => $orders['DEMO-2026-004'],
                'type' => 'missing_scan',
                'description' => 'Parcel was approved for pickup but has not received a sorting-center scan.',
            ],
            [
                'order' => $orders['DEMO-2026-009'],
                'type' => 'failed_delivery',
                'description' => 'Delivery attempt failed. Confirm buyer contact details before the next attempt.',
            ],
            [
                'order' => $orders['DEMO-2026-006'],
                'type' => 'rider_unavailable',
                'description' => 'No active rider currently covers the destination barangay Pinagbuhatan.',
            ],
        ] as $definition) {
            LogisticsException::firstOrCreate(
                [
                    'order_id' => $definition['order']->id,
                    'type' => $definition['type'],
                    'status' => 'open',
                ],
                [
                    'opened_by' => $this->users['logistics_pasig']->id,
                    'description' => $definition['description'],
                ],
            );
        }

        LogisticsException::firstOrCreate(
            [
                'order_id' => $orders['DEMO-2026-005']->id,
                'type' => 'wrong_destination',
                'status' => 'resolved',
            ],
            [
                'opened_by' => $this->users['logistics_taguig']->id,
                'description' => 'The destination barangay was checked against the assigned branch.',
                'resolution' => 'The parcel label matches the Taguig destination branch.',
                'resolved_by' => $this->users['logistics_taguig']->id,
                'resolved_at' => now()->subDays(2),
            ],
        );
    }

    private function seedReviewsAndMessages(array $orders): void
    {
        $order = $orders['DEMO-2026-011'];
        ProductReview::firstOrCreate(
            [
                'product_id' => $order->product_id,
                'buyer_id' => $order->buyer_id,
                'order_id' => $order->id,
            ],
            ['rating' => 5, 'body' => 'Arrived safely and matches the listing.'],
        );
        $order->update(['rating' => 5, 'feedback' => 'Arrived safely and matches the listing.']);

        DB::table('messages')->updateOrInsert(
            [
                'sender_id' => $this->users['buyer_pasig']->id,
                'receiver_id' => $this->users['seller_pasig']->id,
                'body' => 'Could you confirm the pickup window for my order?',
            ],
            ['read' => false, 'created_at' => now()->subHours(3), 'updated_at' => now()->subHours(3)],
        );
    }

    private function seedModerationHistory(): void
    {
        $product = $this->products['archived'];
        ProductModerationLog::firstOrCreate(
            [
                'product_id' => $product->id,
                'action' => ProductModerationLog::ARCHIVED,
            ],
            [
                'admin_id' => $this->admin->id,
                'from_status' => 'active',
                'to_status' => 'archived',
                'reason' => 'Demo listing archived for Admin moderation preview.',
            ],
        );
    }

    private function setDemoInventory(array $orders): void
    {
        $reservedOrSold = [];
        foreach ($orders as $order) {
            if (! in_array($order->status, ['cancelled', 'returned'], true) && $order->product_id) {
                $reservedOrSold[$order->product_id] = ($reservedOrSold[$order->product_id] ?? 0) + $order->quantity;
            }
        }

        foreach ($this->products as $product) {
            if ($product->status !== 'active') {
                continue;
            }
            $startingStock = match ($product->name) {
                'Woven Storage Basket' => 22,
                'Minimalist Desk Lamp' => 18,
                'Wireless Noise-Cancelling Headphones' => 16,
                default => 25,
            };
            $product->update(['stock' => max(0, $startingStock - ($reservedOrSold[$product->id] ?? 0))]);
        }
    }
}
