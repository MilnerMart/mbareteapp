<?php

use App\Core\EntityStatus;
use App\Models\Permit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Permiso para ver la configuracion de la app. No se liga a ningun rol: el admin ya lo tiene
     * por el permiso total, y a otro rol se le agrega sumando el slug a sus rolePermits.
     * Es idempotente: no duplica el permiso.
     */
    public function up(): void
    {
        if (DB::table('permits')->where('slug', Permit::allowConfigPermitSlug)->exists()) {
            return;
        }

        DB::table('permits')->insert([
            'name' => 'Configurar la app',
            'slug' => Permit::allowConfigPermitSlug,
            'role_id' => null,
            'status' => EntityStatus::statusIdActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permits')->where('slug', Permit::allowConfigPermitSlug)->delete();
    }
};
