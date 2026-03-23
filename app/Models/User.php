<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable {
    use HasApiTokens;

    protected $fillable = [
        'first_name','last_name','username','email','phone','password',
        'role','avatar','birthdate','is_blocked',
        'blocked_reason','blocked_at','last_login_at',
    ];
    protected $hidden  = ['password','remember_token'];
    protected $casts   = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'blocked_at'        => 'datetime',
        'birthdate'         => 'date',
        'is_blocked'        => 'boolean',
    ];

    // ── Accessor : nom complet calculé automatiquement
    // Utilisation : $user->full_name → "Jean Mbala"
    public function getFullNameAttribute(): string {
        return "{$this->first_name} {$this->last_name}";
    }
    // Utilisation : $user->is_admin → true ou false
    public function getIsAdminAttribute(): bool {
        return in_array($this->role, ['admin','super_admin']);
    }

    // ── Relations
    public function addresses()    { return $this->hasMany(Address::class); }
    public function orders()       { return $this->hasMany(Order::class); }
    public function favorites()    { return $this->hasMany(Favorite::class); }
    public function reviews()      { return $this->hasMany(Review::class); }
    public function favoriteProducts() {
        return $this->belongsToMany(Product::class, 'favorites');
    }
    public function unreadNotifications() {
        return $this->notifications()->whereNull('read_at');
    }
}