<?php

use App\Core\EntityStatus;
use App\Models\Permit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const roleSlugs = ['trainee-role', 'trainer-role'];

    /**
     * Run the migrations.
     * Alumnos y entrenadores pertenecen a gimnasios (todos estan en el base) y ven "Mi gimnasio".
     * Es idempotente: no duplica el permiso ni lo agrega dos veces a un rol.
     */
    public function up(): void
    {
        $traineeRoleId = DB::table('roles')->where('slug', 'trainee-role')->value('id');
        if (!DB::table('permits')->where('slug', Permit::belongsToGymPermitSlug)->exists()) {
            DB::table('permits')->insert([
                'name' => 'Pertenecer a gimnasios',
                'slug' => Permit::belongsToGymPermitSlug,
                'role_id' => $traineeRoleId,
                'status' => EntityStatus::statusIdActive,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('roles')->whereIn('slug', self::roleSlugs)->get(['id', 'data']) as $role) {
            $data = json_decode($role->data ?? '', true) ?: [];
            $rolePermits = $data['rolePermits'] ?? [];
            if (in_array(Permit::belongsToGymPermitSlug, $rolePermits, true)) {
                continue;
            }
            $data['rolePermits'] = [...$rolePermits, Permit::belongsToGymPermitSlug];
            DB::table('roles')->where('id', $role->id)->update(['data' => json_encode($data), 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (DB::table('roles')->whereIn('slug', self::roleSlugs)->get(['id', 'data']) as $role) {
            $data = json_decode($role->data ?? '', true) ?: [];
            $data['rolePermits'] = array_values(array_diff($data['rolePermits'] ?? [], [Permit::belongsToGymPermitSlug]));
            DB::table('roles')->where('id', $role->id)->update(['data' => $data['rolePermits'] ? json_encode($data) : null]);
        }
        DB::table('permits')->where('slug', Permit::belongsToGymPermitSlug)->delete();
    }
};
