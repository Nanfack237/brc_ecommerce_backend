<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    private const MARCHAND_OM   = '075 XX XX XX'; // ← remplacer par vrai numéro
    private const MARCHAND_MOMO = '650 XX XX XX'; // ← remplacer par vrai numéro

    // ══════════════════════════════════════════════════════════════════════
    // POST /api/orders/checkout  — Public (invité OU connecté)
    // ══════════════════════════════════════════════════════════════════════
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ref'              => 'nullable|string|max:30',
            'nom'              => 'required|string|max:200',
            'email'            => 'nullable|email|max:200',
            'phone'            => 'required|string|max:30',
            'adresse'          => 'required|string|max:500',
            'ville'            => 'nullable|string|max:100',
            'quartier'         => 'nullable|string|max:100',
            'pays'             => 'nullable|string|max:100',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'nullable|integer',
            'items.*.name'     => 'required|string|max:500',
            'items.*.price'    => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.image'    => 'nullable|string',
            'items.*.slug'     => 'nullable|string',
            'subtotal'         => 'required|numeric|min:0',
            'livraison'        => 'required|numeric|min:0',
            'discount'         => 'nullable|numeric|min:0',
            'total'            => 'required|numeric|min:0',
            'payment'          => 'required|in:om,momo,cash',
            'shipping'         => 'required|in:standard,express',
            'promo_code'       => 'nullable|string|max:30',
            'notes'            => 'nullable|string|max:1000',
        ]);

        // ── Récupérer l'utilisateur connecté via le token Bearer ─────────────
        // auth('sanctum')->user() force la résolution du token même si le guard
        // par défaut n'est pas sanctum
        $user   = $request->user('sanctum');
        $userId = $user?->id;

        // Log de debug — retirer en production
        Log::info('Checkout user', ['user_id' => $userId, 'token' => $request->bearerToken()]);

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.',
            ], 401);
        }

        $paymentMethod = match($data['payment']) {
            'om', 'momo' => 'mobile_money',
            default      => 'cash_on_delivery',
        };

        $marchand = match($data['payment']) {
            'om'    => self::MARCHAND_OM,
            'momo'  => self::MARCHAND_MOMO,
            default => null,
        };

        $nameParts = explode(' ', trim($data['nom']), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName  = $nameParts[1] ?? '';

        DB::beginTransaction();
        try {
            $order = Order::create([
                // order_number généré automatiquement par booted() dans Order.php
                'user_id'             => $userId,
                'guest_email'         => $data['email'] ?? null, // ← email du formulaire pour la confirmation
                'shipping_first_name' => $firstName,
                'shipping_last_name'  => $lastName,
                'shipping_phone'      => $data['phone'],
                'shipping_street'     => $data['quartier'] ?? null,
                'shipping_city'       => $data['ville']    ?? null,
                'shipping_country'    => $data['pays']     ?? null,
                'subtotal'            => $data['subtotal'],
                'shipping_cost'       => $data['livraison'],
                'discount_amount'     => $data['discount'] ?? 0,
                'total'               => $data['total'],
                'status'              => 'pending',
                'payment_method'      => $paymentMethod,
                'payment_status'      => 'unpaid',
                'notes'               => implode(' | ', array_filter([
                                            !empty($data['notes'])      ? $data['notes']                        : null,
                                            !empty($data['promo_code']) ? "Code promo : {$data['promo_code']}"  : null,
                                        ])) ?: null,
            ]);

            foreach ($data['items'] as $item) {
                // Ignorer les images base64 (trop longues) — garder uniquement les URLs http
                $image = $item['image'] ?? null;
                if ($image && str_starts_with($image, 'data:')) {
                    $image = null; // base64 → null, l'image Cloudinary sera chargée via product_id
                }

                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item['id']    ?? null,
                    'product_name'  => $item['name'],
                    'product_image' => $image,
                    'product_sku'   => $item['slug']  ?? null,
                    'unit_price'    => $item['price'],
                    'quantity'      => $item['quantity'],
                    'subtotal'      => $item['price'] * $item['quantity'],
                ]);
            }

            DB::commit();

            // ── Envoi email ───────────────────────────────────────────────
            $emailRecipient = $data['email'] ?? null;
            if ($emailRecipient) {
                try {
                    Mail::to($emailRecipient)->send(new OrderConfirmationMail(
                        array_merge($data, [
                            'marchand'     => $marchand,
                            'order_number' => $order->order_number,
                        ])
                    ));
                } catch (\Throwable $e) {
                    Log::warning('Email confirmation non envoyé', [
                        'order' => $order->order_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success'    => true,
                'ref'        => $order->order_number,
                'order_id'   => $order->id,
                'email_sent' => !empty($emailRecipient),
                'message'    => 'Commande enregistrée avec succès.',
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Checkout failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande.',
                // DEBUG — retirer en production
                'debug'   => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => basename($e->getFile()),
            ], 500);
        }
    }

    public function myOrders(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', auth()->id())
            ->with('items')
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/orders/{id}  — Client connecté : détail d'une commande
    // ══════════════════════════════════════════════════════════════════════
    public function show(int $id): JsonResponse
    {
        $order = Order::where('user_id', auth()->id())
            ->with('items')
            ->findOrFail($id);

        return response()->json($order);
    }

    // ══════════════════════════════════════════════════════════════════════
    // POST /api/orders/{id}/cancel  — Client connecté : annuler
    // ══════════════════════════════════════════════════════════════════════
    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', auth()->id())->findOrFail($id);

        if (!in_array($order->status, ['pending', 'processing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne peut plus être annulée.',
            ], 422);
        }

        $order->update([
            'status'           => 'cancelled',
            'cancelled_at'     => now(),
            'cancelled_reason' => $request->input('reason', 'Annulée par le client'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commande annulée.',
            'order'   => $order->fresh('items'),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/orders  — Admin : toutes les commandes
    // ══════════════════════════════════════════════════════════════════════
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Order::with(['items', 'user:id,first_name,last_name,email,phone', 'deliveryDriver:id,first_name,last_name,phone'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('order_number',         'like', "%{$s}%")
                  ->orWhere('shipping_first_name', 'like', "%{$s}%")
                  ->orWhere('shipping_last_name',  'like', "%{$s}%")
                  ->orWhere('shipping_phone',      'like', "%{$s}%")
                  ->orWhere('guest_email',         'like', "%{$s}%");
            });
        }

        return response()->json(
            $query->paginate($request->get('per_page', 20))
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/orders/{id}  — Admin : détail commande
    // ══════════════════════════════════════════════════════════════════════
    public function adminShow(Order $order): JsonResponse
    {
        return response()->json($order->load('items'));
    }

    // ══════════════════════════════════════════════════════════════════════
    // PATCH /api/admin/orders/{id}/status  — Admin : changer statut
    // ══════════════════════════════════════════════════════════════════════
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $data = $request->validate([
            'status'            => 'required|in:pending,processing,shipped,delivered,cancelled,refunded',
            'payment_status'    => 'nullable|in:unpaid,paid,refunded',
            'payment_reference' => 'nullable|string|max:100',
            'cancelled_reason'  => 'nullable|string|max:500',
        ]);

        // Gestion des dates
        if ($data['status'] === 'shipped' && !$order->shipped_at) {
            $order->shipped_at = now();
        }
        if ($data['status'] === 'delivered' && !$order->delivered_at) {
            $order->delivered_at = now();
        }
        if ($data['status'] === 'cancelled' && !$order->cancelled_at) {
            $order->cancelled_at = now();
            // Utilisation de guillemets doubles pour éviter l'erreur sur l'apostrophe
            $order->cancelled_reason = $data['cancelled_reason'] ?? "Annulée par l'admin";
        }

        $order->status = $data['status'];
        
        if (!empty($data['payment_status'])) {
            $order->payment_status = $data['payment_status'];
        }
        if (!empty($data['payment_reference'])) {
            $order->payment_reference = $data['payment_reference'];
        }

        $order->save();

        return response()->json([
            'success' => true,
            'order'   => $order->fresh(['items', 'deliveryDriver']),
        ]);
    }


    public function updatePaymentStatus(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $data = $request->validate([
            'payment_status' => 'required|in:unpaid,paid,refunded',
        ]);

        // Déduire le stock uniquement quand on passe à "paid"
        // et que le statut précédent n'était pas déjà "paid"
        if ($data['payment_status'] === 'paid' && $order->payment_status !== 'paid') {

            // Charger les items avec leurs produits en une seule requête
            $order->load('items.product');

            foreach ($order->items as $item) {
                $product = $item->product;

                if (!$product) continue;

                // Déduire la quantité commandée du stock
                $newStock = max(0, $product->stock - $item->quantity);
                $product->stock = $newStock;

                // Passer automatiquement en out_of_stock si stock épuisé
                if ($newStock === 0) {
                    $product->status = 'out_of_stock';
                }

                $product->save();
            }
        }

        $order->payment_status = $data['payment_status'];
        $order->save();

        return response()->json([
            'success' => true,
            'order'   => $order->load('items.product'),
        ]);
    }
    // ══════════════════════════════════════════════════════════════════════
    // PATCH /api/admin/orders/{order}/assign — Assigner un livreur
    // Met à jour delivery_driver_id + shipped_at sur la commande existante
    // ══════════════════════════════════════════════════════════════════════
    public function assignDelivery(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'delivery_driver_id' => 'required|exists:users,id',
        ]);

        // 1. On récupère l'instance existante
        $order = Order::findOrFail($id);

        // 2. On met à jour les champs
        $order->update([
            'delivery_driver_id' => $data['delivery_driver_id'],
            'status'             => 'processing', // "En cours" comme tu as demandé
            'shipped_at'         => now(),
        ]);

        // 3. On recharge les relations pour le front-end (pour avoir le nom du livreur)
        $order->load('deliveryDriver:id,first_name,last_name,phone');

        return response()->json([
            'success' => true,
            'order'   => $order,
            'message' => "Livreur assigné et commande en cours."
        ]);
    }
    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/orders/stats  — Admin : statistiques dashboard
    // ══════════════════════════════════════════════════════════════════════
    public function stats(): JsonResponse
    {
        return response()->json([
            'total'         => Order::count(),
            'pending'       => Order::where('status', 'pending')->count(),
            'processing'    => Order::where('status', 'processing')->count(),
            'shipped'       => Order::where('status', 'shipped')->count(),
            'delivered'     => Order::where('status', 'delivered')->count(),
            'cancelled'     => Order::where('status', 'cancelled')->count(),
            'revenue_total' => Order::where('payment_status', 'paid')->sum('total'),
            'revenue_month' => Order::where('payment_status', 'paid')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at',  now()->year)
                ->sum('total'),
            'orders_today'  => Order::whereDate('created_at', today())->count(),
        ]);
    }


}