<?php

use App\Models\Gym;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * El dueño del gimnasio base es el unico usuario que no es alumno de el: lo gestiona y lo ve siempre.
     * La migracion que sumo a todos al gimnasio base lo habia dejado como alumno; aca se lo saca.
     */
    public function up(): void
    {
        $baseGym = DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->first(['id', 'owner_id']);
        if (!$baseGym?->owner_id) {
            return;
        }

        DB::table('gym_users')->where('gym_id', $baseGym->id)->where('user_id', $baseGym->owner_id)->delete();
    }

    /**
     * Reverse the migrations.
     * No se deshace: el dueño no vuelve a ser alumno de su propio gimnasio.
     */
    public function down(): void
    {
    }
};
