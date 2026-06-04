<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'payment_provider')) {
                $table->string('payment_provider', 50)
                    ->nullable()
                    ->after('status');
            }

            if (!Schema::hasColumn('orders', 'payment_reference')) {
                $table->string('payment_reference')
                    ->nullable()
                    ->after('payment_provider');
            }

            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status', 50)
                    ->nullable()
                    ->after('payment_reference');
            }
        });

        DB::table('orders')
            ->whereNull('payment_provider')
            ->update([
                'payment_provider' => 'mercadopago_simulado',
                'payment_status' => 'simulado',
            ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payment_status')) {
                $table->dropColumn('payment_status');
            }

            if (Schema::hasColumn('orders', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }

            if (Schema::hasColumn('orders', 'payment_provider')) {
                $table->dropColumn('payment_provider');
            }
        });
    }
};
