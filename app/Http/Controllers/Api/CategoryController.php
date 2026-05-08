<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // PUBLIC (vitrine)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/categories
     * Catégories racines actives + leurs sous-catégories actives
     */
    public function index(): JsonResponse
    {
        $categories = Category::roots()
            ->active()
            ->oldest()
            ->withCount(['products' => fn ($q) => $q->where('status', 'published')])
            ->with(['activeChildren' => function ($q) {
                $q->withCount(['products' => fn ($q) => $q->where('status', 'published')])
                  ->oldest();
            }])
            ->get()
            ->map(function (Category $cat) {
                return [
                    'id'             => $cat->id,
                    'name'           => $cat->name,
                    'slug'           => $cat->slug,
                    'description'    => $cat->description,
                    'image'          => $cat->image,
                    'is_promoted'    => $cat->is_promoted,
                    'products_count' => $cat->products_count,
                    'children'       => $cat->activeChildren->map(fn ($s) => [
                        'id'             => $s->id,
                        'name'           => $s->name,
                        'slug'           => $s->slug,
                        'description'    => $s->description,
                        'products_count' => $s->products_count,
                    ]),
                ];
            });

        return response()->json($categories);
    }

    /**
     * GET /api/categories/{slug}
     * Détail d'une catégorie par slug
     */
    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->active()
            ->withCount(['products' => fn ($q) => $q->where('status', 'published')])
            ->with(['activeChildren' => fn ($q) => $q->withCount(['products' => fn ($q) => $q->where('status', 'published')])])
            ->firstOrFail();

        return response()->json($category);
    }

    /**
     * GET /api/categories/{slug}/products
     * Produits publiés d'une catégorie (inclut les sous-catégories)
     * Filtres supportés :
     *   - brand         : filtre sur la colonne brand
     *   - min_price     : prix minimum
     *   - max_price     : prix maximum
     *   - spec_*        : filtre sur les specs JSON (ex: spec_ram=16 Go, spec_processeur=Core i5)
     *   - sort          : latest | price_asc | price_desc | popular
     *   - per_page      : nombre de résultats par page (défaut 24)
     */
    public function products(string $slug, Request $request): JsonResponse
    {
        $category = Category::where('slug', $slug)->active()->firstOrFail();

        $childIds = $category->children()->pluck('id')->toArray();
        $allIds   = array_merge([$category->id], $childIds);

        $query = \App\Models\Product::whereIn('category_id', $allIds)
            ->where('status', 'published')
            ->with('category:id,name,slug');

        // ── Marque ────────────────────────────────────────────────────────
        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }

        // ── Prix ──────────────────────────────────────────────────────────
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // ── Specs JSON ────────────────────────────────────────────────────
        // Format en BD : [{"key":"Ram","value":"16 Go"}, {"key":"Processeur","value":"Core i5"}, ...]
        // Le frontend envoie : spec_ram=16 Go, spec_processeur=Core i5, spec_etat=Neuf, etc.
        // ── Specs JSON ────────────────────────────────────────────────────────
$specParams = collect($request->all())
    ->filter(fn ($v, $k) => str_starts_with($k, 'spec_') && filled($v));

