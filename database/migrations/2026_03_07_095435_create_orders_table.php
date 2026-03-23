<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique(); // "CM000001"
            $table->foreignId('user_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->string('guest_email')->nullable();
            $table->foreignId('address_id')->nullable()
                ->constrained()->nullOnDelete();
            // ── Snapshot adresse (figé au moment de la commande) ──
            $table->string('shipping_first_name', 100)->nullable();
            $table->string('shipping_last_name', 100)->nullable();
            $table->string('shipping_phone', 20)->nullable();
            $table->string('shipping_street')->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_country', 100)->nullable();
            // ── Montants ──
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            // ── Statuts ──
            $table->enum('status',
                ['pending','processing','shipped','delivered',
                'cancelled','refunded'])->default('pending');
            $table->enum('payment_method',
                ['cash_on_delivery','mobile_money','card',
                'bank_transfer'])->default('cash_on_delivery');
            $table->enum('payment_status',
                ['unpaid','paid','refunded'])->default('unpaid');
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->timestamps();
            $table->index('user_id'); $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
