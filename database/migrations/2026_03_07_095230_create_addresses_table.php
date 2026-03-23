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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()      // référence users.id automatiquement
                ->cascadeOnDelete(); // si user supprimé → ses adresses supprimées
            $table->string('label', 50)->nullable();  // "Domicile", "Bureau"
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('street');
            $table->string('city', 100);
            $table->string('region', 100)->nullable();
            $table->string('country', 100)->default('Cameroun');
            $table->boolean('is_default')->default(false);
            $table->timestamps(); // created_at + updated_at auto
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
