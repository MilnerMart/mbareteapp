<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Baja logica que hace el admin desde la ficha del alumno: el usuario queda con status eliminado
     * y se guarda cuando, quien y por que, para mostrarlo en la ficha.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('rest_days');
            $table->unsignedBigInteger('deactivated_by')->nullable()->after('deactivated_at');
            $table->string('deactivation_reason', 500)->nullable()->after('deactivated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['deactivated_at', 'deactivated_by', 'deactivation_reason']);
        });
    }
};
