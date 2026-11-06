<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each parcel goes, copied from the address the buyer chose at checkout so later edits to
 * the address book leave booked parcels alone, and the buyer's note to the seller. Shipments
 * from before this keep these empty and ship to the buyer's profile address, as they always did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_shipments', function (Blueprint $table) {
            // Null with a copied address means the profile (default) address, whose pin can still move.
            $table->foreignId('buyer_address_id')->nullable()->after('seller_id')->constrained('buyer_addresses')->nullOnDelete();
            $table->string('recipient_name', 120)->nullable()->after('buyer_address_id');
            $table->string('recipient_phone', 20)->nullable()->after('recipient_name');
            $table->string('delivery_address')->nullable()->after('recipient_phone');
            $table->string('delivery_province')->nullable()->after('delivery_address');
            $table->string('delivery_municipality')->nullable()->after('delivery_province');
            $table->string('delivery_barangay')->nullable()->after('delivery_municipality');
            $table->string('delivery_municipality_code', 10)->nullable()->after('delivery_barangay');
            $table->string('delivery_barangay_code', 10)->nullable()->after('delivery_municipality_code');
            $table->decimal('delivery_lat', 10, 7)->nullable()->after('delivery_barangay_code');
            $table->decimal('delivery_lng', 10, 7)->nullable()->after('delivery_lat');
            $table->string('buyer_note', 300)->nullable()->after('delivery_lng');
        });
    }

    public function down(): void
    {
        Schema::table('order_shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('buyer_address_id');
            $table->dropColumn([
                'recipient_name', 'recipient_phone', 'delivery_address', 'delivery_province', 'delivery_municipality',
                'delivery_barangay', 'delivery_municipality_code', 'delivery_barangay_code', 'delivery_lat', 'delivery_lng', 'buyer_note',
            ]);
        });
    }
};
