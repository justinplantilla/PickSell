<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signs that a seller is taking a sale off PickSell (contact details or an outside courier in
 * chat or a listing, an order cancelled right after such a chat, a parcel never handed to the
 * carrier, a buyer's report), queued for admin review. Listings can now be held by the system,
 * so a moderation log may have no admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('off_platform_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20);                // chat | product | order | report
            $table->string('signal', 40);              // phone, email, link, payment, account, courier, phrase, keyword, cancel_after_contact, never_picked_up, high_cancellation, buyer_report
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->text('evidence')->nullable();       // what was detected, as written (admins only)
            $table->unsignedTinyInteger('weight')->default(1);
            $table->string('status', 20)->default('open'); // open | dismissed | actioned
            $table->string('action', 30)->nullable();       // warned | case_opened | suspended | listing_restored
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['seller_id', 'created_at']);
            $table->unique(['signal', 'order_id']);   // order signals are raised once per order
        });

        Schema::table('product_moderation_logs', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('off_platform_flags');
    }
};
