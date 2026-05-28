<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('user')
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'orders' => $orders
        ]);
    }

    public function userOrders($userId)
    {
        $orders = Order::with('user')
            ->where('user_id', $userId)
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'orders' => $orders
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:pendiente,entregado,no_entregado,expirado'
        ]);

        $order = Order::findOrFail($id);
        $newStatus = $request->status;

        $result = DB::transaction(function () use ($order, $newStatus) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);
            $realPreviousStatus = $lockedOrder->status;

            if ($this->isLateDeliveryAttempt($lockedOrder, $realPreviousStatus, $newStatus)) {
                if ($realPreviousStatus === 'pendiente') {
                    $this->returnOrderStock($lockedOrder);
                    $lockedOrder->status = 'expirado';
                    $lockedOrder->save();
                }

                return [
                    'status' => false,
                    'code' => 422,
                    'message' => 'El pedido ya expiro. No se puede marcar como entregado.',
                ];
            }

            if ($this->shouldReturnStock($realPreviousStatus, $newStatus)) {
                $this->returnOrderStock($lockedOrder);
            }

            if ($this->shouldDiscountStock($realPreviousStatus, $newStatus)) {
                $this->discountOrderStock($lockedOrder);
            }

            $lockedOrder->status = $newStatus;
            $lockedOrder->save();

            return [
                'status' => true,
                'code' => 200,
                'message' => $this->statusMessage($realPreviousStatus, $newStatus),
            ];
        });

        $order = Order::with('user')->findOrFail($id);

        if (!$result['status']) {
            return response()->json([
                'status' => false,
                'message' => $result['message'],
                'order' => $order,
            ], $result['code']);
        }

        return response()->json([
            'status' => true,
            'message' => $result['message'],
            'order' => $order
        ]);
    }

    private function isLateDeliveryAttempt(Order $order, string $previousStatus, string $newStatus): bool
    {
        if ($newStatus !== 'entregado' || $previousStatus === 'entregado') {
            return false;
        }

        return $order->expires_at && $order->expires_at->isPast();
    }

    private function statusMessage(string $previousStatus, string $newStatus): string
    {
        if ($previousStatus === $newStatus) {
            return 'El pedido ya tenia ese estado.';
        }

        if ($newStatus === 'entregado') {
            return 'Pedido entregado. El stock reservado queda confirmado como vendido.';
        }

        if (in_array($newStatus, ['no_entregado', 'expirado'], true)) {
            return 'Pedido actualizado. El stock reservado fue devuelto al inventario.';
        }

        if ($newStatus === 'pendiente') {
            return 'Pedido reactivado. El stock del pedido queda reservado nuevamente.';
        }

        return 'Estado actualizado.';
    }

    private function shouldReturnStock(string $previousStatus, string $newStatus): bool
    {
        return in_array($previousStatus, ['pendiente', 'entregado'], true)
            && in_array($newStatus, ['no_entregado', 'expirado'], true);
    }

    private function shouldDiscountStock(string $previousStatus, string $newStatus): bool
    {
        return in_array($previousStatus, ['no_entregado', 'expirado'], true)
            && in_array($newStatus, ['pendiente', 'entregado'], true);
    }

    private function returnOrderStock(Order $order): void
    {
        foreach ($this->productQuantities($order) as $item) {
            $product = $this->findOrderProduct($item);

            if ($product) {
                $product->increment('stock', $item['cantidad']);
            }
        }
    }

    private function discountOrderStock(Order $order): void
    {
        foreach ($this->productQuantities($order) as $item) {
            $product = $this->findOrderProduct($item);

            if (!$product) {
                continue;
            }

            $product->stock = max(0, (int) $product->stock - $item['cantidad']);
            $product->save();
        }
    }

    private function productQuantities(Order $order): array
    {
        $items = is_array($order->items) ? $order->items : [];
        $quantities = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['producto_id'])) {
                $key = 'id:' . (int) $item['producto_id'];

                if (!isset($quantities[$key])) {
                    $quantities[$key] = [
                        'producto_id' => (int) $item['producto_id'],
                        'nombre' => $item['nombre'] ?? null,
                        'cantidad' => 0,
                    ];
                }

                $quantities[$key]['cantidad'] += max(1, (int) ($item['cantidad'] ?? 1));
                continue;
            }

            $productName = is_string($item)
                ? $item
                : ($item['nombre'] ?? null);

            if (!$productName) {
                continue;
            }

            $key = 'name:' . $productName;

            if (!isset($quantities[$key])) {
                $quantities[$key] = [
                    'producto_id' => null,
                    'nombre' => $productName,
                    'cantidad' => 0,
                ];
            }

            $quantities[$key]['cantidad'] += max(1, (int) ($item['cantidad'] ?? 1));
        }

        return array_values($quantities);
    }

    private function findOrderProduct(array $item): ?Producto
    {
        if (!empty($item['producto_id'])) {
            return Producto::lockForUpdate()->find((int) $item['producto_id']);
        }

        if (!empty($item['nombre'])) {
            return Producto::where('nombre', $item['nombre'])
                ->lockForUpdate()
                ->first();
        }

        return null;
    }

    public function destroy($id)
    {
        $order = Order::findOrFail($id);

        $order->delete();

        return response()->json([
            'status' => true,
            'message' => 'Pedido eliminado.'
        ]);
    }
}
