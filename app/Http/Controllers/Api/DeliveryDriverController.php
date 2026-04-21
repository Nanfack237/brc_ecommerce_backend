<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryDriverController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // GET /api/livreur/livraisons
    // Retourne toutes les commandes assignées au livreur connecté
    // ══════════════════════════════════════════════════════════════════════
    public function index(Request $request): JsonResponse
    {
        $driverId = auth()->id();

        $query = Order::where('delivery_driver_id', $driverId)
            ->with('items')
            ->latest();

        // Filtre optionnel par statut  (?status=processing)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->paginate($request->get('per_page', 20));

        return response()->json($orders);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/livreur/livraisons/{id}
    // Détail d'une commande assignée au livreur connecté
    // ══════════════════════════════════════════════════════════════════════
    public function show(int $id): JsonResponse
    {
        $order = Order::where('delivery_driver_id', auth()->id())
            ->with('items')
            ->findOrFail($id);

        return response()->json($order);
    }

    // ══════════════════════════════════════════════════════════════════════
    // PATCH /api/livreur/livraisons/{id}/deliver
    // Marquer une commande comme livrée
    // ══════════════════════════════════════════════════════════════════════
    public function markDelivered(int $id): JsonResponse
    {
        // S'assurer que la commande appartient bien au livreur connecté
        $order = Order::where('delivery_driver_id', auth()->id())
            ->findOrFail($id);

        // Vérifier que le statut permet la livraison
        if (!in_array($order->status, ['processing', 'shipped'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne peut pas être marquée comme livrée.',
            ], 422);
        }

        $order->update([
            'status'       => 'delivered',
            'delivered_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commande marquée comme livrée.',
            'order'   => $order->fresh('items'),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/livreur/stats
    // Statistiques rapides du livreur connecté
    // ══════════════════════════════════════════════════════════════════════
    public function stats(): JsonResponse
    {
        $driverId = auth()->id();

        return response()->json([
            'total'      => Order::where('delivery_driver_id', $driverId)->count(),
            'processing' => Order::where('delivery_driver_id', $driverId)->where('status', 'processing')->count(),
            'shipped'    => Order::where('delivery_driver_id', $driverId)->where('status', 'shipped')->count(),
            'delivered'  => Order::where('delivery_driver_id', $driverId)->where('status', 'delivered')->count(),
        ]);
    }
}