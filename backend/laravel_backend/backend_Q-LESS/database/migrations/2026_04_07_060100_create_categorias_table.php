<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('categorias')->insert([
            [
                'nombre' => 'Cuadernos y libretas',
                'descripcion' => 'Categoria para cuadernos, libretas y productos de escritura escolar.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nombre' => 'Lapices y marcadores',
                'descripcion' => 'Categoria para lapices, colores, marcadores y resaltadores.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nombre' => 'Cartulinas y hojas',
                'descripcion' => 'Categoria para hojas, cartulinas y papeles de apoyo.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nombre' => 'Herramientas escolares',
                'descripcion' => 'Categoria para tijeras, reglas, pegantes y demas herramientas.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
