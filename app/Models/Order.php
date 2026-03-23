<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'guest_email',
        'address_id',
        'shipping_first_name',
        'shipping_last_name',
        'shipping_phone',
        'shipping_street',
        'shipping_city',
        'shipping_country',
        'subtotal',
        'shipping_cost',
        'discount_amount',
        'total',
        'status',
        'payment_method',
        'payment_status',
        'payment_reference',
        'notes',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancelled_reason',
        'delivery_driver_id',
    ];

    protected $casts = [
        'subtotal'        => 'float',
        'shipping_cost'   => 'float',
        'discount_amount' => 'float',
        'total'           => 'float',
        'shipped_at'      => 'datetime',
        'delivered_at'    => 'datetime',
        'cancelled_at'    => 'datetime',
    ];

    // ── Génère automatiquement le numéro de commande ───────────────────────
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                $last = static::max('id') ?? 0;
                $order->order_number = 'CM' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    // ── Relations ──────────────────────────────────────────────────────────
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryDriver()
    {
        return $this->belongsTo(User::class, 'delivery_driver_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────
    public function scopePending($q)    { return $q->where('status', 'pending'); }
    public function scopeProcessing($q) { return $q->where('status', 'processing'); }
    public function scopeDelivered($q)  { return $q->where('status', 'delivered'); }
    public function scopeCancelled($q)  { return $q->where('status', 'cancelled'); }

    // ── Accesseurs ─────────────────────────────────────────────────────────
    public function getShippingFullNameAttribute(): string
    {
        return trim("{$this->shipping_first_name} {$this->shipping_last_name}");
    }

    public function getShippingAddressAttribute(): string
    {
        return collect([
            $this->shipping_street,
            $this->shipping_city,
            $this->shipping_country,
        ])->filter()->implode(', ');
    }
}