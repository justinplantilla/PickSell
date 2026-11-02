<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerVacation;
use App\Models\User;
use App\Services\SellerVacations;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SellerVacationModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-10 10:00'));
    }

    public function test_seller_can_turn_vacation_mode_on_and_off(): void
    {
        $seller = $this->user('seller', 'toggle');

        $this->actingAs($seller)->patch(route('seller.account.vacation'), ['vacation_mode' => 1, 'vacation_until' => now()->addDays(7)->toDateString(), 'vacation_message' => 'Back on Monday!'])
            ->assertSessionHasNoErrors();
        $seller->refresh();
        $this->assertTrue($seller->isOnVacation());
        $this->assertNotNull($seller->vacation_started_at);
        $this->assertTrue($seller->vacation_ends_at->equalTo(now()->addDays(7)->startOfDay()));
        $this->assertSame('Back on Monday!', $seller->vacation_message);

        $this->actingAs($seller)->patch(route('seller.account.vacation'), ['vacation_mode' => 0])->assertSessionHasNoErrors();
        $seller->refresh();
        $this->assertFalse($seller->isOnVacation());
        $this->assertNull($seller->vacation_started_at);
        $this->assertNull($seller->vacation_ends_at);
    }

    public function test_vacation_mode_is_blocked_while_orders_are_unshipped(): void
    {
        $seller = $this->user('seller', 'busy');
        $buyer = $this->user('buyer', 'waiting');
        $this->order($buyer, $seller, 'ready_for_pickup');

        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway())
            ->assertSessionHasErrors('vacation_mode');
        $this->assertFalse($seller->refresh()->isOnVacation());
    }

    public function test_orders_already_with_the_rider_do_not_block_vacation_mode(): void
    {
        $seller = $this->user('seller', 'shipped');
        $this->order($this->user('buyer', 'receiving'), $seller, 'in_transit');

        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway())->assertSessionHasNoErrors();
        $this->assertTrue($seller->refresh()->isOnVacation());
    }

    public function test_sellers_get_seven_vacation_days_a_month(): void
    {
        $seller = $this->user('seller', 'planner');

        $this->actingAs($seller)->patch(route('seller.account.vacation'), ['vacation_mode' => 1])->assertSessionHasErrors('vacation_until');
        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway(0))->assertSessionHasErrors('vacation_until');
        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway(8))->assertSessionHasErrors('vacation_until');
        $this->assertFalse($seller->refresh()->isOnVacation());

        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway(7))->assertSessionHasNoErrors();
        $this->assertTrue($seller->refresh()->isOnVacation());
        $this->assertSame(1, SellerVacation::where('seller_id', $seller->id)->count());
    }

    public function test_earlier_vacations_this_month_use_up_the_allowance(): void
    {
        $seller = $this->user('seller', 'repeat');
        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway(3))->assertSessionHasNoErrors();

        $this->travelTo(now()->addDays(3)); // back on Nov 13: Nov 10-12 used
        $this->assertFalse($seller->refresh()->isOnVacation());

        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway(5))->assertSessionHasErrors('vacation_until');
        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway(4))->assertSessionHasNoErrors();

        $this->travelTo(now()->addDays(4));
        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway(1))->assertSessionHasErrors('vacation_mode');
    }

    public function test_coming_back_early_gives_the_unused_days_back(): void
    {
        $seller = $this->user('seller', 'early');
        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway(7))->assertSessionHasNoErrors();

        $this->travelTo(now()->addDay()); // Nov 11: away on Nov 10 and 11
        $this->actingAs($seller)->patch(route('seller.account.vacation'), ['vacation_mode' => 0])->assertSessionHasNoErrors();

        $this->assertFalse($seller->refresh()->isOnVacation());
        $this->assertSame(5, app(SellerVacations::class)->daysLeft($seller, now()));
    }

    public function test_a_vacation_into_next_month_uses_next_months_days(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 09:00'));
        $seller = $this->user('seller', 'crossing');

        // Nov 28-30 come from November's days, Dec 1-7 from December's.
        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway(11))->assertSessionHasErrors('vacation_until');
        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway(10))->assertSessionHasNoErrors();
    }

    public function test_changing_the_return_date_mid_vacation_stays_within_the_allowance(): void
    {
        $seller = $this->user('seller', 'stretcher');
        $this->actingAs($seller)->patch(route('seller.account.vacation'), $this->goAway(3))->assertSessionHasNoErrors();
        $this->travelTo(now()->addDay());

        $this->actingAs($seller)->patch(route('seller.account.vacation'), ['vacation_mode' => 1, 'vacation_until' => '2026-11-18'])->assertSessionHasErrors('vacation_until');
        $this->actingAs($seller)->patch(route('seller.account.vacation'), ['vacation_mode' => 1, 'vacation_until' => '2026-11-17'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-17', $seller->refresh()->vacation_ends_at->toDateString());
        $this->assertSame(1, SellerVacation::where('seller_id', $seller->id)->count());
    }

    public function test_vacation_ends_on_its_own_at_the_return_date(): void
    {
        $seller = $this->user('seller', 'returner', $this->away(['vacation_ends_at' => now()->addDays(3)->startOfDay()]));
        $product = $this->product($seller);

        $this->assertFalse(Product::purchasable()->whereKey($product->id)->exists());

        $this->travelTo(now()->addDays(3)->startOfDay()->addMinute());
        $this->assertFalse($seller->refresh()->isOnVacation());
        $this->assertTrue(Product::purchasable()->whereKey($product->id)->exists());

        $this->artisan('sellers:end-vacations')->assertSuccessful();
        $seller->refresh();
        $this->assertFalse($seller->vacation_mode);
        $this->assertNull($seller->vacation_ends_at);
        $this->assertSame(1, $seller->notifications()->count());
    }

    public function test_products_stay_visible_everywhere_but_cannot_be_ordered(): void
    {
        $seller = $this->user('seller', 'away', $this->away());
        $product = $this->product($seller);
        $buyer = $this->user('buyer', 'browsing');

        $this->assertFalse(Product::purchasable()->whereKey($product->id)->exists());
        $this->assertTrue(Product::showcased()->whereKey($product->id)->exists());
        $this->assertFalse($product->fresh('seller')->isPurchasable());

        $this->actingAs($buyer)->get(route('buyer.home'))->assertOk()->assertSee('Vacation Lamp')->assertSee('Seller on vacation');
        $this->actingAs($buyer)->get(route('buyer.seller', $seller))->assertOk()->assertSee('Vacation Lamp')->assertSee('On vacation');
        $this->actingAs($buyer)->get(route('buyer.product', $product))->assertOk()->assertSee('This seller is on vacation');
        $this->actingAs($buyer)->post(route('buyer.wishlist.store', $product))->assertRedirect();
        $this->assertDatabaseHas('wishlist_items', ['user_id' => $buyer->id, 'product_id' => $product->id]);
    }

    public function test_buyer_cannot_add_to_cart_or_check_out_while_seller_is_on_vacation(): void
    {
        $seller = $this->user('seller', 'closed');
        $product = $this->product($seller);
        $buyer = $this->user('buyer', 'shopper');
        $item = CartItem::create(['cart_id' => Cart::create(['buyer_id' => $buyer->id])->id, 'product_id' => $product->id, 'quantity' => 1]);

        $seller->update($this->away());

        $this->actingAs($buyer)->post(route('buyer.cart.add', $product), ['quantity' => 1])->assertNotFound();
        $this->actingAs($buyer)->post(route('buyer.checkout'), ['item_ids' => [$item->id], 'payment_method' => 'cod'])
            ->assertSessionHasErrors('item_ids');
        $this->assertSame(0, Order::count());
    }

    public function test_buyer_gets_one_auto_reply_per_vacation(): void
    {
        $seller = $this->user('seller', 'replier', $this->away(['vacation_started_at' => now()->subMinute(), 'vacation_message' => 'Gone fishing.']));
        $buyer = $this->user('buyer', 'asker');

        $this->actingAs($buyer)->post(route('buyer.chat.send'), ['receiver_id' => $seller->id, 'body' => 'Hello?']);
        $this->actingAs($buyer)->post(route('buyer.chat.send'), ['receiver_id' => $seller->id, 'body' => 'Still there?']);

        $replies = Message::where('sender_id', $seller->id)->where('receiver_id', $buyer->id)->get();
        $this->assertCount(1, $replies);
        $this->assertSame('[Auto-reply] Gone fishing.', $replies->first()->body);
    }

    public function test_any_seller_thanks_the_buyer_once_until_the_conversation_goes_quiet(): void
    {
        $seller = $this->user('seller', 'present');
        $buyer = $this->user('buyer', 'greeter');

        $this->actingAs($buyer)->post(route('buyer.chat.send'), ['receiver_id' => $seller->id, 'body' => 'Hi!']);
        $this->actingAs($buyer)->post(route('buyer.chat.send'), ['receiver_id' => $seller->id, 'body' => 'Is this in stock?']);

        $replies = Message::where('sender_id', $seller->id)->get();
        $this->assertCount(1, $replies);
        $this->assertSame('[Auto-reply] '.User::DEFAULT_THANK_YOU_MESSAGE, $replies->first()->body);

        $this->travel(User::AUTO_REPLY_QUIET_HOURS + 1)->hours();
        $this->actingAs($buyer)->post(route('buyer.chat.send'), ['receiver_id' => $seller->id, 'body' => 'Hello again']);
        $this->assertSame(2, Message::where('sender_id', $seller->id)->count());
    }

    public function test_seller_who_already_chatted_still_thanks_a_returning_buyer(): void
    {
        $seller = $this->user('seller', 'chatty');
        $buyer = $this->user('buyer', 'returning');
        Message::create(['sender_id' => $seller->id, 'receiver_id' => $buyer->id, 'body' => 'Thanks for ordering!', 'read' => false]);

        $this->travel(2)->hours();
        $this->actingAs($buyer)->post(route('buyer.chat.send'), ['receiver_id' => $seller->id, 'body' => 'Hey, quick question']);

        $this->assertTrue(Message::where('sender_id', $seller->id)->where('body', '[Auto-reply] '.User::DEFAULT_THANK_YOU_MESSAGE)->exists());
    }

    public function test_buyer_messaging_support_gets_no_seller_auto_reply(): void
    {
        $admin = $this->user('admin', 'support');
        $buyer = $this->user('buyer', 'helpme');

        $this->actingAs($buyer)->post(route('buyer.chat.send'), ['receiver_id' => $admin->id, 'body' => 'Help']);

        $this->assertFalse(Message::where('sender_id', $admin->id)->exists());
    }

    private function away(array $overrides = []): array
    {
        return $overrides + ['vacation_mode' => true, 'vacation_started_at' => now(), 'vacation_ends_at' => now()->addDays(7)->startOfDay()];
    }

    private function goAway(int $days = 7): array
    {
        return ['vacation_mode' => 1, 'vacation_until' => now()->addDays($days)->toDateString()];
    }

    private function product(User $seller): Product
    {
        return Product::create([
            'seller_id' => $seller->id,
            'name' => 'Vacation Lamp',
            'description' => 'Test product',
            'category' => 'Home & Living',
            'price' => 250,
            'discount' => 0,
            'voucher_discount' => 0,
            'stock' => 5,
            'status' => 'active',
        ]);
    }

    private function order(User $buyer, User $seller, string $status): Order
    {
        return Order::create([
            'order_number' => 'ORD-'.strtoupper(uniqid()),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_name' => 'Vacation test parcel',
            'quantity' => 1,
            'amount' => 100,
            'commission' => 10,
            'status' => $status,
        ]);
    }

    private function user(string $role, string $name, array $overrides = []): User
    {
        return User::create($overrides + [
            'first_name' => ucfirst($name),
            'last_name' => 'Vacation',
            'email' => "{$name}@vacation.test",
            'password' => bcrypt('password'),
            'role' => $role,
            'status' => 'approved',
            'sex' => 'Male',
            'contact_no' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'barangay' => 'San Miguel',
            'business_name' => $role === 'seller' ? ucfirst($name).' Shop' : null,
        ]);
    }
}
