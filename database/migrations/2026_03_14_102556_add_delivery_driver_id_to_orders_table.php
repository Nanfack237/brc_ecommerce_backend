<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // On ajoute livreur_id qui pointe vers la table users
            // On le met en nullable car une commande n'a pas de livreur au début
            $table->foreignId('delivery_driver_id')
                  ->nullable()
                  ->after('payment_status') // On le place après le statut de paiement
                  ->constrained('users')    // Indique que c'est lié à la table users
                  ->nullOnDelete();         // Si le livreur est supprimé, la commande reste
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_driver_id']);
            $table->dropColumn('delivery_driver_id');
        });
    }
};