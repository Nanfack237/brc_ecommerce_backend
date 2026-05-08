<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════
    // PUBLIC (vitrine)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::whereIn('status', ['published', 'out_of_stock'])
            ->with('category:id,name,slug')
            ->withCount('reviews');

        if ($request->filled('category')) {
            $slugs = array_filter(array_map('trim', (array) $request->input('category')));
            $query->whereHas('category', function ($q) use ($slugs) {
                $q->whereIn('slug', $slugs)
                  ->orWhereHas('parent', fn ($p) => $p->whereIn('slug', $slugs));
            });
        }

        if ($request->has('brand')) {
            $brands = array_filter(array_map('trim', (array) $request->input('brand')));
            if (!empty($brands)) {
                $query->whereIn('brand', $brands);
            }
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        self::applySpecFilters($query, $request);

        if ($request->filled('featured'))     $query->featured();
        if ($request->filled('best_seller'))  $query->bestSeller();
        if ($request->filled('is_new'))       $query->new();
        if ($request->filled('has_discount')) {
            $query->whereNotNull('old_price')->whereColumn('old_price', '>', 'price');
        }
        if ($request->filled('is_promoted'))  $query->where('is_promoted', true);

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sq) use ($q) {
                $sq->where('name',        'like', "%{$q}%")
                   ->orWhere('brand',       'like', "%{$q}%")
                   ->orWhere('description', 'like', "%{$q}%");
            });
        }

        switch ($request->get('sort', 'latest')) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'popular':
                $query->orderByDesc(
                    DB::table('order_items')
                        ->selectRaw('COALESCE(SUM(quantity), 0)')
                        ->whereColumn('order_items.product_id', 'products.id')
                )->latest('products.created_at');
                break;
            case 'discount':
                $query->orderByRaw('(old_price - price) DESC')
                      ->whereNotNull('old_price');
                break;
            default:
                $query->latest('products.created_at');
                break;
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/products/filter-counts
    //
    // Retourne les comptages EXACTS pour tous les filtres (marques + specs).
    // Vérifie STRICTEMENT le couple (clé, valeur) dans le même row JSON_TABLE :
    //   → "8 Go" dans clé RAM  ✓ compte dans Ram / 8 Go
    //   → "8 Go" dans clé Stockage ✗ ne compte PAS dans Ram / 8 Go
    //   → "8 Go DDR4" dans clé RAM ✓ compte dans Ram / 8 Go (REGEXP ^8)
    //
    // Appelé une seule fois au chargement de la boutique (ou quand les
    // filtres non-specs changent : marque, prix, promo, q).
    // ══════════════════════════════════════════════════════════════════════
    public function filterCounts(Request $request): JsonResponse
    {
        // ── Base : mêmes filtres "contextuels" que la grille (catégorie, marque,
        //    prix, promo, q) MAIS PAS les filtres specs (pour afficher tous les
        //    comptages disponibles même quand aucun spec n'est sélectionné).
        $baseQuery = Product::whereIn('status', ['published', 'out_of_stock']);

        if ($request->filled('category')) {
            $slugs = array_filter(array_map('trim', (array) $request->input('category')));
            $baseQuery->whereHas('category', function ($q) use ($slugs) {
                $q->whereIn('slug', $slugs)
                  ->orWhereHas('parent', fn ($p) => $p->whereIn('slug', $slugs));
            });
        }
        if ($request->has('brand')) {
            $brands = array_filter(array_map('trim', (array) $request->input('brand')));
            if (!empty($brands)) $baseQuery->whereIn('brand', $brands);
        }
        if ($request->filled('min_price')) $baseQuery->where('price', '>=', (float) $request->min_price);
        if ($request->filled('max_price')) $baseQuery->where('price', '<=', (float) $request->max_price);
        if ($request->filled('is_promoted')) $baseQuery->where('is_promoted', true);
        if ($request->filled('q')) {
            $q = $request->q;
            $baseQuery->where(function ($sq) use ($q) {
                $sq->where('name',        'like', "%{$q}%")
                   ->orWhere('brand',     'like', "%{$q}%")
                   ->orWhere('description', 'like', "%{$q}%");
            });
        }

        // Récupérer les IDs filtrés UNE SEULE FOIS (base sans specs)
        $baseIds = $baseQuery->pluck('id')->toArray();

        $counts = [];

        // ── Marques ──────────────────────────────────────────────────────
        // Comptage simple par valeur de colonne, pas via JSON_TABLE
        if (empty($baseIds)) {
            $counts['Marques'] = [];
        } else {
            $idList = implode(',', $baseIds);
            $counts['Marques'] = DB::table('products')
                ->selectRaw('brand, COUNT(*) as cnt')
                ->whereNotNull('brand')
                ->where('brand', '!=', '')
                ->whereRaw("id IN ({$idList})")
                ->groupBy('brand')
                ->pluck('cnt', 'brand')
                ->toArray();
        }

        // ── Définitions des filtres specs ─────────────────────────────────
        //
        // Structure : 'Label' => [
        //     'keys'    => [...alias de clés JSON acceptés...],
        //     'options' => ['Label option' => "condition SQL sur jt.jval"],
        // ]
        //
        // La garantie d'isolation clé/valeur vient du SQL généré ci-dessous :
        //   LOWER(jt.jkey) IN ('ram','memoire',...) AND (<condition_valeur>)
        // Les deux conditions portent sur le MÊME row JSON_TABLE → impossible
        // qu'une valeur dans une mauvaise clé soit comptée.
        //
        $specFilters = [

            // ── RAM ───────────────────────────────────────────────────────
            // REGEXP strict : le nombre doit être EN DÉBUT de valeur.
            // "8 Go DDR4" ✓   |   "Cache 8 Go" ✗   |   "256 Go SSD" ✗
            'Ram' => [
                'keys' => ['ram', 'memoire', 'memory', 'mem'],
                'options' => [
                    '4 Go'  => "LOWER(jt.jval) REGEXP '^4[[:space:]]*(go|gb)'",
                    '8 Go'  => "LOWER(jt.jval) REGEXP '^8[[:space:]]*(go|gb)'",
                    '16 Go' => "LOWER(jt.jval) REGEXP '^16[[:space:]]*(go|gb)'",
                    '24 Go' => "LOWER(jt.jval) REGEXP '^24[[:space:]]*(go|gb)'",
                    '32 Go' => "LOWER(jt.jval) REGEXP '^32[[:space:]]*(go|gb)'",
                ],
            ],

            // ── Stockage ──────────────────────────────────────────────────
            // Taille + type (SSD/HDD) dans la valeur, clé = stockage uniquement.
            // "256 Go SSD" ✓   |   "8 Go DDR4" ✗ (clé différente)
            'Stockage' => [
                'keys' => ['stockage', 'storage', 'disque', 'disk'],
                'options' => [
                    '128 Go SSD' => "(LOWER(jt.jval) LIKE '%128%') AND LOWER(jt.jval) LIKE '%ssd%'",
                    '256 Go SSD' => "(LOWER(jt.jval) LIKE '%256%') AND LOWER(jt.jval) LIKE '%ssd%'",
                    '512 Go SSD' => "(LOWER(jt.jval) LIKE '%512%') AND LOWER(jt.jval) LIKE '%ssd%'",
                    '1 TB SSD'   => "(LOWER(jt.jval) LIKE '%1024%' OR LOWER(jt.jval) LIKE '%1 to%' OR LOWER(jt.jval) LIKE '%1to%' OR LOWER(jt.jval) LIKE '%1 tb%') AND LOWER(jt.jval) LIKE '%ssd%'",
                    '500 Go HDD' => "(LOWER(jt.jval) LIKE '%500%') AND LOWER(jt.jval) LIKE '%hdd%'",
                    '1 TB HDD'   => "(LOWER(jt.jval) LIKE '%1024%' OR LOWER(jt.jval) LIKE '%1 to%' OR LOWER(jt.jval) LIKE '%1to%' OR LOWER(jt.jval) LIKE '%1 tb%') AND LOWER(jt.jval) LIKE '%hdd%'",
                ],
            ],

            // ── Processeur ────────────────────────────────────────────────
            // LIKE large sur la valeur, mais clé = processeur/cpu uniquement.
            // "Core i5-12450H" ✓   |   "Core i5" dans clé "ram" ✗ (impossible en pratique)
            'Processeur' => [
                'keys' => ['processeur', 'processor', 'cpu', 'proc'],
                'options' => [
                    'Dual Core' => "(LOWER(jt.jval) LIKE '%celeron%' OR LOWER(jt.jval) LIKE '%pentium%' OR LOWER(jt.jval) LIKE '%atom%' OR LOWER(jt.jval) LIKE '%dual core%' OR LOWER(jt.jval) LIKE '%dualcore%')",
                    'Core i3'   => "(LOWER(jt.jval) LIKE '%core i3%' OR LOWER(jt.jval) LIKE '%corei3%')",
                    'Core i5'   => "(LOWER(jt.jval) LIKE '%core i5%' OR LOWER(jt.jval) LIKE '%corei5%')",
                    'Core i7'   => "(LOWER(jt.jval) LIKE '%core i7%' OR LOWER(jt.jval) LIKE '%corei7%')",
                    'Core i9'   => "(LOWER(jt.jval) LIKE '%core i9%' OR LOWER(jt.jval) LIKE '%corei9%')",
                ],
            ],

            // ── Génération ────────────────────────────────────────────────
            // REGEXP STRICT : le chiffre DOIT être suivi d'un suffixe de génération.
            // "12th Gen" ✓   |   "12 Go" ✗ (pas de suffixe gen)   |   "12ième Gen" ✓
            // Sans ce matching strict, "8 Go" dans la clé génération serait compté
            // comme "8ième Gen" — ce qu'on veut absolument éviter.
            'Generation' => [
                'keys' => ['generation', 'gen', 'generati'],
                'options' => [
                    '4ieme Gen'  => "LOWER(jt.jval) REGEXP '4.*(ieme|ème|eme|th|e gen|gen)'",
                    '5ieme Gen'  => "LOWER(jt.jval) REGEXP '5.*(ieme|ème|eme|th|e gen|gen)'",
                    '6ieme Gen'  => "LOWER(jt.jval) REGEXP '6.*(ieme|ème|eme|th|e gen|gen)'",
                    '7ieme Gen'  => "LOWER(jt.jval) REGEXP '7.*(ieme|ème|eme|th|e gen|gen)'",
                    '8ieme Gen'  => "LOWER(jt.jval) REGEXP '8.*(ieme|ème|eme|th|e gen|gen)'",
                    '9ieme Gen'  => "LOWER(jt.jval) REGEXP '9.*(ieme|ème|eme|th|e gen|gen)'",
                    '10ieme Gen' => "LOWER(jt.jval) REGEXP '10.*(ieme|ème|eme|th|e gen|gen)'",
                    '11ieme Gen' => "LOWER(jt.jval) REGEXP '11.*(ieme|ème|eme|th|e gen|gen)'",
                    '12ieme Gen' => "LOWER(jt.jval) REGEXP '12.*(ieme|ème|eme|th|e gen|gen)'",
                    '13ieme Gen' => "LOWER(jt.jval) REGEXP '13.*(ieme|ème|eme|th|e gen|gen)'",
                    '14ieme Gen' => "LOWER(jt.jval) REGEXP '14.*(ieme|ème|eme|th|e gen|gen)'",
                ],
            ],

            // ── État ──────────────────────────────────────────────────────
            // Matching exact sur toute la valeur (= pas LIKE).
            // "Neuf" ✓   |   "Neuf (déballé)" ✗   |   "occasion" ✓ (LOWER)
            'Etat' => [
                'keys' => ['etat', 'condition', 'state'],
                'options' => [
                    'Neuf'     => "LOWER(jt.jval) = 'neuf'",
                    'Occasion' => "LOWER(jt.jval) = 'occasion'",
                ],
            ],
        ];

        // ── Boucle de comptage ─────────────────────────────────────────────
        //
        // Pour chaque filtre et chaque option, on exécute UN seul SELECT qui :
        //   1. Joint JSON_TABLE sur specs → un row par entrée du tableau JSON
        //   2. Filtre LOWER(jt.jkey) IN (...alias...) → garantit la bonne CLÉ
        //   3. Applique la condition sur jt.jval → garantit la bonne VALEUR
        //   4. Restreint au sous-ensemble $baseIds déjà calculé
        //
        // Résultat : comptage DISTINCT de produits (pas de rows JSON_TABLE)
        // qui ont EXACTEMENT ce spec à cette valeur, et rien d'autre.
        //
        foreach ($specFilters as $filterLabel => $def) {
            $counts[$filterLabel] = [];
            $keyList = implode("','", $def['keys']); // 'ram','memoire','memory','mem'

            foreach ($def['options'] as $optLabel => $valCond) {

                // Aucun produit dans la base filtrée → count = 0 directement
                if (empty($baseIds)) {
                    $counts[$filterLabel][$optLabel] = 0;
                    continue;
                }

                $idList = implode(',', $baseIds);

                // SQL : clé ET valeur vérifiés dans le MÊME row JSON_TABLE
                // → impossible qu'une valeur "8 Go" dans la clé "stockage"
                //   soit comptée dans le filtre Ram / 8 Go.
                $sql = "
                    SELECT COUNT(DISTINCT p.id) AS cnt
                    FROM products p
                    JOIN JSON_TABLE(
                        p.specs,
                        '\$[*]' COLUMNS (
                            jkey VARCHAR(255) PATH '\$.key',
                            jval VARCHAR(255) PATH '\$.value'
                        )
                    ) AS jt ON TRUE
                    WHERE p.status IN ('published', 'out_of_stock')
                      AND p.id IN ({$idList})
                      AND LOWER(jt.jkey) IN ('{$keyList}')
                      AND ({$valCond})
                ";

                $result = DB::selectOne($sql);
                $counts[$filterLabel][$optLabel] = (int) ($result->cnt ?? 0);
            }
        }

        return response()->json($counts);
    }

    // ══════════════════════════════════════════════════════════════════════
    // applySpecFilters
    //
    // Applique les filtres specs (spec_ram, spec_stockage, spec_processeur…)
    // à une query Eloquent. Méthode statique réutilisée par index().
    // Même logique clé+valeur que filterCounts → cohérence garantie.
    // ══════════════════════════════════════════════════════════════════════
    public static function applySpecFilters($query, Request $request): void
    {
        $keyAliases = [
            'ram'        => ['ram', 'memoire', 'memory', 'mem'],
            'stockage'   => ['stockage', 'storage', 'disque', 'disk'],
            'processeur' => ['processeur', 'processor', 'cpu', 'proc'],
            'generation' => ['generation', 'gen', 'generati'],
            'etat'       => ['etat', 'condition', 'state'],
        ];

        foreach ($request->all() as $paramKey => $paramVal) {
            if (!str_starts_with($paramKey, 'spec_')) continue;

            $vals = array_filter(array_map('trim', (array) $paramVal));
            if (empty($vals)) continue;

            $specKey      = strtolower(substr($paramKey, 5));
            $acceptedKeys = $keyAliases[$specKey] ?? [$specKey];
            $keyList      = "'" . implode("','", $acceptedKeys) . "'";

            $query->where(function ($q) use ($specKey, $keyList, $vals) {
                foreach ($vals as $specVal) {

                    // ── Dual Core ────────────────────────────────────────
                    if (strtolower($specVal) === 'dual core') {
                        $keywords = ['celeron', 'pentium', 'atom', 'dual core', 'dualcore'];
                        foreach ($keywords as $kw) {
                            $q->orWhereRaw("EXISTS (
                                SELECT 1 FROM JSON_TABLE(specs, '\$[*]' COLUMNS (
                                    jkey VARCHAR(255) PATH '\$.key',
                                    jval VARCHAR(255) PATH '\$.value'
                                )) AS jt
                                WHERE LOWER(jt.jkey) IN ({$keyList})
                                AND LOWER(jt.jval) LIKE ?
                            )", ["%{$kw}%"]);
                        }
                        continue;
                    }

                    // ── RAM : REGEXP strict — chiffre en début de valeur ─
                    if ($specKey === 'ram') {
                        preg_match('/^(\d+)\s*(go|gb)/i', $specVal, $m);
                        if ($m) {
                            $n = $m[1];
                            $q->orWhereRaw("EXISTS (
                                SELECT 1 FROM JSON_TABLE(specs, '\$[*]' COLUMNS (
                                    jkey VARCHAR(255) PATH '\$.key',
                                    jval VARCHAR(255) PATH '\$.value'
                                )) AS jt
                                WHERE LOWER(jt.jkey) IN ({$keyList})
                                AND LOWER(jt.jval) REGEXP ?
                            )", ["^{$n}[[:space:]]*(go|gb)"]);
                            continue;
                        }
                    }

                    // ── Stockage ──────────────────────────────────────────
                    if ($specKey === 'stockage') {
                        preg_match('/(\d+)\s*(go|gb|to|tb)/i', $specVal, $m);
                        if ($m) {
                            $size   = (int) $m[1];
                            $unit   = strtolower($m[2]);
                            $sizeGo = in_array($unit, ['to', 'tb']) ? $size * 1024 : $size;
                            $type   = stripos($specVal, 'ssd') !== false ? 'ssd'
                                    : (stripos($specVal, 'hdd') !== false ? 'hdd' : '');

                            $sizePatterns = ["%{$sizeGo} go%", "%{$sizeGo}go%", "%{$sizeGo} gb%"];
                            if (in_array($unit, ['to', 'tb'])) {
                                $sizePatterns[] = "%{$size} to%";
                                $sizePatterns[] = "%{$size} tb%";
                            }

                            if ($type) {
                                foreach ($sizePatterns as $pat) {
                                    $q->orWhereRaw("EXISTS (
                                        SELECT 1 FROM JSON_TABLE(specs, '\$[*]' COLUMNS (
                                            jkey VARCHAR(255) PATH '\$.key',
                                            jval VARCHAR(255) PATH '\$.value'
                                        )) AS jt
                                        WHERE LOWER(jt.jkey) IN ({$keyList})
                                        AND LOWER(jt.jval) LIKE ?
                                        AND LOWER(jt.jval) LIKE ?
                                    )", [$pat, "%{$type}%"]);
                                }
                            } else {
                                foreach ($sizePatterns as $pat) {
                                    $q->orWhereRaw("EXISTS (
                                        SELECT 1 FROM JSON_TABLE(specs, '\$[*]' COLUMNS (
                                            jkey VARCHAR(255) PATH '\$.key',
                                            jval VARCHAR(255) PATH '\$.value'
                                        )) AS jt
                                        WHERE LOWER(jt.jkey) IN ({$keyList})
                                        AND LOWER(jt.jval) LIKE ?
                                    )", [$pat]);
                                }
                            }
                            continue;
                        }
                    }

                    // ── Génération : REGEXP strict + suffixe obligatoire ──
                    if ($specKey === 'generation') {
                        preg_match('/^(\d+)/i', $specVal, $m);
                        if ($m) {
                            $gen = $m[1];
                            $q->orWhereRaw("EXISTS (
                                SELECT 1 FROM JSON_TABLE(specs, '\$[*]' COLUMNS (
                                    jkey VARCHAR(255) PATH '\$.key',
                                    jval VARCHAR(255) PATH '\$.value'
                                )) AS jt
                                WHERE LOWER(jt.jkey) IN ({$keyList})
                                AND LOWER(jt.jval) REGEXP ?
                            )", ["{$gen}.*(ieme|ème|eme|th|e gen|gen)"]);
                            continue;
                        }
                    }

                    // ── Processeur ────────────────────────────────────────
                    if ($specKey === 'processeur') {
                        $q->orWhereRaw("EXISTS (
                            SELECT 1 FROM JSON_TABLE(specs, '\$[*]' COLUMNS (
                                jkey VARCHAR(255) PATH '\$.key',
                                jval VARCHAR(255) PATH '\$.value'
                            )) AS jt
                            WHERE LOWER(jt.jkey) IN ({$keyList})
                            AND LOWER(jt.jval) LIKE ?
                        )", ['%' . strtolower($specVal) . '%']);
                        continue;
                    }

                    // ── Défaut : Etat et tout autre filtre futur ──────────
                    // Matching exact sur la valeur entière (= pas LIKE).
                    $q->orWhereRaw("EXISTS (
                        SELECT 1 FROM JSON_TABLE(specs, '\$[*]' COLUMNS (
                            jkey VARCHAR(255) PATH '\$.key',
                            jval VARCHAR(255) PATH '\$.value'
                        )) AS jt
                        WHERE LOWER(jt.jkey) IN ({$keyList})
                        AND LOWER(jt.jval) = ?
                    )", [strtolower($specVal)]);
                }
            });
        }
    }

    /**
     * GET /api/products/{slug}
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
     */
    public function promotions(Request $request): JsonResponse
    {
        $query = Product::whereIn('status', ['published', 'out_of_stock'])
            ->where('is_promoted', true)
            ->with('category:id,name,slug')
            ->withCount('reviews');

        if ($request->filled('category')) {
            $slugs = array_filter(array_map('trim', (array) $request->input('category')));
            $query->whereHas('category', function ($q) use ($slugs) {
                $q->whereIn('slug', $slugs)
                  ->orWhereHas('parent', fn ($p) => $p->whereIn('slug', $slugs));
            });
        }
        if ($request->filled('min_price')) $query->where('price', '>=', (float) $request->min_price);
        if ($request->filled('max_price')) $query->where('price', '<=', (float) $request->max_price);
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sq) use ($q) {
                $sq->where('name',        'like', "%{$q}%")
                   ->orWhere('brand',     'like', "%{$q}%")
                   ->orWhere('description', 'like', "%{$q}%");
            });
        }

        switch ($request->get('sort', 'latest')) {
            case 'price_asc':  $query->orderBy('price', 'asc'); break;
            case 'price_desc': $query->orderBy('price', 'desc'); break;
            case 'popular':
                $query->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
                      ->selectRaw('products.*, COALESCE(SUM(order_items.quantity), 0) as total_sold')
                      ->groupBy('products.id')
                      ->orderByDesc('total_sold');
                break;
            case 'discount':
                $query->orderByRaw('(old_price - price) DESC')->whereNotNull('old_price');
                break;
            default: $query->latest('products.created_at'); break;
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        return response()->json($query->paginate($perPage));
    }

    // ══════════════════════════════════════════════════════════════════════
    // ADMIN
    // ══════════════════════════════════════════════════════════════════════

    public function adminIndex(Request $request): JsonResponse
    {
        $query = Product::with('category:id,name,slug')
            ->withCount('reviews')
            ->withAvg(['reviews' => fn ($q) => $q->where('is_approved', true)], 'rating');

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('category')) $query->where('category_id', $request->category);
        if ($request->filled('is_promoted')) {
            $query->where('is_promoted', filter_var($request->is_promoted, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                   ->orWhere('sku',   'like', "%{$q}%")
                   ->orWhere('brand', 'like', "%{$q}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        return response()->json($query->latest()->paginate($perPage));
    }

    public function adminShow(int $id): JsonResponse
    {
        $product = Product::with(['category', 'reviews'])->withCount('reviews')->findOrFail($id);
        return response()->json(array_merge($product->toArray(), [
            'discount_percent' => $product->discountPercent(),
            'average_rating'   => $product->averageRating(),
        ]));
    }

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
            'is_promoted'    => 'boolean',
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

    public function update(Request $request, int $id): JsonResponse
    {
        $product   = Product::findOrFail($id);
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
            'is_promoted'    => 'boolean',
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

    public function destroy(int $id): JsonResponse
    {
        Product::findOrFail($id)->delete();
        return response()->json(['message' => 'Produit supprimé avec succès.']);
    }

    public function toggle(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update([
            'status' => $product->status === 'published' ? 'draft' : 'published',
        ]);
        return response()->json(['id' => $product->id, 'status' => $product->status]);
    }

    public function dashboardStats(): JsonResponse
    {
        $now       = now();
        $thisMonth = Product::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();
        $lastMonth = Product::whereMonth('created_at', $now->copy()->subMonth()->month)
                            ->whereYear('created_at',  $now->copy()->subMonth()->year)->count();
        $change    = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100) : 0;

        $topProducts = Product::select('products.id', 'products.name', 'products.stock')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as sales_count')
            ->leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
            ->groupBy('products.id', 'products.name', 'products.stock')
            ->orderByDesc('sales_count')
            ->limit(5)
            ->get();

        return response()->json([
            'total'        => Product::where('status', 'published')->count(),
            'change'       => $change,
            'top_products' => $topProducts,
        ]);
    }
}