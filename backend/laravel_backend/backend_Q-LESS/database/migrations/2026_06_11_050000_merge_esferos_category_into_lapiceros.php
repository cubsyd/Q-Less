<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $target = DB::table('categorias')
            ->where('nombre', 'Lapiceros y esferos')
            ->first();

        if (!$target) {
            DB::table('categorias')->insert([
                'nombre' => 'Lapiceros y esferos',
                'descripcion' => 'Categoria para lapiceros, esferos y boligrafos escolares.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $target = DB::table('categorias')
                ->where('nombre', 'Lapiceros y esferos')
                ->first();
        }

        $esferos = DB::table('categorias')
            ->where('nombre', 'Esferos')
            ->first();

        if (!$esferos || !$target) {
            return;
        }

        DB::table('productos')
            ->where('categoria_id', $esferos->id)
            ->update([
                'categoria_id' => $target->id,
                'categoria' => 'Lapiceros y esferos',
            ]);

        DB::table('productos')
            ->where('categoria', 'Esferos')
            ->update([
                'categoria_id' => $target->id,
                'categoria' => 'Lapiceros y esferos',
            ]);

        DB::table('categorias')
            ->where('id', $esferos->id)
            ->delete();
    }

    public function down(): void
    {
        DB::table('categorias')->updateOrInsert(
            ['nombre' => 'Esferos'],
            [
                'descripcion' => 'Categoria para esferos.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
