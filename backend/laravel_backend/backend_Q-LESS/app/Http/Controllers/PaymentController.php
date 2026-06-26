<?php

namespace App\Http\Controllers;

use App\Models\CartReservation;
use App\Models\Order;
use App\Models\User;
use App\Mail\DeliveryCodeMail;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use App\Services\RouletteRewardService;

class PaymentController extends Controller
{
    public function __construct(private RouletteRewardService $rouletteRewardService)
    {
    }

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

        $reward = $this->rouletteRewardService->activeForUser($user->id);

        $items = $cartItems->map(function (CartReservation $reservation) use ($reward) {

            $product = $reservation->producto;
            $quantity = (int) $reservation->cantidad;
            $pricing = $this->rouletteRewardService->applyToLine((float) $product->precio, $quantity, $reward);
            $title = (string) $product->nombre;

            if ($pricing['discount_label']) {
                $title .= ' - ' . $pricing['discount_label'];
            }

            $item = [
                'id' => (string) $product->id,
                'title' => $title,
                'description' => (string) ($product->descripcion ?? 'Producto Q-LESS'),
                'quantity' => 1,
                'currency_id' => config('services.mercadopago.currency', 'COP'),
                'unit_price' => (float) $pricing['subtotal'],
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

        try {
            $orderResult = $this->createOrderFromCart(
                $user,
                $externalReference,
                $isSimulatedPayment ? 'mercadopago_simulado' : 'mercadopago',
                $isSimulatedPayment ? 'simulado' : 'pending'
            );
        } catch (RuntimeException $exception) {
            return $this->emailRequiredError($exception);
        }

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

            'email_error' => $orderResult['email_error'],

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

        try {
            $orderResult = $this->createOrderFromCart(
                $user,
                $paymentReference,
                $data['payment_provider'] ?? 'mercadopago_simulado',
                $data['payment_status'] ?? 'simulado'
            );
        } catch (RuntimeException $exception) {
            return $this->emailRequiredError($exception);
        }

        return response()->json([

            'status' => true,

            'message' => 'Pedido creado correctamente con el numero de pedido #' . $orderResult['order']->order_number . '.',

            'order' => $orderResult['order'],

            'email_sent' => $orderResult['email_sent'],

            'email_error' => $orderResult['email_error'],

            'already_created' => $orderResult['already_created'],
        ]);
    }

    public function showOrderByReference(Request $request, string $reference)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $order = Order::where('user_id', $data['user_id'])
            ->where('payment_reference', $reference)
            ->latest()
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'No se encontro un pedido para esta referencia de pago.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'order' => $order,
            'order_number' => $order->order_number,
            'payment_reference' => $order->payment_reference,
            'payment_status' => $order->payment_status,
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

                $freshOrder = $existingOrder->fresh();
                $emailResult = $this->sendOrderEmail($user, $freshOrder);

                return [
                    'order' => $freshOrder,
                    'email_sent' => $emailResult['sent'],
                    'email_error' => $emailResult['error'],
                    'already_created' => true,
                ];
            }
        }

