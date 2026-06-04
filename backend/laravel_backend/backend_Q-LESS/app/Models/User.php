<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'email_verification_token',
        'email_verification_sent_at',
        'telefono',
        'rol',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_verification_sent_at' => 'datetime',
    ];

    public function favoriteProducts()
    {
        return $this->belongsToMany(Producto::class, 'favorite_products', 'user_id', 'producto_id')
            ->withTimestamps();
    }
}
