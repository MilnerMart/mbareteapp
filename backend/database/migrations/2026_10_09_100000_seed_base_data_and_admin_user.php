<?php

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Models\Gym;
use App\Models\Permit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Datos minimos para levantar el proyecto en una base vacia: core models, roles, permisos,
     * el gimnasio base (sin el no funciona el registro) y el unico usuario administrador.
     * Es idempotente: si los datos ya existen (ej. base local) no toca nada.
     * El admin se crea solo si ADMIN_EMAIL y ADMIN_PASSWORD estan definidos en el .env.
     */
    public function up(): void
    {
        $now = now();

        DB::table('core_models')->insertOrIgnore([
            ['id' => CoreModel::exerciseModelId, 'slug' => 'exercises', 'name' => 'Ejercicios', 'created_at' => $now, 'updated_at' => $now],
            ['id' => CoreModel::resourceModelId, 'slug' => 'resources', 'name' => 'Recursos', 'created_at' => $now, 'updated_at' => $now],
            ['id' => CoreModel::muscleModelId, 'slug' => 'muscle', 'name' => 'Musculos', 'created_at' => $now, 'updated_at' => $now],
            ['id' => CoreModel::gymEntityModelId, 'slug' => 'gym', 'name' => 'Gimnasio', 'created_at' => $now, 'updated_at' => $now],
            ['id' => CoreModel::roleModelId, 'slug' => 'role', 'name' => 'Roles de usuario', 'created_at' => $now, 'updated_at' => $now],
            ['id' => CoreModel::routineModelId, 'slug' => 'routine', 'name' => 'Rutinas', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $adminRoleId = $this->ensureRole('Rol de administrador', 'admin-role', [Permit::seeAllPermitSlug]);
        $this->ensureRole('Alumno', 'trainee-role', null);
        $trainerRoleId = $this->ensureRole('Entrenador', 'trainer-role', [Permit::createGymEntityPermitSlug, Permit::assignRoutinesPermitSlug]);

        $this->ensurePermit('God tier permit', Permit::seeAllPermitSlug, $adminRoleId);
        $this->ensurePermit('Crear gimnasios', Permit::createGymEntityPermitSlug, $trainerRoleId);
        $this->ensurePermit('Asignar rutinas', Permit::assignRoutinesPermitSlug, $trainerRoleId);

        $adminId = $this->ensureAdminUser($adminRoleId);

        $gymId = DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->value('id');
        if (!$gymId) {
            $gymId = DB::table('gym_entities')->insertGetId([
                'name' => env('BASE_GYM_NAME', 'Leoncio Gym'),
                'slug' => Gym::baseGymSlug,
                'owner_id' => $adminId,
                'status' => EntityStatus::statusIdActive,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($adminId) {
            DB::table('gym_users')->insertOrIgnore([
                'gym_id' => $gymId,
                'user_id' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     * No se revierte: borrar roles o el admin rompería los datos que dependen de ellos.
     */
    public function down(): void
    {
    }

    private function ensureRole(string $name, string $slug, ?array $rolePermits): int
    {
        $roleId = DB::table('roles')->where('slug', $slug)->value('id');
        if ($roleId) {
            return $roleId;
        }

        return DB::table('roles')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'status' => EntityStatus::statusIdActive,
            'data' => $rolePermits ? json_encode(['rolePermits' => $rolePermits]) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensurePermit(string $name, string $slug, int $roleId): void
    {
        if (DB::table('permits')->where('slug', $slug)->exists()) {
            return;
        }

        DB::table('permits')->insert([
            'name' => $name,
            'slug' => $slug,
            'role_id' => $roleId,
            'status' => EntityStatus::statusIdActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureAdminUser(int $adminRoleId): ?int
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if (!$email || !$password) {
            return null;
        }

        $userId = DB::table('users')->where('email', $email)->value('id');
        if (!$userId) {
            $userId = DB::table('users')->insertGetId([
                'name' => env('ADMIN_NAME', 'Admin'),
                'last_name' => env('ADMIN_LAST_NAME', 'MuscleApp'),
                'email' => $email,
                'password' => Hash::make($password),
                'age' => 0,
                'height' => 0,
                'weight' => 0,
                'status' => EntityStatus::statusIdActive,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $hasRole = DB::table('user_roles')->where('user_id', $userId)->where('role_id', $adminRoleId)->exists();
        if (!$hasRole) {
            DB::table('user_roles')->insert(['user_id' => $userId, 'role_id' => $adminRoleId]);
        }

        return $userId;
    }
};
