<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categorias')->updateOrInsert(
            ['nombre' => 'Lapiceros y esferos'],
            [
                'descripcion' => 'Categoria para lapiceros, esferos y boligrafos escolares.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('categorias')
            ->where('nombre', 'Lapiceros y esferos')
            ->delete();
    }
};
