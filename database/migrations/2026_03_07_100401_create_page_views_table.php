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
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->foreignId('user_id')
                ->nullable()->constrained()->nullOnDelete();
            $table->string('page_path', 500); // "/categories/laptops"
            $table->string('referrer', 500)->nullable();
            $table->enum('source',
                ['organic','social','direct','email','referral'])
                ->default('direct');
            $table->enum('device',
                ['mobile','desktop','tablet'])->default('desktop');
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->unsignedInteger('duration')->default(0); // secondes
            $table->timestamp('created_at')->useCurrent();
            $table->index('session_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
