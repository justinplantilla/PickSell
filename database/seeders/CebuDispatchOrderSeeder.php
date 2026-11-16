<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Models\Company;
use App\Pickrush\Services\HubRouter;
use App\Pickrush\Services\ParcelLedger;
use App\Services\Geo\AddressResolver;
use App\Services\OrderPlacement;
use App\Services\Orders\OrderLifecycleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * php artisan db:seed --class=CebuDispatchOrderSeeder
 *
 * Requires CebuBuyerSeeder, CategorySellersSeeder, shipping rates and the Pickrush hub network.
 * Creates one real checkout for Andrea, then simulates hub scans to Cebu City Station.
 * Stops in Dispatch > Deliveries, ready to assign a rider (not yet out for delivery).
 * Re-running preserves the existing test order, even after a tester dispatches or delivers it.
 */
class CebuDispatchOrderSeeder extends Seeder
{
    public const ORDER_NUMBER = 'TEST-CEBU-ANDREA-DISPATCH';

    public function run(): void
    {
        $buyer = User::where('email', 'cebu.buyer@picksell.ph')->where('role', 'buyer')->where('status', 'approved')->first();
        if (! $buyer) {
            throw new RuntimeException('Run CebuBuyerSeeder first to create Andrea Villanueva.');
        }

        $order = DB::transaction(function () use ($buyer) {
            // Serializes repeat runs for this buyer without resetting any existing order.
            User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            if ($existing = Order::where('order_number', self::ORDER_NUMBER)->first()) {
                if ($existing->buyer_id !== $buyer->id) {
                    throw new RuntimeException('The test order number belongs to another buyer. Nothing changed.');
                }
                return $existing;
            }

            $seller = User::where('email', 'homeliving.marikina@picksell.ph')->where('role', 'seller')->where('status', 'approved')->first();
            if (! $seller) {
                throw new RuntimeException('Marikina Home Nook is missing. Run CategorySellersSeeder first.');
            }
            $product = Product::where('seller_id', $seller->id)->where('name', 'Stackable Food Containers (Set of 5)')->firstOrFail();
            $addresses = app(AddressResolver::class);
            $router = app(HubRouter::class);
            $originCodes = $addresses->resolve($seller->province, $seller->municipality, $seller->barangay);
            $destinationCodes = $addresses->resolve($buyer->province, $buyer->municipality, $buyer->barangay);
            if ($destinationCodes['municipality_code'] !== '072217000') {
                throw new RuntimeException('Andrea must have a Cebu City delivery address. Her profile was left unchanged.');
            }
            $companyId = Company::defaultId();
            $origin = $router->stationFor($originCodes['municipality_code'], $companyId);
            $destination = $router->stationFor('072217000', $companyId);
            $path = $origin && $destination ? $router->path($origin, $destination) : null;
            if (! $path) {
                throw new RuntimeException('No Pickrush route to Cebu City Station. Build/import the hub network and enable its links first.');
            }

            $cart = Cart::firstOrCreate(['buyer_id' => $buyer->id]);
            // Only this temporary line is checked out; existing cart lines remain untouched.
            $line = CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
            $order = app(OrderPlacement::class)->place($buyer, [$line->id], null, carriers: [$seller->id => $companyId])->first();
            $order->update(['order_number' => self::ORDER_NUMBER]);
            $lifecycle = app(OrderLifecycleService::class);
            $bridge = app(PicksellBridge::class);
            $lifecycle->transition($order, 'preparing', $seller->id, 'seller', 'Dispatch test fixture', ['packed_at' => now()]);
            $parcel = $bridge->book($order);
            if ($parcel->dest_hub_id !== $destination->id || $parcel->origin_hub_id !== $origin->id || $parcel->company_id !== $companyId) {
                throw new RuntimeException('Checkout resolved a different carrier or route. Test order rolled back.');
            }
            $order->update(['waybill_number' => $parcel->tracking_no]);
            $bridge->requestPickup($order);
            $lifecycle->transition($order->fresh(), 'ready_for_pickup', $seller->id, 'seller', 'Dispatch test fixture', [
                'handed_over_at' => now(), 'waybill_number' => $parcel->tracking_no,
            ]);

            $ledger = app(ParcelLedger::class);
            $options = ['note' => 'Simulated scan for Andrea Cebu dispatch test', 'meta' => ['seeder' => self::class]];
            $ledger->record($parcel, ParcelStatus::PickedUp, $origin, $options);
            $ledger->record($parcel, ParcelStatus::AtOriginHub, $origin, $options);
            $current = $origin;
            foreach (array_slice($path, 1) as $next) {
                $ledger->record($parcel, $next->isStation() ? ParcelStatus::InTransitToStation : ParcelStatus::InTransitToProvince,
                    $current, $options + ['to_hub' => $next]);
                $ledger->record($parcel, $next->isStation() ? ParcelStatus::ArrivedAtStation : ParcelStatus::AtProvinceHub,
                    $next, $options);
                $current = $next;
            }

            return $order;
        });

        $parcel = PicksellBridge::parcelFor($order);
        $this->command?->info("Order: {$order->order_number}; waybill: ".($parcel?->tracking_no ?? 'not found'));
        $this->command?->info('Current parcel status: '.($parcel?->current_status?->value ?? 'not found').'. Existing test orders are never reset.');
        $this->command?->info('Open Cebu City Station > Dispatch > Deliveries to assign the new parcel to an available rider.');
    }
}
