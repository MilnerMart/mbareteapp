<?php

use App\Core\CoreModel;
use App\Core\EntityStatus;
use App\Models\Muscle;
use App\Models\Resource;
use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const publicKeyPrefix = Resource::publicKeyPrefix.'free-exercise-db:';

    // grupo => slugs posibles del musculo (en algunos entornos se crearon con otro slug)
    private const muscleSlugMap = [
        'triceps' => ['muscle-triceps', 'triceps'],
        'espalda' => ['espalda'],
        'pantorrillas' => ['pantorrillas', 'leoncio-calves'],
        'abdominales' => ['abdominales'],
        'hombros' => ['hombros'],
        'antebrazos' => ['antebrazos'],
        'cuadriceps' => ['cuadriceps'],
        'biceps' => ['muscle-biceps', 'biceps'],
        'gluteos' => ['gluteos'],
        'pecho' => ['pecho'],
    ];

    // grupo => [nombre, ejercicio de Free Exercise DB, descanso entre series (seg), descripcion]
    private const exerciseMap = [
        'triceps' => [
            ['Extensión de tríceps en polea', 'Triceps_Pushdown', 60, 'El clásico para esculpir la herradura del tríceps. Codos pegados al cuerpo y fijos como bisagras: empuja la barra hasta extender por completo y aprieta abajo un segundo. Sin balanceo, toda la tensión queda en el brazo.'],
            ['Rompecráneos con barra Z', 'EZ-Bar_Skullcrusher', 90, 'Masa pura para la parte larga del tríceps. Acostado en el banco, baja la barra con control hacia la frente doblando solo los codos y súbela con potencia. Brazos quietos y verticales: el tríceps hace todo el trabajo.'],
            ['Extensión de tríceps sobre la cabeza', 'Standing_Dumbbell_Triceps_Extension', 60, 'Estira el tríceps al máximo para ganar volumen real. De pie y con el core firme, lleva la mancuerna detrás de la cabeza con los codos apuntando al techo y extiende hasta arriba. Lento al bajar, explosivo al subir.'],
            ['Patada de tríceps con mancuerna', 'Tricep_Dumbbell_Kickback', 60, 'El remate perfecto para definir. Torso inclinado y codo pegado al costado: extiende el brazo hacia atrás hasta bloquear y sostén la contracción. Peso moderado y técnica impecable para sentir cada repetición.'],
        ],
        'espalda' => [
            ['Dominadas', 'Pullups', 120, 'La reina de los ejercicios de espalda. Cuélgate con agarre firme, saca pecho y tira hasta pasar la barbilla sobre la barra llevando los codos hacia abajo. Fuerza, amplitud y control con tu propio peso.'],
            ['Remo con barra', 'Bent_Over_Barbell_Row', 90, 'Construye una espalda gruesa y poderosa. Torso inclinado, espalda neutra y rodillas flexionadas: lleva la barra hacia el abdomen juntando los omóplatos y baja con control. Potencia de pies a cabeza.'],
            ['Remo a un brazo con mancuerna', 'One-Arm_Dumbbell_Row', 60, 'Trabajo unilateral para corregir desequilibrios y sumar detalle. Apoya rodilla y mano en el banco, tira de la mancuerna hacia la cadera con el codo pegado y estira completo al bajar. Cada lado rinde al máximo.'],
            ['Remo sentado en polea', 'Seated_Cable_Rows', 60, 'Tensión constante para una espalda densa y marcada. Pecho arriba y espalda recta: tira del agarre hacia el abdomen apretando los omóplatos y vuelve lento sin redondear la espalda. Control total en cada tramo.'],
        ],
        'pantorrillas' => [
            ['Elevación de talones sentado', 'Seated_Calf_Raise', 45, 'Directo al sóleo, el músculo que da grosor a la pantorrilla. Rodillas bajo el apoyo, baja el talón hasta sentir el estiramiento y sube a la punta con fuerza. Pausa arriba y abajo: aquí no se rebota.'],
            ['Pantorrillas en prensa', 'Calf_Press_On_The_Leg_Press_Machine', 45, 'Carga pesada sin castigar la espalda. Con las piernas casi extendidas y solo la punta de los pies en la plataforma, empuja hasta la máxima elevación y baja profundo. Recorrido completo, pantorrillas encendidas.'],
            ['Elevación de talones tipo burro', 'Donkey_Calf_Raises', 45, 'El secreto de la vieja escuela para pantorrillas de acero. Torso inclinado hacia adelante para un estiramiento extra: baja el talón al máximo y sube explosivo sobre la punta. Cada repetición cuenta.'],
        ],
        'abdominales' => [
            ['Plancha', 'Plank', 45, 'Core de acero sin moverte un centímetro. Antebrazos bajo los hombros, cuerpo en línea recta de la cabeza a los talones y abdomen apretado como si fueras a recibir un golpe. Respira y aguanta: la resistencia se construye aquí.'],
            ['Elevación de piernas colgado', 'Hanging_Leg_Raise', 60, 'Desafío total para la parte baja del abdomen. Colgado de la barra y sin balanceo, eleva las piernas con control llevando la pelvis hacia adelante y bájalas lento. Fuerza de agarre y abdomen trabajando juntos.'],
            ['Giro ruso', 'Russian_Twist', 45, 'Oblicuos encendidos y una cintura firme. Sentado con el torso inclinado hacia atrás y los pies apoyados o en el aire, gira de lado a lado llevando las manos o el peso junto a la cadera. Ritmo constante y espalda recta.'],
            ['Rueda abdominal', 'Ab_Roller', 60, 'Uno de los ejercicios más exigentes para todo el core. De rodillas, rueda hacia adelante con el abdomen apretado y la espalda sin arquear, y vuelve tirando desde el centro del cuerpo. Avanza solo hasta donde controles.'],
        ],
        'hombros' => [
            ['Press militar con barra', 'Standing_Military_Press', 120, 'El rey para hombros fuertes y anchos. De pie, glúteos y abdomen firmes, empuja la barra desde el pecho hasta bloquear sobre la cabeza sin arquear la espalda. Fuerza real que se nota en todo el tren superior.'],
            ['Elevaciones laterales', 'Side_Lateral_Raise', 60, 'La clave para unos hombros redondos y anchos. Codos ligeramente flexionados, eleva las mancuernas hacia los lados hasta la altura de los hombros y baja lento. Sin impulso: el deltoide lateral hace todo.'],
            ['Elevaciones frontales', 'Front_Dumbbell_Raise', 60, 'Define el frente del hombro con precisión. Eleva la mancuerna al frente hasta la altura de los ojos con el brazo casi extendido y baja con control. Torso quieto y abdomen firme para no balancearte.'],
            ['Pájaros con mancuernas', 'Reverse_Flyes', 60, 'Hombros completos y una postura imponente. Torso inclinado y espalda recta, abre los brazos hacia los lados juntando los omóplatos arriba. El deltoide posterior, el gran olvidado, trabaja a fondo.'],
            ['Press Arnold', 'Arnold_Dumbbell_Press', 90, 'El press que lleva el nombre de una leyenda. Arranca con las palmas hacia ti y gíralas mientras empujas hasta arriba: el recorrido extra activa las tres cabezas del hombro. Control y potencia en cada rotación.'],
        ],
        'antebrazos' => [
            ['Curl de muñeca', 'Palms-Up_Barbell_Wrist_Curl_Over_A_Bench', 45, 'Antebrazos marcados y un agarre de hierro. Apoya los antebrazos en el banco con las palmas hacia arriba, deja caer la barra hasta los dedos y súbela doblando solo las muñecas. Recorrido corto, quemazón garantizada.'],
            ['Curl de muñeca inverso', 'Palms-Down_Wrist_Curl_Over_A_Bench', 45, 'Equilibrio y fuerza para la parte externa del antebrazo. Palmas hacia abajo y antebrazos apoyados: eleva la barra solo con las muñecas y baja lento. Protege codos y muñecas mientras sumas detalle.'],
            ['Caminata del granjero', 'Farmers_Walk', 90, 'Fuerza funcional en estado puro. Toma dos pesos pesados, hombros atrás y abdomen firme, y camina con pasos cortos sin soltar. Agarre, antebrazos y core trabajan a la vez como en la vida real.'],
            ['Rodillo de muñeca', 'Wrist_Roller', 60, 'Quemazón intensa para unos antebrazos de acero. Brazos al frente, enrolla la cuerda girando las muñecas hasta subir el peso y desenróllala con control. Un ejercicio simple que no perdona.'],
        ],
        'cuadriceps' => [
            ['Sentadilla con barra', 'Barbell_Squat', 120, 'El ejercicio rey de las piernas. Barra sobre la espalda alta, pecho arriba y rodillas en la línea de los pies: baja hasta romper el paralelo y sube empujando el suelo con fuerza. Potencia total para el tren inferior.'],
            ['Prensa de piernas', 'Leg_Press', 90, 'Carga pesada con la espalda protegida. Pies a la altura de los hombros en la plataforma, baja con control hasta 90 grados y empuja sin bloquear las rodillas. Cuádriceps a fondo en cada repetición.'],
            ['Extensión de cuádriceps', 'Leg_Extensions', 60, 'Aislamiento total para unos cuádriceps definidos. Espalda apoyada y rodillas alineadas con el eje de la máquina: extiende las piernas, aprieta arriba un segundo y baja lento. El cierre ideal para piernas.'],
        ],
        'biceps' => [
            ['Curl de bíceps con barra', 'Barbell_Curl', 60, 'El básico infalible para bíceps grandes. De pie, codos pegados al torso: sube la barra hasta el pecho apretando fuerte y baja lento hasta extender. Nada de balanceo, la fuerza sale solo de los brazos.'],
            ['Curl predicador', 'Preacher_Curl', 60, 'Aislamiento estricto sin trampas posibles. Con los brazos apoyados en el banco predicador, sube la barra contrayendo el bíceps al máximo y baja controlando hasta casi extender. Pico de bíceps en cada repetición.'],
        ],
        'gluteos' => [
            ['Patada de glúteo', 'Glute_Kickback', 45, 'Directo al glúteo para darle forma y firmeza. En cuatro apoyos y con el abdomen firme, empuja una pierna hacia atrás y arriba apretando el glúteo arriba un segundo. Sin arquear la espalda: el glúteo manda.'],
            ['Step-up con elevación de rodilla', 'Step-up_with_Knee_Raise', 60, 'Glúteos y equilibrio en un solo movimiento. Sube al banco empujando con el talón de la pierna de apoyo y eleva la otra rodilla al frente. Baja con control y repite: potencia unilateral que se nota.'],
        ],
        'pecho' => [
            ['Flexiones de brazos', 'Pushups', 60, 'Pecho fuerte en cualquier lugar y sin equipamiento. Manos algo más abiertas que los hombros y cuerpo en línea recta: baja el pecho hasta casi tocar el suelo y empuja con fuerza. Simple, efectivo y siempre disponible.'],
        ],
    ];

    /**
     * Run the migrations.
     * Suma ejercicios principales a cada grupo muscular, con imagenes de Free Exercise DB (dominio publico).
     * No repite ejercicios ni imagenes: saltea lo que el musculo ya tiene y las imagenes que ya usa otro ejercicio.
     * Si un musculo no existe en el entorno se saltea. Es idempotente.
     */
    public function up(): void
    {
        $now = now();
        foreach (self::exerciseMap as $group => $exerciseList) {
            $muscleId = DB::table('muscles')->whereIn('slug', self::muscleSlugMap[$group])->where('kind', Muscle::kindMuscle)
                ->where('status', EntityStatus::statusIdActive)->orderByDesc('id')->value('id');
            if (!$muscleId) {
                continue;
            }

            foreach ($exerciseList as [$name, $itemKey, $restSeconds, $description]) {
                $slug = Str::slug($name);
                $mainUrl = self::publicKeyPrefix.$itemKey.'/0.jpg';
                $isRepeated = DB::table('exercises')->where('muscle_id', $muscleId)->where('status', EntityStatus::statusIdActive)
                        ->where(fn($query) => $query->where('slug', $slug)->orWhere('name', $name))->exists()
                    || DB::table('resources')->where('url', $mainUrl)->where('status', EntityStatus::statusIdActive)->exists();
                if ($isRepeated) {
                    continue;
                }

                $exerciseId = DB::table('exercises')->insertGetId([
                    'owner_id' => null,
                    'is_public' => true,
                    'review_state' => Ticket::stateIdApproved,
                    'name' => $name,
                    'slug' => $slug,
                    'muscle_id' => $muscleId,
                    'description' => $description,
                    'recommended_rest_time' => $restSeconds,
                    'status' => EntityStatus::statusIdActive,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->insertResource($name, $slug.'-image', $exerciseId, $mainUrl);
                $this->insertResource($name.' · Posición final', $slug.'-'.Str::lower(Str::random(6)), $exerciseId, self::publicKeyPrefix.$itemKey.'/1.jpg');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // solo lo que agrego esta migracion: mismo nombre y misma imagen principal
        $query = DB::table('exercises as e')
            ->join('resources as r', fn($join) => $join->on('r.owner_id', '=', 'e.id')->where('r.model_id', CoreModel::exerciseModelId))
            ->whereNull('e.owner_id')
            ->where(function($where){
                foreach (self::exerciseMap as $exerciseList) {
                    foreach ($exerciseList as [$name, $itemKey]) {
                        $where->orWhere(fn($pair) => $pair->where('e.name', $name)->where('r.url', self::publicKeyPrefix.$itemKey.'/0.jpg'));
                    }
                }
            });
        $exerciseIdList = $query->pluck('e.id');
        DB::table('resources')->where('model_id', CoreModel::exerciseModelId)->whereIn('owner_id', $exerciseIdList)->delete();
        DB::table('exercises')->whereIn('id', $exerciseIdList)->delete();
    }

    // el slug de los recursos es unico: si ya existe uno igual se le suma un sufijo
    private function insertResource(string $name, string $slug, int $exerciseId, string $url): void
    {
        if (DB::table('resources')->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(6));
        }
        DB::table('resources')->insert([
            'name' => $name,
            'kind' => Resource::kindImg,
            'url' => $url,
            'slug' => $slug,
            'owner_id' => $exerciseId,
            'model_id' => CoreModel::exerciseModelId,
            'status' => EntityStatus::statusIdActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
