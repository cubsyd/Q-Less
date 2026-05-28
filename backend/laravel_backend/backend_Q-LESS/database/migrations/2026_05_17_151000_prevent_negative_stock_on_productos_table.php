<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $constraintName = 'productos_stock_non_negative';

    public function up(): void
    {
        if (!Schema::hasTable('productos') || !Schema::hasColumn('productos', 'stock')) {
            return;
        }

        DB::table('productos')
            ->where('stock', '<', 0)
            ->update(['stock' => 0]);

        try {
            DB::statement(
                "ALTER TABLE productos ADD CONSTRAINT {$this->constraintName} CHECK (stock >= 0)"
            );
        } catch (\Throwable $exception) {
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('productos')) {
            return;
        }

        try {
            DB::statement("ALTER TABLE productos DROP CHECK {$this->constraintName}");
        } catch (\Throwable $exception) {
            try {
                DB::statement("ALTER TABLE productos DROP CONSTRAINT {$this->constraintName}");
            } catch (\Throwable $ignored) {
                //
            }
        }
    }
};
