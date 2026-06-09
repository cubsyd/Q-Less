<?php

namespace App\Http\Controllers;

use App\Models\FavoriteProduct;
use App\Models\User;
use Illuminate\Http\Request;

class FavoriteProductController extends Controller
{
    public function index(int $userId)
    {
        $user = User::findOrFail($userId);

        $products = $user->favoriteProducts()
            ->with(['categoriaRelacion', 'usuario'])
            ->latest('favorite_products.created_at')
            ->get();

        return response()->json([
            'status' => true,
            'products' => $products,
            'product_ids' => $products->pluck('id')->values(),
        ]);
    }

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'producto_id' => 'required|integer|exists:productos,id',
        ]);

        $favorite = FavoriteProduct::where('user_id', $data['user_id'])
            ->where('producto_id', $data['producto_id'])
            ->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json([
                'status' => true,
                'is_favorite' => false,
                'message' => 'Producto eliminado de favoritos.',
            ]);
        }

        FavoriteProduct::create([
            'user_id' => $data['user_id'],
            'producto_id' => $data['producto_id'],
        ]);

        return response()->json([
            'status' => true,
            'is_favorite' => true,
            'message' => 'Producto agregado a favoritos.',
        ], 201);
    }
}
