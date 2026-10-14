<?php

use App\Core\CoreModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Registro de actividad: quien hizo que accion sobre que entidad (model_id de core_models + entity_id).
     * El nombre de la entidad queda en data para seguir viendolo aunque despues se borre o se renombre.
     */
    public function up(): void
    {
        Schema::create('entity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->integer('model_id');
            $table->unsignedBigInteger('entity_id');
            $table->integer('action');
            $table->integer('status');
            $table->json('data')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['model_id', 'entity_id']);
        });

        DB::table('core_models')->insertOrIgnore([
            ['id' => CoreModel::entityLogModelId, 'slug' => 'entity-log', 'name' => 'Registro de actividad', 'created_at' => now(), 'updated_at' => now()],
            ['id' => CoreModel::userModelId, 'slug' => 'user', 'name' => 'Usuarios', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_logs');
        DB::table('core_models')->whereIn('id', [CoreModel::entityLogModelId, CoreModel::userModelId])->delete();
    }
};
