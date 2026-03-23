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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')  // vérifie achat réel
                ->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating'); // 1 à 5 étoiles
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(false); // admin valide
            $table->unsignedInteger('helpful_count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'product_id']); // 1 avis/produit
            $table->index('product_id');
            $table->index('is_approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
