<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cada ejercicio de una rutina puede indicar minutos (ej. 10 min de cinta) ademas de series y repeticiones,
     * y una nota con indicaciones extra (ej. pasadas de 10 metros, ritmo suave, al terminar la rutina).
     */
    public function up(): void
    {
        Schema::table('routine_exercises', function (Blueprint $table) {
            $table->integer('duration_minutes')->nullable()->after('reps');
            $table->string('notes', 255)->nullable()->after('duration_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routine_exercises', function (Blueprint $table) {
            $table->dropColumn(['duration_minutes', 'notes']);
        });
    }
};
