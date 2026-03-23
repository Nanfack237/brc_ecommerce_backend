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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique(); // "laptops", "smartphones"
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            // parent_id NULL = catégorie racine (Informatique, Téléphones...)
            // parent_id = 1  = sous-catégorie (Laptops, Desktops...)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories') // référence la même table !
                ->nullOnDelete();            // si parent supprimé → parent_id = NULL
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_promoted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
