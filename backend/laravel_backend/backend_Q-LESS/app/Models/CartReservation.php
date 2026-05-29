<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartReservation extends Model
{
    protected $table = 'cart_reservations';

    protected $fillable = [
        'user_id',
        'producto_id',
        'cantidad',
        'status',
        'expires_at',
        'purchased_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'purchased_at' => 'datetime',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
