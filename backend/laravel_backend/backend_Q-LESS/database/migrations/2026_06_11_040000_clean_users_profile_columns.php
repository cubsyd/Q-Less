<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (!Schema::hasColumn('users', 'bio')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('bio')->nullable()->after('rol');
            });
        }

        if (Schema::hasColumn('users', 'role')) {
            if (Schema::hasColumn('users', 'rol')) {
                DB::table('users')
                    ->where(function ($query) {
                        $query->whereNull('rol')->orWhere('rol', '');
                    })
                    ->update(['rol' => DB::raw('role')]);
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }

        foreach (['telefono', 'avatar_path'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'telefono')) {
                $table->string('telefono', 30)->nullable()->after('email');
            }

            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role', 30)->nullable()->after('rol');
            }

            if (!Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path')->nullable()->after('role');
            }
        });
    }
};
