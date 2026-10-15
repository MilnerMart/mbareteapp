<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ranking por temporadas en cada gimnasio. El entrenador inicia cada temporada eligiendo sus fechas y decide si sus
     * alumnos ven el ranking. Al terminar, su resultado queda congelado en results (podio, premios y puntos de cada uno);
     * mientras esta en curso results queda vacio y el ranking se calcula al consultarlo.
     */
    public function up(): void
    {
        Schema::table('gym_entities', function (Blueprint $table) {
            $table->boolean('is_ranking_public')->default(false)->after('owner_id');
        });

        Schema::create('gym_seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->references('id')->on('gym_entities')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->json('results')->nullable();
            $table->timestamps();
            $table->unique(['gym_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_seasons');
        Schema::table('gym_entities', function (Blueprint $table) {
            $table->dropColumn('is_ranking_public');
        });
    }
};
