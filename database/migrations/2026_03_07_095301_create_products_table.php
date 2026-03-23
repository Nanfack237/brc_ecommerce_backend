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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('sku', 100)->nullable()->unique();
            $table->foreignId('category_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->decimal('price', 12, 2);
            $table->decimal('old_price', 12, 2)->nullable(); // prix barré
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('stock_alert')->default(5); // seuil alerte
            // JSON : [{key: "RAM", value: "8Go"}, {key: "Écran", value: "15.6"}]
            $table->json('specs')->nullable();
            // JSON : ["img/prod1.jpg", "img/prod2.jpg"]
            $table->json('images')->nullable();
            $table->enum('status',
                ['published','draft','archived','out_of_stock'])
                ->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_best_seller')->default(false);
            $table->boolean('is_new')->default(true);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->timestamps();
            $table->index('status');
            $table->index('category_id');
            $table->index('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
