<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * La cantidad de alumnos ahora se calcula desde gym_users y el slug pasa a ser el codigo del gimnasio.
     */
    public function up(): void
    {
        Schema::table('gym_entities', function (Blueprint $table) {
            $table->dropColumn('alumns_count');
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_entities', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->integer('alumns_count')->nullable();
        });
    }
};
