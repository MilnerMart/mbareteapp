<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Reglas del sistema que corren solas (la definicion de cada una esta en App\SystemRules). system_rules guarda lo que
     * el admin configura de cada regla (si esta activa, cada cuanto, a que hora y en cuantos minutos reintenta si falla)
     * y cuando le toca la proxima vez;
     * system_rule_runs guarda cada ejecucion con su estado y lo que hizo, para ver desde Configuracion que corrio y cuando.
     */
    public function up(): void
    {
        Schema::create('system_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('rule_model_id')->unique();
            $table->boolean('is_enabled')->default(true);
            $table->string('frequency', 20);
            $table->string('run_at', 5);
            $table->unsignedSmallInteger('retry_minutes')->default(0);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        Schema::create('system_rule_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('rule_model_id')->index();
            $table->unsignedSmallInteger('status');
            $table->boolean('is_manual')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_rule_runs');
        Schema::dropIfExists('system_rules');
    }
};
