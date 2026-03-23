<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ReviewController;

// ══════════════════════════════════════════════════════════════════════════
// AUTH (public)
// ══════════════════════════════════════════════════════════════════════════

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get ('/me',     [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// ══════════════════════════════════════════════════════════════════════════
// PUBLIC — Vitrine
// ══════════════════════════════════════════════════════════════════════════

Route::prefix('categories')->group(function () {
    Route::get('/',                [CategoryController::class, 'index']);
    Route::get('/{slug}/products', [CategoryController::class, 'products']);
    Route::get('/{slug}',          [CategoryController::class, 'show']);
});

Route::prefix('products')->group(function () {
    Route::get('/',       [ProductController::class, 'index']);
    Route::get('/search', [ProductController::class, 'search']); // ← AVANT /{slug}
    Route::get('/{slug}', [ProductController::class, 'show']);
});

Route::get('/products/{id}/reviews', [ReviewController::class, 'index']);

// ══════════════════════════════════════════════════════════════════════════
// COMPTE CLIENT (auth:sanctum)
// ══════════════════════════════════════════════════════════════════════════

Route::middleware('auth:sanctum')->group(function () {

    // Profil
    Route::put   ('/profile',          [AuthController::class, 'updateProfile']);
    Route::put   ('/profile/password', [AuthController::class, 'updatePassword']);
    Route::delete('/profile',          [AuthController::class, 'deleteAccount']);

    // Wishlist
    Route::get   ('/wishlist',                   [WishlistController::class, 'index']);
    Route::get   ('/wishlist/check/{productId}', [WishlistController::class, 'check']);
    Route::post  ('/wishlist/{productId}',        [WishlistController::class, 'store']);
    Route::delete('/wishlist/{productId}',        [WishlistController::class, 'destroy']);

    // Commandes client
    Route::prefix('orders')->group(function () {
        Route::post('checkout',     [OrderController::class, 'checkout']);
        Route::get ('/',            [OrderController::class, 'myOrders']);
        Route::get ('/{id}',        [OrderController::class, 'show']);
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
    });

    // Reviews
    Route::post  ('/products/{id}/reviews', [ReviewController::class, 'store']);
    Route::put   ('/reviews/{id}',          [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}',          [ReviewController::class, 'destroy']);
});

// ══════════════════════════════════════════════════════════════════════════
// ADMIN (auth:sanctum + role:admin)
// ══════════════════════════════════════════════════════════════════════════

Route::middleware(['auth:sanctum', 'role:admin,user'])
    ->prefix('admin')
    ->group(function () {

    // ── Catégories ────────────────────────────────────────────────────────
    Route::prefix('categories')->group(function () {
        Route::get   ('/',            [CategoryController::class, 'adminIndex']);
        Route::post  ('/reorder',     [CategoryController::class, 'reorder']); // ← AVANT /{id}
        Route::post  ('/',            [CategoryController::class, 'store']);
        Route::put   ('/{id}',        [CategoryController::class, 'update']);
        Route::delete('/{id}',        [CategoryController::class, 'destroy']);
        Route::patch ('/{id}/toggle', [CategoryController::class, 'toggle']);
    });

    // ── Produits ──────────────────────────────────────────────────────────
    Route::prefix('products')->group(function () {
        Route::get   ('/',            [ProductController::class, 'adminIndex']);
        Route::post  ('/',            [ProductController::class, 'store']);
        Route::get   ('/{id}',        [ProductController::class, 'adminShow']);
        Route::put   ('/{id}',        [ProductController::class, 'update']);
        Route::delete('/{id}',        [ProductController::class, 'destroy']);
        Route::patch ('/{id}/toggle', [ProductController::class, 'toggle']);
    });

    // ── Commandes ─────────────────────────────────────────────────────────
    Route::prefix('orders')->group(function () {
        Route::get  ('/stats',       [OrderController::class,  'stats']);       // ← AVANT /{id}
        Route::get  ('/',            [OrderController::class,  'adminIndex']);
        Route::get  ('/{id}',        [OrderController::class,  'adminShow']);
        Route::patch('/{id}/status', [OrderController::class,  'updateStatus']);
        Route::patch('/{id}/payment-status', [OrderController::class,  'updatePaymentStatus']);
        Route::patch('/{id}/assign', [OrderController::class,  'assignDelivery']); // assigner livreur → delivery_driver_id + shipped_at
    });

    // ── Avis ──────────────────────────────────────────────────────────────
    Route::prefix('reviews')->group(function () {
        Route::get   ('/',             [ReviewController::class, 'adminIndex']);
        Route::patch ('/{id}/approve', [ReviewController::class, 'approve']);
        Route::patch ('/{id}/reject',  [ReviewController::class, 'reject']);
        Route::delete('/{id}',         [ReviewController::class, 'adminDestroy']);
    });

    // ── Utilisateurs — tout dans AuthController ───────────────────────────
    Route::prefix('users')->group(function () {
        Route::get   ('/',             [AuthController::class, 'listUsers']);    // liste
        Route::post  ('/',             [AuthController::class, 'createUser']);   // créer livreur/admin
        Route::get   ('/{id}',         [AuthController::class, 'showUser']);     // détail
        Route::patch ('/{id}/block',   [AuthController::class, 'blockUser']);    // bloquer
        Route::patch ('/{id}/unblock', [AuthController::class, 'unblockUser']); // débloquer
        Route::patch ('/{id}/role',    [AuthController::class, 'updateRole']);   // changer rôle
    });

});