<?php

namespace App\Http\Controllers;

use App\Models\CartReservation;
use App\Models\Order;
use App\Models\User;
use App\Mail\DeliveryCodeMail;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function createPreference(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $isSimulatedPayment = filter_var(
            config('services.mercadopago.simulated', true),
            FILTER_VALIDATE_BOOLEAN
        );
        $accessToken = trim((string) config('services.mercadopago.access_token'));

        if (!$isSimulatedPayment && $accessToken === '') {

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

        if ($cartItems->contains(fn (CartReservation $item) => !$item->producto)) {
            return response()->json([
                'status' => false,
                'message' => 'El carrito tiene productos que ya no existen.',
            ], 422);
        }

        $items = $cartItems->map(function (CartReservation $reservation) {

            $product = $reservation->producto;
            $item = [
                'id' => (string) $product->id,
                'title' => (string) $product->nombre,
                'description' => (string) ($product->descripcion ?? 'Producto Q-LESS'),
                'quantity' => (int) $reservation->cantidad,
                'currency_id' => config('services.mercadopago.currency', 'COP'),
                'unit_price' => (float) $product->precio,
            ];

            if (is_string($product->image_path) && str_starts_with($product->image_path, 'http')) {
                $item['picture_url'] = $product->image_path;
            }

            return $item;
        })->values()->all();

        $externalReference =
            'QLESS-' .
            $user->id .
            '-' .
            now()->format('YmdHis') .
            '-' .
            Str::upper(Str::random(6));

        $orderResult = $this->createOrderFromCart(
            $user,
            $externalReference,
            $isSimulatedPayment ? 'mercadopago_simulado' : 'mercadopago',
            $isSimulatedPayment ? 'simulado' : 'pending'
        );

        if ($isSimulatedPayment) {
            return response()->json(
                $this->simulatedPreference($externalReference, 'Pago simulado configurado para Q-LESS.', $orderResult),
                201
            );
        }

        $payload = [

            'items' => $items,

            'payer' => [
                'name' => $user->name,
                'email' => $user->email,
            ],

            'external_reference' => $externalReference,

            'back_urls' => [
                'success' => $this->frontendUrl('/carrito?payment=success&external_reference=' . urlencode($externalReference)),
                'failure' => $this->frontendUrl('/carrito?payment=failure&external_reference=' . urlencode($externalReference)),
                'pending' => $this->frontendUrl('/carrito?payment=pending&external_reference=' . urlencode($externalReference))
            ],

            'statement_descriptor' => 'Q-LESS',
        ];

        if (!$this->isLocalFrontendUrl()) {
            $payload['auto_return'] = 'approved';
        }

        $notificationUrl =
            trim((string) config('services.mercadopago.notification_url'));

        if ($notificationUrl !== '') {
            $payload['notification_url'] = $notificationUrl;
        }

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->withOptions([
                    'proxy' => '',
                ])
                ->post(
                    'https://api.mercadopago.com/checkout/preferences',
                    $payload
                );
        } catch (ConnectionException $exception) {
            if (app()->environment('local')) {
                return response()->json(
                    $this->simulatedPreference($externalReference, $exception->getMessage(), $orderResult),
                    201
                );
            }

            throw $exception;
        }

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

            'order' => $orderResult['order'],

            'order_number' => $orderResult['order']->order_number,

            'email_sent' => $orderResult['email_sent'],

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
            'payment_provider' => 'nullable|string|in:mercadopago,mercadopago_simulado',
            'payment_reference' => 'required_if:payment_provider,mercadopago|nullable|string|max:255',
            'payment_status' => 'nullable|string|in:approved,pending,simulado',
        ]);

        $user = User::findOrFail($data['user_id']);
        $paymentReference = $data['payment_reference'] ?? null;

        $orderResult = $this->createOrderFromCart(
            $user,
            $paymentReference,
            $data['payment_provider'] ?? 'mercadopago_simulado',
            $data['payment_status'] ?? 'simulado'
        );

        return response()->json([

            'status' => true,

            'message' => 'Pedido creado correctamente con el numero de pedido #' . $orderResult['order']->order_number . '.',

            'order' => $orderResult['order'],

            'email_sent' => $orderResult['email_sent'],

            'already_created' => $orderResult['already_created'],
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

    private function createOrderFromCart(
        User $user,
        ?string $paymentReference,
        string $paymentProvider,
        string $paymentStatus
    ): array {

        if ($paymentReference) {
            $existingOrder = Order::where('user_id', $user->id)
                ->where('payment_reference', $paymentReference)
                ->latest()
                ->first();

            if ($existingOrder) {
                if ($existingOrder->payment_status !== $paymentStatus) {
                    $existingOrder->update([
                        'payment_status' => $paymentStatus,
                    ]);
                }

                return [
                    'order' => $existingOrder->fresh(),
                    'email_sent' => false,
                    'already_created' => true,
                ];
            }
        }

        $cartItems = CartReservation::with('producto')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->get();

        if ($cartItems->isEmpty()) {
            abort(response()->json([
                'status' => false,
                'message' => 'Carrito vacio.',
            ], 422));
        }

        if ($cartItems->contains(fn (CartReservation $item) => !$item->producto)) {
            abort(response()->json([
                'status' => false,
                'message' => 'El carrito tiene productos que ya no existen.',
            ], 422));
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

        $orderNumber = $this->generateOrderNumber();

        $order = Order::create([

            'user_id' => $user->id,

            'order_number' => $orderNumber,

            'total' => $total,

            'items' => $orderItems,

            'status' => 'pendiente',

            'payment_provider' => $paymentProvider,

            'payment_reference' => $paymentReference,

            'payment_status' => $paymentStatus,

            'expires_at' => now()->addMinutes(10),
        ]);

        CartReservation::where('user_id', $user->id)
            ->delete();

        $emailSent = $this->sendOrderEmail($user, $order);

        return [
            'order' => $order,
            'email_sent' => $emailSent,
            'already_created' => false,
        ];
    }

    private function frontendUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('services.mercadopago.frontend_url'), '/');

        return $baseUrl . $path;
    }

    private function isLocalFrontendUrl(): bool
    {
        $host = parse_url((string) config('services.mercadopago.frontend_url'), PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1'], true);
    }

    private function simulatedPreference(string $externalReference, string $reason, array $orderResult): array
    {
        $url = $this->frontendUrl('/carrito?payment=success&external_reference=' . urlencode($externalReference));

        return [
            'status' => true,
            'message' => 'Preferencia simulada para entorno local.',
            'external_reference' => $externalReference,
            'order' => $orderResult['order'],
            'order_number' => $orderResult['order']->order_number,
            'email_sent' => $orderResult['email_sent'],
            'preference_id' => 'LOCAL-' . $externalReference,
            'init_point' => $url,
            'sandbox_init_point' => $url,
            'is_simulated' => true,
            'simulation_reason' => $reason,
            'preference' => null,
        ];
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = (string) random_int(1000, 9999);
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    private function sendOrderEmail(User $user, Order $order): bool
    {
        try {
            Mail::to($user->email)->send(new DeliveryCodeMail($order));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar el correo de pedido creado.', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
