<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    // La migration a created_at seulement (pas updated_at)
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_image',
        'product_sku',
        'unit_price',
        'quantity',
        'subtotal',
        'created_at',
    ];

    protected $casts = [
        'unit_price'  => 'float',
        'subtotal'    => 'float',
        'quantity'    => 'integer',
        'created_at'  => 'datetime',
    ];

    // Remplit created_at automatiquement puisque $timestamps = false
    protected static function booted(): void
    {
        static::creating(function (OrderItem $item) {
            if (empty($item->created_at)) {
                $item->created_at = now();
            }
        });
    }

    // ── Relations ──────────────────────────────────────────────────────────
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}