<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'brand',
        'sku',
        'price',
        'old_price',
        'stock',
        'category_id',
        'status',       // published | draft | archived | out_of_stock
        'is_featured',
        'is_best_seller',
        'is_new',
        'is_promoted',
        'images',       // JSON array of image paths
        'specs',        // JSON array of { key, value }
    ];

    protected $casts = [
        'price'         => 'float',
        'old_price'     => 'float',
        'stock'         => 'integer',
        'is_featured'   => 'boolean',
        'is_best_seller'=> 'boolean',
        'is_new'        => 'boolean',
        'is_promoted' => 'boolean',
        'images'        => 'array',
        'specs'         => 'array',
    ];

    // ── Relations ──────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeBestSeller($query)
    {
        return $query->where('is_best_seller', true);
    }

    public function scopeNew($query)
    {
        return $query->where('is_new', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function discountPercent(): int
    {
        if ($this->old_price && $this->old_price > $this->price) {
            return (int) round((($this->old_price - $this->price) / $this->old_price) * 100);
        }
        return 0;
    }

    public function averageRating(): float
    {
        return round($this->reviews()->where('is_approved', true)->avg('rating') ?? 0, 1);
    }
}