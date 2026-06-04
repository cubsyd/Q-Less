<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'items')) {
            return;
        }

        DB::table('orders')
            ->whereNotNull('items')
            ->orderBy('id')
            ->get(['id', 'items'])
            ->each(function ($order) {
                $items = json_decode($order->items, true);

                if (!is_array($items) || $items === []) {
                    return;
                }

                $structuredItems = collect($items)
                    ->map(function ($item) {
                        if (is_array($item) && isset($item['producto_id'], $item['cantidad'])) {
                            return $item;
                        }

                        $productName = is_string($item)
                            ? $item
                            : ($item['nombre'] ?? null);

                        if (!$productName) {
                            return null;
                        }

                        $product = DB::table('productos')
                            ->where('nombre', $productName)
                            ->first();

                        $unitPrice = (float) ($product->precio ?? 0);

                        return [
                            'producto_id' => $product?->id,
                            'nombre' => $productName,
                            'precio_unitario' => $unitPrice,
                            'cantidad' => 1,
                            'subtotal' => $unitPrice,
                        ];
                    })
                    ->filter()
                    ->groupBy(fn (array $item) => $item['producto_id']
                        ? 'id:' . $item['producto_id']
                        : 'name:' . $item['nombre'])
                    ->map(function ($group) {
                        $first = $group->first();
                        $quantity = $group->sum(fn (array $item) => max(1, (int) ($item['cantidad'] ?? 1)));
                        $unitPrice = (float) ($first['precio_unitario'] ?? 0);

                        return [
                            'producto_id' => $first['producto_id'] ?? null,
                            'nombre' => $first['nombre'],
                            'precio_unitario' => $unitPrice,
                            'cantidad' => $quantity,
                            'subtotal' => $unitPrice * $quantity,
                        ];
                    })
                    ->values()
                    ->all();

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'items' => json_encode($structuredItems),
                    ]);
            });
    }

    public function down(): void
    {
        //
    }
};
