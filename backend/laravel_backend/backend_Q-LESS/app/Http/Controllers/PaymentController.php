<?php

namespace App\Http\Controllers;

use App\Models\CartReservation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function createPreference(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $accessToken = trim((string) config('services.mercadopago.access_token'));

        if ($accessToken === '') {

            return response()->json([
                'status' => false,
                'message' => 'Falta configurar MERCADOPAGO_ACCESS_TOKEN en el archivo .env.',
            ], 500);
        }

        $user = User::findOrFail($data['user_id']);

        $cartItems = CartReservation::with('producto')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->get();

        if ($cartItems->isEmpty()) {

            return response()->json([
                'status' => false,
                'message' => 'El carrito esta vacio o las reservas expiraron.',
            ], 422);
        }

        $items = $cartItems->map(function (CartReservation $reservation) {

            $product = $reservation->producto;

            return [
                'id' => (string) $product->id,
                'title' => (string) $product->nombre,
                'description' => (string) ($product->descripcion ?? 'Producto Q-LESS'),
                'picture_url' => $product->image_path,
                'quantity' => (int) $reservation->cantidad,
                'currency_id' => config('services.mercadopago.currency', 'COP'),
                'unit_price' => (float) $product->precio,
            ];
        })->values()->all();

        $externalReference =
            'QLESS-' .
            $user->id .
            '-' .
            now()->format('YmdHis') .
            '-' .
            Str::upper(Str::random(6));

        $payload = [

            'items' => $items,

            'payer' => [
                'name' => $user->name,
                'email' => $user->email,
            ],

            'external_reference' => $externalReference,

            'back_urls' => [
                'success' => $this->frontendUrl('/carrito?payment=success'),
                'failure' => $this->frontendUrl('/carrito?payment=failure'),
                'pending' => $this->frontendUrl('/carrito?payment=pending')
            ],

            'statement_descriptor' => 'Q-LESS',
        ];

        $notificationUrl =
            trim((string) config('services.mercadopago.notification_url'));

        if ($notificationUrl !== '') {
            $payload['notification_url'] = $notificationUrl;
        }

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post(
                'https://api.mercadopago.com/checkout/preferences',
                $payload
            );

        if ($response->failed()) {

            return response()->json([
                'status' => false,
                'message' => 'No se pudo crear la preferencia de Mercado Pago.',
                'mercadopago_error' => $response->json(),
            ], $response->status() >= 400 && $response->status() < 500 ? 422 : 500);
        }

        $preference = $response->json();

        return response()->json([

            'status' => true,

            'message' => 'Preferencia creada correctamente.',

            'external_reference' => $externalReference,

            'preference_id' => $preference['id'] ?? null,

            'init_point' => $preference['init_point'] ?? null,

            'sandbox_init_point' => $preference['sandbox_init_point'] ?? null,

            'preference' => $preference,

        ], 201);
    }

    public function createOrder(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'payment_provider' => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:255',
            'payment_status' => 'nullable|string|max:50',
        ]);

        $user = User::findOrFail($data['user_id']);

        $cartItems = CartReservation::with('producto')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->get();

        if ($cartItems->isEmpty()) {

            return response()->json([
                'status' => false,
                'message' => 'Carrito vacio.'
            ], 422);
        }

        $total = $cartItems->sum(function ($item) {

            return $item->cantidad * $item->producto->precio;
        });

        $orderItems = $cartItems->map(function (CartReservation $item) {
            $quantity = (int) $item->cantidad;
            $unitPrice = (float) $item->producto->precio;

            return [
                'producto_id' => (int) $item->producto->id,
                'nombre' => (string) $item->producto->nombre,
                'precio_unitario' => $unitPrice,
                'cantidad' => $quantity,
                'subtotal' => $unitPrice * $quantity,
            ];
        })->values()->all();

        $orderNumber = rand(1000, 9999);

        $order = Order::create([

            'user_id' => $user->id,

            'order_number' => $orderNumber,

            'total' => $total,

            'items' => $orderItems,

            'status' => 'pendiente',

            'payment_provider' => $data['payment_provider'] ?? 'mercadopago_simulado',

            'payment_reference' => $data['payment_reference'] ?? null,

            'payment_status' => $data['payment_status'] ?? 'simulado',

            'expires_at' => now()->addMinutes(10),
        ]);

        CartReservation::where('user_id', $user->id)
            ->delete();

        return response()->json([

            'status' => true,

            'message' => 'Pedido creado correctamente.',

            'order' => $order
        ]);
    }

    public function webhook(Request $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'Notificacion recibida.',
            'payload' => $request->all(),
        ]);
    }

    private function frontendUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('services.mercadopago.frontend_url'), '/');

        return $baseUrl . $path;
    }
}
