<?php

use App\Core\EntityStatus;
use App\Models\Gym;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Todo usuario pertenece al gimnasio base (asi entra al registrarse): suma a los que quedaron afuera, por ejemplo
     * cuentas anteriores a esa regla, para que alumnos y entrenadores lo vean y puedan competir en su ranking.
     * Entran sin mostrarse en la lista de alumnos, como al registrarse. Si el gimnasio base no existe no hace nada.
     */
    public function up(): void
    {
        $baseGymId = DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->value('id');
        if (!$baseGymId) {
            return;
        }

        $now = now();
        DB::table('users')
            ->where('status', '!=', EntityStatus::statusIdDeleted)
            ->whereNotExists(fn(Builder $query) => $query->select(DB::raw(1))->from('gym_users')
                ->whereColumn('gym_users.user_id', 'users.id')->where('gym_users.gym_id', $baseGymId))
            ->orderBy('id')
            ->pluck('id')
            ->chunk(500)
            ->each(fn($userIdList) => DB::table('gym_users')->insertOrIgnore($userIdList->map(fn($userId) => [
                'gym_id' => $baseGymId,
                'user_id' => $userId,
                'is_public' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()));
    }

    /**
     * Reverse the migrations.
     * No se deshace: sacar a alguien del gimnasio base lo dejaria sin el gimnasio que tiene todo usuario.
     */
    public function down(): void
    {
    }
};
