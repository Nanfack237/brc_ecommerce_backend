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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // "BIENVENUE10"
            $table->enum('type', ['percent', 'fixed']);
            $table->decimal('value', 10, 2);       // 10 (%) ou 5000 (FCFA)
            $table->decimal('min_order', 12, 2)->default(0); // min commande
            $table->unsignedInteger('max_uses')->nullable(); // NULL=illimité
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable(); // NULL=jamais expiré
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
