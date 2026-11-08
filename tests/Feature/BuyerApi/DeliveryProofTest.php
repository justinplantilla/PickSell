<?php

namespace Tests\Feature\BuyerApi;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Pickrush\Bridge\PicksellBridge;
use App\Pickrush\Enums\ParcelStatus;
use App\Pickrush\Enums\StaffRole;
use App\Pickrush\Models\Assignment;
use App\Pickrush\Models\Parcel;
use App\Pickrush\Models\Rider;
use App\Pickrush\Services\ParcelLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Pickrush\BuildsNetwork;
use Tests\TestCase;

/**
 * The last mile on the order page (OrderDelivery): the rider bringing it, the rider's photo at the
 * door (app: GET /buyer/orders/{id}/proof-of-delivery; website: the order pages) and payment status.
 */
class DeliveryProofTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private User $seller;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->buildNetwork();
        $person = fn (string $role, string $province, string $municipality, array $extra = []) => User::create($extra + [
            'role' => $role, 'status' => 'approved', 'first_name' => ucfirst($role), 'last_name' => 'Proof', 'sex' => 'Female',
            'email' => "{$role}@proof.test", 'password' => bcrypt('secret-password'), 'contact_no' => $role === 'seller' ? '09170000001' : '09170000002',
            'birthday' => '1995-01-01', 'age' => 30, 'province' => $province, 'municipality' => $municipality, 'barangay' => 'Bagong Pag-asa',
            'house_no' => '12', 'street' => 'Rizal St',
        ]);
        $this->seller = $person('seller', 'Batangas', 'City of Tanauan', ['business_name' => 'Tote House']);
        $this->buyer = $person('buyer', 'Metro Manila', 'Quezon City');
    }

    /** A handed-over order whose parcel has reached Quezon City Station, and the rider taking it out. */
    private function outForDelivery(): array
    {
        $product = Product::create(['seller_id' => $this->seller->id, 'name' => 'Canvas Tote', 'category' => 'Fashion', 'price' => 600, 'stock' => 5, 'status' => 'active']);
        $order = Order::create([
            'order_number' => 'ORD-PROOF01', 'product_id' => $product->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id,
            'product_name' => 'Canvas Tote', 'quantity' => 1, 'amount' => 600, 'commission' => 60, 'status' => 'preparing',
        ]);
        $this->actingAs($this->seller)->post(config('app.url')."/seller/orders/{$order->id}/waybill")->assertRedirect();
        $this->actingAs($this->seller)->patch(config('app.url')."/seller/orders/{$order->id}/handover")->assertRedirect();
        $parcel = PicksellBridge::parcelFor($order);
        $qc = $this->station('137404000');
        $this->bringTo($parcel, $qc);

        $rider = Rider::withoutGlobalScopes()->create([
            'station_hub_id' => $qc->id, 'first_name' => 'Rico', 'last_name' => 'Santos', 'full_name' => 'Rico Santos', 'mobile_no' => '09181234567',
            'vehicle_type' => 'motorcycle', 'plate_no' => 'NBC 1234', 'status' => 'active', 'shift_status' => 'on_shift',
            'cod_limit' => 10000, 'max_parcels' => 30, 'created_by' => $this->staff(StaffRole::HubManager, $qc)->id,
        ]);
        $task = Assignment::create(['parcel_id' => $parcel->id, 'rider_id' => $rider->id, 'type' => 'delivery', 'assigned_at' => now(), 'status' => 'out_for_delivery']);
        $ledger = app(ParcelLedger::class);
        $ledger->record($parcel->fresh(), ParcelStatus::AssignedToRider, $qc);
        $ledger->record($parcel->fresh(), ParcelStatus::OutForDelivery, $qc);

        return [$order->fresh(), $parcel->fresh(), $task, $qc];
    }

    /** Scans the parcel from the seller's station hub by hub (as routed) to [$station]. */
    private function bringTo(Parcel $parcel, \App\Pickrush\Models\Hub $station): void
    {
        $ledger = app(ParcelLedger::class);
        $origin = $parcel->originHub;
        $ledger->record($parcel, ParcelStatus::PickedUp, $origin);
        $ledger->record($parcel->fresh(), ParcelStatus::AtOriginHub, $origin);
        for ($hops = 0; ($parcel = $parcel->fresh())->current_hub_id !== $station->id && $hops < 10; $hops++) {
            $from = $parcel->currentHub;
            $next = $parcel->nextHub;
            $ledger->record($parcel, $next->isProvince() ? ParcelStatus::InTransitToProvince : ParcelStatus::InTransitToStation, $from, ['to_hub' => $next]);
            $ledger->record($parcel->fresh(), $next->isProvince() ? ParcelStatus::AtProvinceHub : ParcelStatus::ArrivedAtStation, $next);
        }
    }

    public function test_the_app_shows_the_rider_then_the_photo_and_payment(): void
    {
        [$order, $parcel, $task, $qc] = $this->outForDelivery();
        Sanctum::actingAs($this->buyer);

        // On the way: who is bringing it, with their number; cash still to pay; no photo yet.
        $this->getJson("/api/mobile/buyer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.courier', ['name' => 'Rico Santos', 'vehicle' => 'Motorcycle · NBC 1234', 'phone' => '09181234567'])
            ->assertJsonPath('order.has_proof_of_delivery', false)
            ->assertJsonPath('order.payment_status', 'unpaid');
        $this->get("/api/mobile/buyer/orders/{$order->id}/proof-of-delivery")->assertNotFound();

        // Delivered with a photo at the door.
        $path = UploadedFile::fake()->create('door.jpg', 40, 'image/jpeg')->store("pickrush/proof/{$task->id}", 'local');
        app(ParcelLedger::class)->record($parcel->fresh(), ParcelStatus::Delivered, $qc);
        $task->update(['status' => 'completed', 'proof_photo_path' => $path, 'cod_collected' => $parcel->cod_amount, 'completed_at' => now()]);

        $this->getJson("/api/mobile/buyer/orders/{$order->id}")->assertOk()
            ->assertJsonPath('order.courier.name', 'Rico Santos')
            ->assertJsonPath('order.courier.phone', null)
            ->assertJsonPath('order.has_proof_of_delivery', true)
            ->assertJsonPath('order.payment_status', 'paid');
        $photo = $this->get("/api/mobile/buyer/orders/{$order->id}/proof-of-delivery")->assertOk();
        $this->assertStringContainsString('private', $photo->headers->get('Cache-Control'));

        // Only the buyer of the order gets the photo.
        Sanctum::actingAs(User::create(['role' => 'buyer', 'status' => 'approved', 'first_name' => 'Other', 'last_name' => 'Buyer', 'sex' => 'Male', 'province' => 'Cebu', 'municipality' => 'Cebu City', 'barangay' => 'Lahug', 'email' => 'other@proof.test',
            'password' => bcrypt('x'), 'contact_no' => '09170000003', 'birthday' => '1995-01-01', 'age' => 30]));
        $this->get("/api/mobile/buyer/orders/{$order->id}/proof-of-delivery")->assertNotFound();
    }

    public function test_the_website_order_pages_show_the_delivery_photo(): void
    {
        [$order, $parcel, $task, $qc] = $this->outForDelivery();
        $this->actingAs($this->buyer)->get(config('app.url').'/buyer/orders')->assertOk()->assertDontSee('Proof of delivery');

        $path = UploadedFile::fake()->create('door.jpg', 40, 'image/jpeg')->store("pickrush/proof/{$task->id}", 'local');
        app(ParcelLedger::class)->record($parcel->fresh(), ParcelStatus::Delivered, $qc);
        $task->update(['status' => 'completed', 'proof_photo_path' => $path, 'completed_at' => now()]);

        $this->actingAs($this->buyer)->get(config('app.url').'/buyer/orders')->assertOk()
            ->assertSee('Proof of delivery')->assertSee('Photo taken by Rico Santos')->assertSee(route('buyer.orders.proof-of-delivery', $order), false);
        $this->actingAs($this->buyer)->get(config('app.url')."/buyer/orders/{$order->id}/tracking")->assertOk()->assertSee('Proof of delivery');
        $this->actingAs($this->buyer)->get(config('app.url')."/buyer/orders/{$order->id}/proof-of-delivery")->assertOk();
        $this->actingAs($this->seller)->get(config('app.url')."/buyer/orders/{$order->id}/proof-of-delivery")->assertRedirect(); // not a buyer page for a seller
        $this->assertSame(1, Parcel::withoutGlobalScopes()->count());
    }
}
