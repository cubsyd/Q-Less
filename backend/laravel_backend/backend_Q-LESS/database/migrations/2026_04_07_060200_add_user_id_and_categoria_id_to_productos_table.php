<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (!Schema::hasColumn('productos', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('productos', 'categoria_id')) {
                $table->foreignId('categoria_id')->nullable()->after('categoria')->constrained('categorias')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('productos', 'categoria') && Schema::hasTable('categorias')) {
            $categorias = DB::table('categorias')->pluck('id', 'nombre');
            $productos = DB::table('productos')->select('id', 'categoria')->get();

            foreach ($productos as $producto) {
                if ($producto->categoria && isset($categorias[$producto->categoria])) {
                    DB::table('productos')
                        ->where('id', $producto->id)
                        ->update(['categoria_id' => $categorias[$producto->categoria]]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'categoria_id')) {
                $table->dropConstrainedForeignId('categoria_id');
            }

            if (Schema::hasColumn('productos', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
