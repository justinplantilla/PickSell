<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scan_type', 40);
            $table->string('location', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scanned_at')->useCurrent();
            $table->timestamps();

            $table->index(['order_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_scans');
    }
};
