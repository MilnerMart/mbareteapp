<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cada vez que el usuario marca un ejercicio de una rutina como hecho se guarda una fila por dia, con una copia
     * de las series, repeticiones y minutos de ese dia y los kg que uso. Con esto la app calcula sus favoritos
     * y los puntos de su nivel (dias entrenados, racha, tiempo y kilos levantados).
     */
    public function up(): void
    {
        Schema::create('routine_exercise_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('routine_id')->references('id')->on('routines')->cascadeOnDelete();
            $table->foreignId('exercise_id')->references('id')->on('exercises')->cascadeOnDelete();
            $table->date('done_on');
            $table->integer('sets')->nullable();
            $table->integer('reps')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'routine_id', 'exercise_id', 'done_on'], 'routine_exercise_logs_unique_day');
            $table->index(['user_id', 'done_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routine_exercise_logs');
    }
};
