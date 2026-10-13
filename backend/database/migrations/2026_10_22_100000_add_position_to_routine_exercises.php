<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Los ejercicios de una rutina se pueden reordenar (subir o bajar). Los que ya existian quedan
     * numerados en el orden en que se agregaron, que es como se mostraban hasta ahora.
     */
    public function up(): void
    {
        Schema::table('routine_exercises', function (Blueprint $table) {
            $table->integer('position')->default(0)->after('exercise_id');
        });

        $position = 0;
        $routineId = null;
        foreach (DB::table('routine_exercises')->orderBy('routine_id')->orderBy('id')->get(['id', 'routine_id']) as $row) {
            $position = $row->routine_id === $routineId ? $position + 1 : 1;
            $routineId = $row->routine_id;
            DB::table('routine_exercises')->where('id', $row->id)->update(['position' => $position]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routine_exercises', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
