<?php

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Models\Muscle;
use App\Models\Resource;
use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const cardioSlug = 'cardio';

    private const publicKeyPrefix = Resource::publicKeyPrefix.'free-exercise-db:';

    private const cardioImageKey = 'Running_Treadmill/0.jpg';

    private const cardioExerciseList = [
        ['Correr en cinta', 'Running_Treadmill', 'El clásico que nunca falla para disparar tu resistencia. Controlas velocidad e inclinación al detalle: combina tramos rápidos y suaves por intervalos y convierte cada minuto en quema de energía y fondo cardiovascular.'],
        ['Caminata en cinta', 'Walking_Treadmill', 'Cardio inteligente sin castigar tus articulaciones. Sube la inclinación, mantén el paso firme y el torso erguido sin apoyarte en las barras: glúteos y piernas trabajan a fondo mientras el pulso se mantiene en zona de quema.'],
        ['Saltar la cuerda', 'Rope_Jumping', 'Explosividad, coordinación y cardio en un solo movimiento. Saltos cortos sobre la punta de los pies y la cuerda girando desde las muñecas, no desde los hombros: pocos minutos bastan para encender todo el cuerpo.'],
        ['Bicicleta estática', 'Bicycling_Stationary', 'Pedalea fuerte sin impacto en rodillas ni espalda. Regula la resistencia para alternar sprints intensos con tramos de recuperación: piernas encendidas, pulso arriba y una resistencia que crece sesión tras sesión.'],
        ['Elíptica', 'Elliptical_Trainer', 'Trabajo de cuerpo completo con impacto cero. Empuja y tira de los manillares mientras las piernas marcan el ritmo: brazos, piernas y glúteos se suman al esfuerzo para un cardio intenso y fluido.'],
        ['Remo estacionario', 'Rowing_Stationary', 'Potencia y cardio en cada palada. Empuja primero con las piernas, inclina el torso y termina tirando con los brazos hacia el abdomen: espalda, piernas y core trabajan en cadena con una técnica limpia y explosiva.'],
        ['Escaladora', 'Stairmaster', 'Escalón tras escalón, sin tregua. Mantén el torso erguido y apoya el pie completo en cada paso para que glúteos y piernas carguen con todo el trabajo mientras tu pulso se dispara. Exigente y efectiva.'],
    ];

    /**
     * Run the migrations.
     * Los grupos pasan a tener tipo: grupo muscular (pecho, espalda...) o actividad (cardio, boxeo, atletismo...).
     * Lo que ya existia queda como grupo muscular. Se agrega la actividad Cardio con ejercicios e imagenes de
     * Free Exercise DB (dominio publico). Es idempotente.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('muscles', 'kind')) {
            Schema::table('muscles', function (Blueprint $table) {
                $table->integer('kind')->default(Muscle::kindMuscle)->after('slug');
            });
        }

        $now = now();
        $cardioId = DB::table('muscles')->where('slug', self::cardioSlug)->where('status', EntityStatus::statusIdActive)->value('id');
        if ($cardioId) {
            return;
        }

        $cardioId = DB::table('muscles')->insertGetId([
            'owner_id' => null,
            'is_public' => true,
            'review_state' => Ticket::stateIdApproved,
            'name' => 'Cardio',
            'slug' => self::cardioSlug,
            'kind' => Muscle::kindActivity,
            'recommended_rest_days' => 1,
            'description' => 'Tu motor de resistencia: sube pulsaciones, quema energía y gana fondo con cinta, cuerda, bicicleta, elíptica y más. Ideal para calentar, cerrar la sesión o entrenar por intervalos.',
            'status' => EntityStatus::statusIdActive,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insertResource('Cardio', self::cardioSlug.'-image', CoreModel::muscleModelId, $cardioId, self::cardioImageKey);

        foreach (self::cardioExerciseList as [$name, $itemKey, $description]) {
            $slug = Str::slug($name);
            $exerciseId = DB::table('exercises')->insertGetId([
                'owner_id' => null,
                'is_public' => true,
                'review_state' => Ticket::stateIdApproved,
                'name' => $name,
                'slug' => $slug,
                'muscle_id' => $cardioId,
                'description' => $description,
                'recommended_rest_time' => 60,
                'status' => EntityStatus::statusIdActive,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertResource($name, $slug.'-image', CoreModel::exerciseModelId, $exerciseId, $itemKey.'/0.jpg');
            $this->insertResource($name.' · Posición final', $slug.'-'.Str::lower(Str::random(6)), CoreModel::exerciseModelId, $exerciseId, $itemKey.'/1.jpg');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $cardioId = DB::table('muscles')->where('slug', self::cardioSlug)->where('kind', Muscle::kindActivity)->value('id');
        if ($cardioId) {
            $exerciseIdList = DB::table('exercises')->where('muscle_id', $cardioId)->pluck('id');
            DB::table('resources')->where('model_id', CoreModel::exerciseModelId)->whereIn('owner_id', $exerciseIdList)->delete();
            DB::table('resources')->where('model_id', CoreModel::muscleModelId)->where('owner_id', $cardioId)->delete();
            DB::table('exercises')->whereIn('id', $exerciseIdList)->delete();
            DB::table('muscles')->where('id', $cardioId)->delete();
        }

        if (Schema::hasColumn('muscles', 'kind')) {
            Schema::table('muscles', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
    }

    // el slug de los recursos es unico: si ya existe uno igual (ej. de un grupo eliminado) se le suma un sufijo
    private function insertResource(string $name, string $slug, int $modelId, int $ownerId, string $itemKey): void
    {
        if (DB::table('resources')->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(6));
        }
        DB::table('resources')->insert([
            'name' => $name,
            'kind' => Resource::kindImg,
            'url' => self::publicKeyPrefix.$itemKey,
            'slug' => $slug,
            'owner_id' => $ownerId,
            'model_id' => $modelId,
            'status' => EntityStatus::statusIdActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
