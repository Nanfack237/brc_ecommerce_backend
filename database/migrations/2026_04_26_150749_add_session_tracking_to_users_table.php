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
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_device_type')->nullable()->after('last_login_at');
            $table->string('last_browser')->nullable()->after('last_device_type');
            $table->string('last_os')->nullable()->after('last_browser');
            $table->string('last_city')->nullable()->after('last_os');
            $table->string('last_country')->nullable()->after('last_city');
            $table->string('last_ip')->nullable()->after('last_country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'last_device_type', 'last_browser', 'last_os',
                'last_city', 'last_country', 'last_ip',
            ]);
        });
    }
};
