<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Dias de descanso del usuario (dia ISO de la semana: 1 = lunes ... 7 = domingo).
     * Si no entrena en esos dias no pierde la racha. Todos arrancan con sabado y domingo, tambien los que ya existian;
     * una lista vacia es que eligio no tener dias de descanso.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('rest_days')->nullable()->after('theme');
        });
        DB::table('users')->update(['rest_days' => json_encode([6, 7])]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rest_days');
        });
    }
};
