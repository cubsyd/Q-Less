<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (Schema::hasTable('productos') && Schema::hasColumn('productos', 'image_path')) {
            $this->changeColumnToLongText($driver, 'productos', 'image_path');
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'profile_photo_path')) {
            $this->changeColumnToLongText($driver, 'users', 'profile_photo_path');
        }
    }

    public function down(): void
    {
        // Keep as text to avoid truncating existing base64 images.
    }

    private function changeColumnToLongText(string $driver, string $table, string $column): void
    {
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE {$table} MODIFY {$column} LONGTEXT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE TEXT");
        }
    }
};
