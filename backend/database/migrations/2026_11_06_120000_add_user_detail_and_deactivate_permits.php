<?php

use App\Core\EntityStatus;
use App\Models\Permit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const permitNameMap = [
        Permit::seeUserDetailPermitSlug => 'Ver la ficha de usuarios',
        Permit::deactivateUserPermitSlug => 'Dar de baja usuarios',
    ];

    /**
     * Run the migrations.
     * Permisos para abrir la ficha de un usuario y para darlo de baja (la baja pide los dos).
     * No se ligan a ningun rol: el admin ya los tiene por el permiso total, y a otro rol se le agregan
     * sumando los slugs a sus rolePermits. Es idempotente: no duplica los permisos.
     */
    public function up(): void
    {
        foreach (self::permitNameMap as $slug => $name) {
            if (DB::table('permits')->where('slug', $slug)->exists()) {
                continue;
            }
            DB::table('permits')->insert([
                'name' => $name,
                'slug' => $slug,
                'role_id' => null,
                'status' => EntityStatus::statusIdActive,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permits')->whereIn('slug', array_keys(self::permitNameMap))->delete();
    }
};
