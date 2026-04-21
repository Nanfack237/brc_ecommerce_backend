<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    private const MARCHAND_OM   = '#150*14*278956*696923379*Montant#';
    private const MARCHAND_MOMO = '*126*14*271452*678451236*Montant#';

    // ══════════════════════════════════════════════════════════════════════
    // POST /api/orders/checkout
    // ══════════════════════════════════════════════════════════════════════
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
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
            'notes'            => 'nullable|string|max:1000',
        ]);

        $user   = $request->user('sanctum');
        $userId = $user?->id;

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié.',
            ], 401);
        }

        $paymentMethod = match($data['payment']) {
            'momo' => 'mobile_money',
            'om' => 'orange_money',
            default      => 'cash_on_delivery',
        };

        $nameParts = explode(' ', trim($data['nom']), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName  = $nameParts[1] ?? '';

        DB::beginTransaction();
        try {
            $order = Order::create([
                'user_id'             => $userId,
                'guest_email'         => $data['email'] ?? null,
                'shipping_first_name' => $firstName,
                'shipping_last_name'  => $lastName,
                'shipping_phone'      => $data['phone'],
                'shipping_street'     => $data['quartier'] ?? null,
                'shipping_city'       => $data['ville']    ?? null,
                'shipping_country'    => $data['pays']     ?? null,
                'subtotal'            => $data['subtotal'],
                'shipping_cost'       => 0, // ← sera défini par l'admin
                'discount_amount'     => $data['discount'] ?? 0,
                'total'               => $data['total'],
                'status'              => 'pending',
                'payment_method'      => $paymentMethod,
                'payment_status'      => 'unpaid',
                'notes'               => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $image = $item['image'] ?? null;
                if ($image && str_starts_with($image, 'data:')) {
                    $image = null;
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

            // ── Email initial (sans frais de livraison) ───────────────────
            // $emailRecipient = $data['email'] ?? null;
            // if ($emailRecipient) {
            //     try {
            //         Mail::to($emailRecipient)->send(new OrderConfirmationMail([
            //             'nom'          => $data['nom'],
            //             'email'        => $emailRecipient,
            //             'adresse'      => $data['adresse'],
            //             'order_number' => $order->order_number,
            //             'payment'      => $data['payment'],
            //             'subtotal'     => $data['subtotal'],
            //             'livraison'    => 0, // frais pas encore définis
            //             'discount'     => $data['discount'] ?? 0,
            //             'total'        => $data['total'],
            //             'items'        => $data['items'],
            //             'shipping_confirmed' => false,
            //         ]));
            //     } catch (\Throwable $e) {
            //         Log::warning('Email confirmation non envoyé', [
            //             'order' => $order->order_number,
            //             'error' => $e->getMessage(),
            //         ]);
            //     }
            // }

            return response()->json([
                'success'    => true,
                'ref'        => $order->order_number,
                'order_id'   => $order->id,
                'email_sent' => !empty($emailRecipient),
                'message'    => 'Commande enregistrée avec succès.',
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Checkout failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande.',
                'debug'   => $e->getMessage(),
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // PATCH /api/admin/orders/{id}/shipping-cost
    // ══════════════════════════════════════════════════════════════════════
    public function setShippingCost(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $order = Order::with(['items.product', 'user'])->findOrFail($id);
        $order->shipping_cost = $data['shipping_cost'];
        $order->total         = $order->subtotal + $data['shipping_cost'] - ($order->discount_amount ?? 0);
        $order->save();

        // ── Email avec frais confirmés ────────────────────────────────────
        $emailRecipient = $order->guest_email ?? $order->user?->email ?? null;

        if ($emailRecipient) {
            try {
                $orderData = [
                    'nom'          => trim("{$order->shipping_first_name} {$order->shipping_last_name}"),
                    'email'        => $emailRecipient,
                    'adresse'      => implode(', ', array_filter([
                                        $order->shipping_street,
                                        $order->shipping_city,
                                        $order->shipping_country,
                                     ])),
                    'order_number' => $order->order_number,
                    'payment'      => match($order->payment_method) {
                                        'mobile_money'     => 'momo',
                                        'orange_money'     => 'om',
                                        'cash_on_delivery' => 'cash',
                                        default            => 'cash',
                                      },
                    'subtotal'           => $order->subtotal,
                    'livraison'          => $order->shipping_cost,
                    'discount'           => $order->discount_amount ?? 0,
                    'total'              => $order->total,
                    'shipping_confirmed' => true,
                    'items'              => $order->items->map(fn($item) => [
                                                'name'     => $item->product_name,
                                                'image'    => $item->product_image,
                                                'price'    => $item->unit_price,
                                                'quantity' => $item->quantity,
                                           ])->toArray(),
                ];

                Mail::to($emailRecipient)->send(new OrderConfirmationMail($orderData));
            } catch (\Throwable $e) {
                Log::warning('Email frais livraison non envoyé', [
                    'order' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success'    => true,
            'email_sent' => !empty($emailRecipient),
            'order'      => $order->fresh(['items', 'deliveryDriver']),
            'message'    => 'Frais de livraison mis à jour' . ($emailRecipient ? ' et email envoyé.' : '.'),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/orders  — Client : mes commandes
    // ══════════════════════════════════════════════════════════════════════
    public function myOrders(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', auth()->id())
            ->with('items')
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/orders/{id}
    // ══════════════════════════════════════════════════════════════════════
    public function show(int $id): JsonResponse
    {
        $order = Order::where('user_id', auth()->id())
            ->with('items')
            ->findOrFail($id);

        return response()->json($order);
    }

    // ══════════════════════════════════════════════════════════════════════
    // POST /api/orders/{id}/cancel
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
    // GET /api/admin/orders
    // ══════════════════════════════════════════════════════════════════════
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Order::with([
            'items',
            'user:id,first_name,last_name,email,phone',
            'deliveryDriver:id,first_name,last_name,phone',
        ])->latest();

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
            $query->paginate($request->get('per_page', 200))
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/orders/{id}
    // ══════════════════════════════════════════════════════════════════════
    public function adminShow(Order $order): JsonResponse
    {
        return response()->json($order->load('items'));
    }

    // ══════════════════════════════════════════════════════════════════════
    // PATCH /api/admin/orders/{id}/status
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

        if ($data['status'] === 'shipped' && !$order->shipped_at) {
            $order->shipped_at = now();
        }
        if ($data['status'] === 'delivered' && !$order->delivered_at) {
            $order->delivered_at = now();
        }
        if ($data['status'] === 'cancelled' && !$order->cancelled_at) {
            $order->cancelled_at     = now();
            $order->cancelled_reason = $data['cancelled_reason'] ?? "Annulée par l'admin";
        }

        $order->status = $data['status'];

        if (!empty($data['payment_status']))    $order->payment_status    = $data['payment_status'];
        if (!empty($data['payment_reference'])) $order->payment_reference = $data['payment_reference'];

        $order->save();

        return response()->json([
            'success' => true,
            'order'   => $order->fresh(['items', 'deliveryDriver']),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // PATCH /api/admin/orders/{id}/payment-status
    // ══════════════════════════════════════════════════════════════════════
    public function updatePaymentStatus(Request $request, int $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        $data = $request->validate([
            'payment_status' => 'required|in:unpaid,paid,refunded',
        ]);

        if ($data['payment_status'] === 'paid' && $order->payment_status !== 'paid') {
            $order->load('items.product');
            foreach ($order->items as $item) {
                $product = $item->product;
                if (!$product) continue;
                $newStock = max(0, $product->stock - $item->quantity);
                $product->stock = $newStock;
                if ($newStock === 0) $product->status = 'out_of_stock';
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
    // PATCH /api/admin/orders/{id}/assign
    // ══════════════════════════════════════════════════════════════════════
    public function assignDelivery(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'delivery_driver_id' => 'required|exists:users,id',
        ]);

        $order = Order::findOrFail($id);
        $order->update([
            'delivery_driver_id' => $data['delivery_driver_id'],
            'status'             => 'processing',
            'shipped_at'         => now(),
        ]);

        $order->load('deliveryDriver:id,first_name,last_name,phone');

        return response()->json([
            'success' => true,
            'order'   => $order,
            'message' => 'Livreur assigné et commande en cours.',
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/admin/orders/stats
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