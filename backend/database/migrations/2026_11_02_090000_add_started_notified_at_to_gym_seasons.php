<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Marca que ya se aviso a los participantes que la temporada empezo: una sola vez, la inicie el entrenador con
     * fecha de hoy o la avise la regla programada del ranking el dia que arranca.
     */
    public function up(): void
    {
        Schema::table('gym_seasons', function (Blueprint $table) {
            $table->timestamp('started_notified_at')->nullable()->after('results');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gym_seasons', function (Blueprint $table) {
            $table->dropColumn('started_notified_at');
        });
    }
};
