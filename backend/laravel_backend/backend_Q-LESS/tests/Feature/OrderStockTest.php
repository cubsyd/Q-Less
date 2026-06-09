<?php

namespace Tests\Feature;

use App\Models\CartReservation;
use App\Models\Order;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_orders_with_structured_items_and_payment_metadata(): void
    {
        $user = User::factory()->create([
            'telefono' => '3001234567',
            'rol' => 'usuario',
        ]);

        $product = Producto::create([
            'nombre' => 'Cuaderno argollado',
            'descripcion' => 'Cuaderno grande',
            'precio' => 12000,
            'stock' => 0,
            'categoria' => 'Cuadernos y libretas',
        ]);

        CartReservation::create([
            'user_id' => $user->id,
            'producto_id' => $product->id,
            'cantidad' => 2,
            'status' => 'active',
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/payments/create-order', [
            'user_id' => $user->id,
            'payment_provider' => 'mercadopago_simulado',
            'payment_reference' => 'QLESS-TEST',
            'payment_status' => 'simulado',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('order.total', 24000)
            ->assertJsonPath('order.payment_reference', 'QLESS-TEST')
            ->assertJsonPath('order.items.0.producto_id', $product->id)
            ->assertJsonPath('order.items.0.cantidad', 2)
            ->assertJsonPath('order.items.0.subtotal', 24000);

        $this->assertDatabaseMissing('cart_reservations', [
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_order_status_transitions_adjust_stock_by_product_id(): void
    {
        $user = User::factory()->create([
            'telefono' => '3001234567',
            'rol' => 'usuario',
        ]);

        $product = Producto::create([
            'nombre' => 'Lapiz HB',
            'descripcion' => 'Lapiz escolar',
            'precio' => 1500,
            'stock' => 0,
            'categoria' => 'Lapices y marcadores',
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => '1234',
            'total' => 3000,
            'items' => [[
                'producto_id' => $product->id,
                'nombre' => 'Nombre historico',
                'precio_unitario' => 1500,
                'cantidad' => 2,
                'subtotal' => 3000,
            ]],
            'status' => 'pendiente',
            'payment_provider' => 'mercadopago_simulado',
            'payment_status' => 'simulado',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'no_entregado',
        ])->assertOk();

        $this->assertSame(2, $product->fresh()->stock);

        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'pendiente',
        ])->assertOk();

        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_delivered_pending_order_confirms_reserved_stock_without_double_discount(): void
    {
        $user = User::factory()->create([
            'telefono' => '3001234567',
            'rol' => 'usuario',
        ]);

        $product = Producto::create([
            'nombre' => 'Cartuchera',
            'descripcion' => 'Cartuchera escolar',
            'precio' => 8000,
            'stock' => 3,
            'categoria' => 'Herramientas escolares',
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => '5678',
            'total' => 16000,
            'items' => [[
                'producto_id' => $product->id,
                'nombre' => 'Cartuchera',
                'precio_unitario' => 8000,
                'cantidad' => 2,
                'subtotal' => 16000,
            ]],
            'status' => 'pendiente',
            'payment_provider' => 'mercadopago_simulado',
            'payment_status' => 'simulado',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'entregado',
        ])->assertOk()
            ->assertJsonPath('order.status', 'entregado');

        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_expired_pending_order_cannot_be_delivered_and_returns_stock(): void
    {
        $user = User::factory()->create([
            'telefono' => '3001234567',
            'rol' => 'usuario',
        ]);

        $product = Producto::create([
            'nombre' => 'Marcador',
            'descripcion' => 'Marcador borrable',
            'precio' => 2500,
            'stock' => 0,
            'categoria' => 'Lapices y marcadores',
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => '9012',
            'total' => 5000,
            'items' => [[
                'producto_id' => $product->id,
                'nombre' => 'Marcador',
                'precio_unitario' => 2500,
                'cantidad' => 2,
                'subtotal' => 5000,
            ]],
            'status' => 'pendiente',
            'payment_provider' => 'mercadopago_simulado',
            'payment_status' => 'simulado',
            'expires_at' => now()->subMinute(),
        ]);

        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'entregado',
        ])->assertStatus(422)
            ->assertJsonPath('order.status', 'expirado');

        $this->assertSame(2, $product->fresh()->stock);
    }
}
