<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model {
    protected $fillable = [
        'code','type','value','min_order',
        'max_uses','used_count','is_active','expires_at',
    ];
    protected $casts = [
        'is_active'  => 'boolean',
        'expires_at' => 'datetime',
    ];

    // $coupon->is_valid → true ou false
    public function getIsValidAttribute(): bool {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_uses && $this->used_count >= $this->max_uses)
            return false;
        return true;
    }

    // Calcule la réduction selon le type
    public function calculateDiscount(float $total): float {
        if ($total < $this->min_order) return 0.0;
        return $this->type === 'percent'
            ? round($total * ($this->value / 100), 2)
            : min((float)$this->value, $total); // fixed, max = total
    }
}
