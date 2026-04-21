<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // PUBLIC (vitrine)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/products
     * Produits publiés avec filtres optionnels
     *
     * Logique filtres :
     *   - OR  à l'intérieur d'un groupe  → brand[]=HP&brand[]=Dell
     *   - AND entre les groupes          → brand[]=HP + spec_ram[]=4 Go
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::whereIn('status', ['published', 'out_of_stock'])
            ->with('category:id,name,slug')
            ->withCount('reviews');

        // ── Catégorie ─────────────────────────────────────────────────────
        if ($request->filled('category')) {
            $slugs = array_filter(array_map('trim', (array) $request->input('category')));
            $query->whereHas('category', function ($q) use ($slugs) {
                $q->whereIn('slug', $slugs)
                ->orWhereHas('parent', fn ($p) => $p->whereIn('slug', $slugs));
            });
        }

        // ── Marques ───────────────────────────────────────────────────────
        if ($request->has('brand')) {
            $brands = array_filter(array_map('trim', (array) $request->input('brand')));
            if (!empty($brands)) {
                $query->whereIn('brand', $brands);
            }
        }

        // ── Prix ──────────────────────────────────────────────────────────
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        // ── Specs dynamiques ──────────────────────────────────────────────
        // spec_ram[]=16 Go  →  paramKey = "spec_ram", specKey = "ram"
        // AND entre les groupes, OR entre les valeurs d'un même groupe
        foreach ($request->all() as $paramKey => $paramVal) {
            if (!str_starts_with($paramKey, 'spec_')) {
                continue;
            }

            $vals = array_filter(array_map('trim', (array) $paramVal));
            if (empty($vals)) {
                continue;
            }

            // Extrait le nom de la spec depuis le paramètre (spec_ram → ram)
            $specKey = strtolower(substr($paramKey, 5));

            $query->where(function ($q) use ($specKey, $vals) {
                foreach ($vals as $specVal) {
                    // Supporte {"key":"ram","value":"16 Go"}
                    // ET       {"key":"Ram","value":"16 Go"} (casse variable)
                    $q->orWhereJsonContains('specs', ['key' => $specKey,        'value' => $specVal])
                    ->orWhereJsonContains('specs', ['key' => ucfirst($specKey), 'value' => $specVal]);
                }
            });
        }

        // ── Flags ─────────────────────────────────────────────────────────
        if ($request->filled('featured')) {
            $query->featured();
        }
        if ($request->filled('best_seller')) {
            $query->bestSeller();
        }
        if ($request->filled('is_new')) {
            $query->new();
        }
        if ($request->filled('has_discount')) {
            $query->whereNotNull('old_price')
                ->whereColumn('old_price', '>', 'price');
        }
        if ($request->filled('is_promoted')) {
            $query->where('is_promoted', true);
        }

        // ── Recherche texte ───────────────────────────────────────────────
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sq) use ($q) {
                $sq->where('name',        'like', "%{$q}%")
                ->orWhere('brand',       'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%");
            });
        }

        // ── Tri ───────────────────────────────────────────────────────────
        match ($request->get('sort', 'latest')) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'popular'    => $query->orderByDesc('reviews_count'),
            'discount'   => $query->orderByRaw('(old_price - price) DESC')->whereNotNull('old_price'),
            default      => $query->latest(),
        };

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    /**
     * GET /api/products/{slug}
     * Détail d'un produit (published ou out_of_stock)
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->whereIn('status', ['published', 'out_of_stock'])
            ->with([
                'category:id,name,slug',
                'reviews' => fn ($q) => $q->where('is_approved', true)->latest()->limit(10),
            ])
            ->withCount(['reviews' => fn ($q) => $q->where('is_approved', true)])
            ->firstOrFail();

        return response()->json(array_merge($product->toArray(), [
            'discount_percent' => $product->discountPercent(),
            'average_rating'   => $product->averageRating(),
        ]));
    }

    /**
     * GET /api/products/search
     */
    public function search(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $products = Product::whereIn('status', ['published', 'out_of_stock'])
            ->where(function ($query) use ($q) {
                $query->where('name',  'like', "%{$q}%")
                      ->orWhere('brand', 'like', "%{$q}%")
                      ->orWhere('sku',   'like', "%{$q}%");
            })
            ->with('category:id,name,slug')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'price', 'images', 'category_id']);

        return response()->json($products);
    }

    /**
     * GET /api/promotions
     * Produits publiés avec is_promoted = true
     */
    public function promotions(Request $request): JsonResponse
    {
        $query = Product::whereIn('status', ['published', 'out_of_stock'])
            ->where('is_promoted', true)
            ->with('category:id,name,slug')
            ->withCount('reviews');

        // ── Catégorie (filtre optionnel) ───────────────────────────────────
        if ($request->filled('category')) {
            $slugs = array_filter(array_map('trim', (array) $request->input('category')));
            $query->whereHas('category', function ($q) use ($slugs) {
                $q->whereIn('slug', $slugs)
                  ->orWhereHas('parent', fn ($p) => $p->whereIn('slug', $slugs));
            });
        }

        // ── Prix ──────────────────────────────────────────────────────────
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        // ── Recherche texte ───────────────────────────────────────────────
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sq) use ($q) {
                $sq->where('name',        'like', "%{$q}%")
                   ->orWhere('brand',     'like', "%{$q}%")
                   ->orWhere('description', 'like', "%{$q}%");
            });
        }

        // ── Tri ───────────────────────────────────────────────────────────
        match ($request->get('sort', 'latest')) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'popular'    => $query->orderByDesc('reviews_count'),
            'discount'   => $query->orderByRaw('(old_price - price) DESC')->whereNotNull('old_price'),
            default      => $query->latest(),
        };

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    // ══════════════════════════════════════════════════════════════════════
    // ADMIN
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/admin/products
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Product::with('category:id,name,slug')
            ->withCount('reviews')
            ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', true)], 'rating');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->filled('is_promoted')) {
            $query->where('is_promoted', filter_var($request->is_promoted, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sq) use ($q) {
                $sq->where('name',  'like', "%{$q}%")
                   ->orWhere('sku',   'like', "%{$q}%")
                   ->orWhere('brand', 'like', "%{$q}%");
            });
        }

        $perPage  = min((int) $request->get('per_page', 20), 100);
        $products = $query->latest()->paginate($perPage);

        return response()->json($products);
    }

    /**
     * GET /api/admin/products/{id}
     */
    public function adminShow(int $id): JsonResponse
    {
        $product = Product::with(['category', 'reviews'])
            ->withCount('reviews')
            ->findOrFail($id);

        return response()->json(array_merge($product->toArray(), [
            'discount_percent' => $product->discountPercent(),
            'average_rating'   => $product->averageRating(),
        ]));
    }

    /**
     * POST /api/admin/products
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'slug'           => 'required|string|max:255|unique:products,slug',
            'description'    => 'nullable|string',
            'brand'          => 'nullable|string|max:100',
            'sku'            => 'nullable|string|max:100|unique:products,sku',
            'price'          => 'required|numeric|min:0',
            'old_price'      => 'nullable|numeric|min:0',
            'stock'          => 'required|integer|min:0',
            'category_id'    => 'required|exists:categories,id',
            'status'         => 'required|in:published,draft,archived,out_of_stock',
            'is_featured'    => 'boolean',
            'is_best_seller' => 'boolean',
            'is_new'         => 'boolean',
            'is_promoted'    => 'boolean',  // ← AJOUT
            'images'         => 'nullable|array',
            'images.*'       => 'nullable|string',
            'specs'          => 'nullable|array',
            'specs.*.key'    => 'required_with:specs|string',
            'specs.*.value'  => 'required_with:specs|string',
        ]);

        $product = Product::create($validated);
        $product->load('category:id,name,slug');
        $product->loadCount('reviews');

        return response()->json($product, 201);
    }

    /**
     * PUT /api/admin/products/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name'           => 'sometimes|required|string|max:255',
            'slug'           => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($id)],
            'description'    => 'nullable|string',
            'brand'          => 'nullable|string|max:100',
            'sku'            => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($id)],
            'price'          => 'sometimes|required|numeric|min:0',
            'old_price'      => 'nullable|numeric|min:0',
            'stock'          => 'sometimes|required|integer|min:0',
            'category_id'    => 'sometimes|required|exists:categories,id',
            'status'         => 'sometimes|required|in:published,draft,archived,out_of_stock',
            'is_featured'    => 'boolean',
            'is_best_seller' => 'boolean',
            'is_new'         => 'boolean',
            'is_promoted'    => 'boolean',  // ← AJOUT
            'images'         => 'nullable|array',
            'images.*'       => 'nullable|string',
            'specs'          => 'nullable|array',
            'specs.*.key'    => 'required_with:specs|string',
            'specs.*.value'  => 'required_with:specs|string',
        ]);

        $product->update($validated);
        $product->load('category:id,name,slug');
        $product->loadCount('reviews');

        return response()->json(array_merge($product->fresh()->toArray(), [
            'discount_percent' => $product->discountPercent(),
        ]));
    }

    /**
     * DELETE /api/admin/products/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        Product::findOrFail($id)->delete();

        return response()->json(['message' => 'Produit supprimé avec succès.']);
    }

    /**
     * PATCH /api/admin/products/{id}/toggle
     * Basculer published ↔ draft
     */
    public function toggle(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update([
            'status' => $product->status === 'published' ? 'draft' : 'published',
        ]);

        return response()->json([
            'id'     => $product->id,
            'status' => $product->status,
        ]);
    }
}