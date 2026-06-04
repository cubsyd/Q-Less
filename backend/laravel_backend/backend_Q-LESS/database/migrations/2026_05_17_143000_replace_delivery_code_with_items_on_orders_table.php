<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_code')) {
                $table->dropColumn('delivery_code');
            }

            if (!Schema::hasColumn('orders', 'items')) {
                $table->json('items')
                    ->nullable()
                    ->after('total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'items')) {
                $table->dropColumn('items');
            }

            if (!Schema::hasColumn('orders', 'delivery_code')) {
                $table->string('delivery_code')
                    ->nullable()
                    ->after('order_number');
            }
        });
    }
};
