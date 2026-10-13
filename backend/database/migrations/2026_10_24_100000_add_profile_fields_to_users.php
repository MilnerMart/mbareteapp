<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Perfil del usuario: objetivo y genero, guardados como slug (los textos los pone el front).
     * El genero define como se nombra su rango (Novato/Novata...); sin especificar se usa el general.
     * Nivel, rutina, grupo muscular y ejercicio favorito no se guardan: se calculan con lo que entrena (routine_exercise_logs).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('goal', 30)->nullable()->after('weight');
            $table->string('gender', 20)->nullable()->after('goal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columnList = array_values(array_filter(['goal', 'gender'], fn(string $column) => Schema::hasColumn('users', $column)));
        if ($columnList) {
            Schema::table('users', function (Blueprint $table) use ($columnList) {
                $table->dropColumn($columnList);
            });
        }
    }
};