foreach ($specParams as $paramKey => $paramValue) {
    $specKey = ucfirst(str_replace('_', ' ', substr($paramKey, 5)));
    $specKeyLower = strtolower(substr($paramKey, 5));

    $query->where(function ($q) use ($specKey, $specKeyLower, $paramValue) {

        // ── Dual Core → celeron, pentium, atom, dual ──────────────────
        if (strtolower($paramValue) === 'dual core') {
            $dualKeywords = ['celeron', 'pentium', 'atom', 'dual core', 'dualcore'];
            foreach ($dualKeywords as $kw) {
                $q->orWhereJsonContains('specs', ['key' => $specKey,      'value' => $kw])
                  ->orWhereJsonContains('specs', ['key' => $specKeyLower,  'value' => $kw]);
                $q->orWhereRaw("JSON_SEARCH(LOWER(specs), 'one', ?) IS NOT NULL", ["%{$kw}%"]);
            }
            return;
        }

        // ── Ram : cherche "8 Go" dans "8 Go DDR4 ..." ────────────────
        if ($specKeyLower === 'ram') {
            preg_match('/^(\d+)\s*(go|gb)/i', $paramValue, $m);
            if ($m) {
                $ramGo = $m[1];
                $q->orWhereRaw("JSON_SEARCH(LOWER(specs), 'one', ?) IS NOT NULL", ["%{$ramGo} go%"])
                  ->orWhereRaw("JSON_SEARCH(LOWER(specs), 'one', ?) IS NOT NULL", ["%{$ramGo}go%"])
                  ->orWhereRaw("JSON_SEARCH(LOWER(specs), 'one', ?) IS NOT NULL", ["%{$ramGo} gb%"]);
                return;
            }
        }

        // ── Stockage ──────────────────────────────────────────────────
        if ($specKeyLower === 'stockage') {
            preg_match('/(\d+)\s*(go|gb|to|tb)/i', $paramValue, $m);
            if ($m) {
                $size = $m[1];
                $unit = strtolower($m[2]);
                $type = stripos($paramValue, 'ssd') !== false ? 'ssd'
                      : (stripos($paramValue, 'hdd') !== false ? 'hdd' : '');
                if (in_array($unit, ['to', 'tb'])) $size = ($size * 1024) . ' go';
                $q->orWhereRaw("JSON_SEARCH(LOWER(specs), 'one', ?) IS NOT NULL", ["%{$size}%"]);
                if ($type) {
                    $q->whereRaw("JSON_SEARCH(LOWER(specs), 'one', ?) IS NOT NULL", ["%{$type}%"]);
                }
                return;
            }
        }

        // ── Génération ────────────────────────────────────────────────
        if ($specKeyLower === 'generation') {
            preg_match('/^(\d+)/i', $paramValue, $m);
            if ($m) {
                $gen = $m[1];
                $q->orWhereRaw("JSON_SEARCH(LOWER(specs), 'one', ?) IS NOT NULL", ["%{$gen}%"]);
                return;
            }
        }

        // ── Matching exact par défaut ──────────────────────────────────
        $q->whereJsonContains('specs', ['key' => $specKey, 'value' => $paramValue]);
        $q->orWhereRaw(
            "JSON_SEARCH(LOWER(specs), 'one', LOWER(?), NULL, '\$[*].value') IS NOT NULL
             AND JSON_SEARCH(LOWER(specs), 'one', LOWER(?), NULL, '\$[*].key') IS NOT NULL",
            [$paramValue, $specKey]
        );
    });
}

        // ── Tri ───────────────────────────────────────────────────────────
        switch ($request->get('sort', 'latest')) {
            case 'price_asc':  $query->orderBy('price', 'asc');        break;
            case 'price_desc': $query->orderBy('price', 'desc');       break;
            case 'popular':    $query->orderBy('views_count', 'desc'); break;
            default:           $query->latest();                       break;
        }

        $perPage  = (int) $request->get('per_page', 24);
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    // ══════════════════════════════════════════════════════════════════════
    // ADMIN
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/admin/categories
     * Toutes les catégories avec leurs sous-catégories et compteurs
     */
    public function adminIndex(): JsonResponse
    {
        $categories = Category::roots()
            ->ordered()
            ->withCount('products')
            ->with(['children' => function ($q) {
                $q->withCount('products')->ordered();
            }])
            ->get()
            ->map(function (Category $cat) {
                $children = $cat->children->map(fn (Category $sub) => [
                    'id'             => $sub->id,
                    'name'           => $sub->name,
                    'slug'           => $sub->slug,
                    'description'    => $sub->description,
                    'image'          => $sub->image,
                    'parent_id'      => $sub->parent_id,
                    'sort_order'     => $sub->sort_order,
                    'is_active'      => $sub->is_active,
                    'is_promoted'    => $sub->is_promoted,
                    'products_count' => $sub->products_count,
                ]);

                return [
                    'id'             => $cat->id,
                    'name'           => $cat->name,
                    'slug'           => $cat->slug,
                    'description'    => $cat->description,
                    'image'          => $cat->image,
                    'parent_id'      => null,
                    'sort_order'     => $cat->sort_order,
                    'is_active'      => $cat->is_active,
                    'is_promoted'    => $cat->is_promoted,
                    'products_count' => $cat->products_count,
                    'total_products' => $cat->totalProductsCount(),
                    'children'       => $children,
                ];
            });

        return response()->json($categories);
    }

    /**
     * POST /api/admin/categories
     * Créer une catégorie ou sous-catégorie
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'required|string|max:255|unique:categories,slug',
            'description' => 'nullable|string|max:1000',
            'image'       => 'nullable|string|max:500',
            'parent_id'   => 'nullable|exists:categories,id',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
            'is_promoted' => 'boolean',
        ]);

        // Max profondeur : pas de sous-sous-catégorie
        if (! empty($validated['parent_id'])) {
            $parent = Category::findOrFail($validated['parent_id']);
            if ($parent->isChild()) {
                return response()->json([
                    'message' => 'La profondeur maximale est de 2 niveaux (catégorie → sous-catégorie).',
                ], 422);
            }
        }

        $category = Category::create([
            'name'        => $validated['name'],
            'slug'        => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'image'       => $validated['image'] ?? null,
            'parent_id'   => $validated['parent_id'] ?? null,
            'sort_order'  => $validated['sort_order'] ?? 0,
            'is_active'   => $validated['is_active'] ?? false,
            'is_promoted' => $validated['is_promoted'] ?? false,
        ]);

        $category->loadCount('products');

        return response()->json(array_merge($category->toArray(), [
            'products_count' => $category->products_count,
            'children'       => [],
        ]), 201);
    }

    /**
     * PUT /api/admin/categories/{id}
     * Modifier une catégorie ou sous-catégorie
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'slug'        => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($id)],
            'description' => 'nullable|string|max:1000',
            'image'       => 'nullable|string|max:500',
            'parent_id'   => 'nullable|exists:categories,id',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
            'is_promoted' => 'boolean',
        ]);

        if (isset($validated['parent_id']) && $validated['parent_id'] === $id) {
            return response()->json(['message' => 'Une catégorie ne peut pas être son propre parent.'], 422);
        }

        if (! empty($validated['parent_id'])) {
            $parent = Category::findOrFail($validated['parent_id']);
            if ($parent->isChild()) {
                return response()->json([
                    'message' => 'La profondeur maximale est de 2 niveaux.',
                ], 422);
            }
        }

        $category->update($validated);
        $category->loadCount('products');

        return response()->json(array_merge($category->fresh()->toArray(), [
            'products_count' => $category->products_count,
        ]));
    }

    /**
     * DELETE /api/admin/categories/{id}
     * Supprimer une catégorie (impossible si elle a des enfants ou des produits)
     */
    public function destroy(int $id): JsonResponse
    {
        $category = Category::withCount(['children', 'products'])->findOrFail($id);

        if ($category->children_count > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer une catégorie qui contient des sous-catégories.',
            ], 422);
        }

        if ($category->products_count > 0) {
            return response()->json([
                'message' => "Impossible de supprimer une catégorie qui contient des produits ({$category->products_count} produit(s)).",
            ], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée avec succès.']);
    }

    /**
     * PATCH /api/admin/categories/{id}/toggle
     * Basculer la visibilité (is_active)
     */
    public function toggle(int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);

        return response()->json([
            'id'        => $category->id,
            'is_active' => $category->is_active,
            'message'   => $category->is_active ? 'Catégorie activée.' : 'Catégorie désactivée.',
        ]);
    }

    /**
     * POST /api/admin/categories/reorder
     * Réordonner les catégories
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items'              => 'required|array',
            'items.*.id'         => 'required|exists:categories,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->items as $item) {
            Category::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Ordre mis à jour.']);
    }
}