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

                if (!is_array($items)) {
                    return;
                }

                $productNames = collect($items)
                    ->map(function ($item) {
                        if (is_string($item)) {
                            return $item;
                        }

                        if (is_array($item) && isset($item['nombre'])) {
                            return $item['nombre'];
                        }

                        return null;
                    })
                    ->filter()
                    ->values()
                    ->all();

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'items' => json_encode($productNames),
                    ]);
            });
    }

    public function down(): void
    {
        //
    }
};
