<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    // GET /api/wishlist — liste des favoris de l'utilisateur connecté
    public function index(): JsonResponse
    {
        $items = Wishlist::where('user_id', auth()->id())
            ->with(['product' => function ($q) {
                $q->select('id', 'name', 'slug', 'price', 'old_price', 'stock', 'images', 'category_id')
                  ->with('category:id,name');
            }])
            ->latest()
            ->get()
            ->filter(fn($w) => $w->product !== null) // exclure les produits supprimés
            ->map(fn($w) => [
                'id'        => $w->product->id,
                'name'      => $w->product->name,
                'slug'      => $w->product->slug,
                'price'     => (float) $w->product->price,
                'old_price' => $w->product->old_price ? (float) $w->product->old_price : null,
                'stock'     => (int) $w->product->stock,
                'category'  => $w->product->category?->name,
                'image'     => $w->product->images[0] ?? null,
                'wishlist_id' => $w->id, // pour la suppression
            ])
            ->values();

        return response()->json($items);
    }

    // POST /api/wishlist/{productId} — ajouter aux favoris
    public function store(int $productId): JsonResponse
    {
        // Vérifie que le produit existe
        if (!\App\Models\Product::find($productId)) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }

        // Crée seulement si pas déjà en favori (unique constraint)
        $wishlist = Wishlist::firstOrCreate([
            'user_id'    => auth()->id(),
            'product_id' => $productId,
        ]);

        return response()->json([
            'success' => true,
            'message' => $wishlist->wasRecentlyCreated ? 'Ajouté aux favoris.' : 'Déjà dans vos favoris.',
            'in_wishlist' => true,
        ], $wishlist->wasRecentlyCreated ? 201 : 200);
    }

    // DELETE /api/wishlist/{productId} — retirer des favoris
    public function destroy(int $productId): JsonResponse
    {
        $deleted = Wishlist::where('user_id', auth()->id())
            ->where('product_id', $productId)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Favori introuvable.'], 404);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Retiré des favoris.',
            'in_wishlist' => false,
        ]);
    }

    // GET /api/wishlist/check/{productId} — vérifie si un produit est en favori
    public function check(int $productId): JsonResponse
    {
        $inWishlist = Wishlist::where('user_id', auth()->id())
            ->where('product_id', $productId)
            ->exists();

        return response()->json(['in_wishlist' => $inWishlist]);
    }
}