<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    // ── GET /products/{id}/reviews — public ──────────────────────────────────
    // Retourne uniquement les avis approuvés (is_approved = true), max 5
    public function index($id)
    {
        $product = Product::findOrFail($id);

        $reviews = Review::with('user:id,first_name,last_name')
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->latest()
            ->limit(5)
            ->get();

        return response()->json($reviews);
    }

    // ── GET /my-reviews — auth ────────────────────────────────────────────────
    // Retourne tous les avis de l'utilisateur connecté (approuvés + en attente)
    public function myReviews(Request $request)
    {
        $reviews = Review::with('product:id,name,slug,images')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json(['data' => $reviews]);
    }

    // ── POST /products/{id}/reviews — auth ────────────────────────────────────
    public function store(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'rating'   => 'required|integer|min:1|max:5',
            'comment'  => 'required|string|max:500',
            'order_id' => 'nullable|integer|exists:orders,id',
        ]);

        // Un seul avis par produit par utilisateur
        $existing = Review::where('product_id', $product->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Vous avez déjà soumis un avis pour ce produit.'], 422);
        }

        $review = Review::create([
            'product_id'  => $product->id,
            'user_id'     => Auth::id(),
            'order_id'    => $data['order_id'] ?? null,
            'rating'      => $data['rating'],
            'comment'     => $data['comment'],
            'is_approved' => false,
        ]);

        return response()->json([
            'message' => 'Avis soumis avec succès, en attente de validation.',
            'review'  => $review->load('user:id,first_name,last_name'),
        ], 201);
    }

    // ── PUT /reviews/{id} — auth ──────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        if ($review->user_id !== Auth::id()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $data = $request->validate([
            'rating'  => 'sometimes|integer|min:1|max:5',
            'comment' => 'sometimes|string|max:500',
        ]);

        $review->update(array_merge($data, ['is_approved' => false]));

        return response()->json($review->load('user:id,first_name,last_name'));
    }

    // ── DELETE /reviews/{id} — auth ───────────────────────────────────────────
    public function destroy($id)
    {
        $review = Review::findOrFail($id);

        if ($review->user_id !== Auth::id()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Avis supprimé.']);
    }

    // ── ADMIN : GET /admin/reviews ────────────────────────────────────────────
    public function adminIndex(Request $request)
    {
        $query = Review::with([
            'user:id,first_name,last_name',
            'product:id,name,slug',
        ])->latest();

        if ($request->has('approved')) {
            $query->where('is_approved', filter_var($request->approved, FILTER_VALIDATE_BOOLEAN));
        }

        $reviews = $query->paginate($request->input('per_page', 20));

        return response()->json($reviews);
    }

    // ── ADMIN : PATCH /admin/reviews/{id}/approve ─────────────────────────────
    public function approve($id)
    {
        $review = Review::with('product')->findOrFail($id);
        $review->update(['is_approved' => true]);

        return response()->json(['message' => 'Avis approuvé.', 'review' => $review]);
    }

    // ── ADMIN : PATCH /admin/reviews/{id}/reject ──────────────────────────────
    public function reject($id)
    {
        $review = Review::findOrFail($id);
        $review->update(['is_approved' => false]);

        return response()->json(['message' => 'Avis rejeté.', 'review' => $review]);
    }

    // ── ADMIN : DELETE /admin/reviews/{id} ────────────────────────────────────
    public function adminDestroy($id)
    {
        $review = Review::findOrFail($id);
        $review->delete();

        return response()->json(['message' => 'Avis supprimé.']);
    }
}