<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (!Schema::hasColumn('productos', 'categoria')) {
                $table->string('categoria')->nullable()->after('nombre');
            }

            if (!Schema::hasColumn('productos', 'image_path')) {
                $table->string('image_path')->nullable()->after('stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'categoria')) {
                $table->dropColumn('categoria');
            }

            if (Schema::hasColumn('productos', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });
    }
};
