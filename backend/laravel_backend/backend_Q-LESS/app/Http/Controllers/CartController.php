<?php

namespace App\Http\Controllers;

use App\Services\CartReservationService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartReservationService $cartReservationService)
    {
    }

    public function index(int $userId)
    {
        return response()->json(
            $this->cartReservationService->getCartByUserId($userId)
        );
    }

    public function reserve(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'producto_id' => 'required|integer|exists:productos,id',
        ]);

        return response()->json(
            $this->cartReservationService->reserveProduct((int) $data['user_id'], (int) $data['producto_id'])
        );
    }

    public function remove(int $userId, int $productId)
    {
        return response()->json(
            $this->cartReservationService->removeProduct($userId, $productId)
        );
    }

    public function decrease(int $userId, int $productId)
    {
        return response()->json(
            $this->cartReservationService->decreaseProduct($userId, $productId)
        );
    }

    public function clear(int $userId)
    {
        return response()->json(
            $this->cartReservationService->clearCart($userId)
        );
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'nombre' => 'required|string|max:255',
            'documento' => 'required|string|max:255',
            'metodo' => 'required|string|max:50',
            'referencia' => 'required|string|max:255',
        ]);

        return response()->json(
            $this->cartReservationService->checkout((int) $data['user_id'])
        );
    }
}