        return DB::transaction(function () use ($user, $paymentReference, $paymentProvider, $paymentStatus) {
            $cartItems = CartReservation::with('producto')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
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

            $reward = $this->rouletteRewardService->activeForUser($user->id);

            $total = $cartItems->sum(function ($item) use ($reward) {
                $pricing = $this->rouletteRewardService->applyToLine(
                    (float) $item->producto->precio,
                    (int) $item->cantidad,
                    $reward
                );

                return $pricing['subtotal'];
            });

            $orderItems = $cartItems->map(function (CartReservation $item) use ($reward) {
                $quantity = (int) $item->cantidad;
                $unitPrice = (float) $item->producto->precio;
                $pricing = $this->rouletteRewardService->applyToLine($unitPrice, $quantity, $reward);

                return [
                    'producto_id' => (int) $item->producto->id,
                    'nombre' => (string) $item->producto->nombre,
                    'precio_unitario' => (float) $pricing['unit_price'],
                    'precio_original' => (float) $pricing['original_unit_price'],
                    'cantidad' => $quantity,
                    'subtotal' => (float) $pricing['subtotal'],
                    'descuento' => (float) $pricing['discount_amount'],
                    'discount_label' => $pricing['discount_label'],
                    'discount_percent' => $pricing['discount_percent'],
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

            $emailResult = $this->sendOrderEmail($user, $order);

            CartReservation::where('user_id', $user->id)
                ->delete();

            $this->rouletteRewardService->consumeForUser($user->id);

            return [
                'order' => $order,
                'email_sent' => $emailResult['sent'],
                'email_error' => $emailResult['error'],
                'already_created' => false,
            ];
        });
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
            'email_error' => $orderResult['email_error'],
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

    private function emailRequiredError(RuntimeException $exception)
    {
        return response()->json([
            'status' => false,
            'message' => $exception->getMessage(),
            'email_required' => true,
        ], 500);
    }

    private function ensureDeliverableMailConfigured(): void
    {
        $mailer = (string) config('mail.default');
        $fromAddress = (string) config('mail.from.address');

        if (in_array($mailer, ['log', 'array'], true)) {
            throw new RuntimeException('El correo no esta configurado para entrega real. Usa MAIL_MAILER=smtp, resend, postmark, ses o mailgun.');
        }

        if ($fromAddress === '' || $fromAddress === 'hello@example.com') {
            throw new RuntimeException('MAIL_FROM_ADDRESS debe ser un correo real y verificado por el proveedor SMTP.');
        }
    }

    private function sendOrderEmail(User $user, Order $order): array
    {
        $brevoApiKey = trim((string) config('services.brevo.api_key'));

        if ($brevoApiKey !== '') {
            return $this->sendOrderEmailWithBrevo($user, $order, $brevoApiKey);
        }

        try {
            $this->ensureDeliverableMailConfigured();
        } catch (RuntimeException $exception) {
            Log::warning('No se intento enviar el correo de pedido por configuracion incompleta.', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }

        try {
            Mail::to($user->email)->send(new DeliveryCodeMail($order));

            return [
                'sent' => true,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar el correo de pedido creado.', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function sendOrderEmailWithBrevo(User $user, Order $order, string $apiKey): array
    {
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');

        if ($fromAddress === '' || $fromAddress === 'hello@example.com') {
            return [
                'sent' => false,
                'error' => 'MAIL_FROM_ADDRESS debe ser un correo real y verificado en Brevo.',
            ];
        }

        try {
            $response = Http::withHeaders([
                'api-key' => $apiKey,
                'accept' => 'application/json',
            ])
                ->asJson()
                ->timeout(15)
                ->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => [
                        'name' => $fromName !== '' ? $fromName : 'Q-LESS',
                        'email' => $fromAddress,
                    ],
                    'to' => [
                        [
                            'email' => $user->email,
                            'name' => $user->name,
                        ],
                    ],
                    'subject' => 'Pedido creado Q-LESS #' . $order->order_number,
                    'htmlContent' => $this->orderEmailHtml($order),
                ]);

            if ($response->successful()) {
                return [
                    'sent' => true,
                    'error' => null,
                ];
            }

            Log::warning('Brevo no acepto el correo de pedido.', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return [
                'sent' => false,
                'error' => 'Brevo rechazo el correo: ' . $response->body(),
            ];
        } catch (\Throwable $exception) {
            Log::warning('No se pudo enviar el correo de pedido por Brevo API.', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return [
                'sent' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function orderEmailHtml(Order $order): string
    {
        $orderNumber = htmlspecialchars((string) $order->order_number, ENT_QUOTES, 'UTF-8');
        $total = number_format((float) $order->total, 0, ',', '.');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Q-LESS</title>
</head>
<body>
    <h2>Q-LESS</h2>
    <p>Tu pedido fue creado correctamente.</p>
    <h1>Pedido #{$orderNumber}</h1>
    <p>Recibimos tu compra y la estamos preparando. Presenta este numero para reclamar tu producto.</p>
    <p>Total: {$total} Pesos</p>
</body>
</html>
HTML;
    }
}
