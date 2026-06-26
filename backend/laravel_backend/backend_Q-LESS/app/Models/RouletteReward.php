<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouletteReward extends Model
{
    protected $fillable = [
        'user_id',
        'prize_type',
        'label',
        'discount_percent',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'discount_percent' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
