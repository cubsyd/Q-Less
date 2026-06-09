<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'stock',
        'image_path',
        'product_images',
        'categoria',
        'categoria_id',
        'user_id',
    ];

    protected $casts = [
        'product_images' => 'array',
    ];

    public function setStockAttribute($value): void
    {
        $this->attributes['stock'] = max(0, (int) $value);
    }

    public function categoriaRelacion()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function favoredByUsers()
    {
        return $this->belongsToMany(User::class, 'favorite_products', 'producto_id', 'user_id')
            ->withTimestamps();
    }
}
