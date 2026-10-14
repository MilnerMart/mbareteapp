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
     * Avisos de la campanita: ticket recibido o resuelto, rutina asignada... Los textos los arma el front con type y data.
     * Los datos de perfil pendientes no se guardan: se calculan con lo que le falta al usuario.
     */
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->integer('type');
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->integer('status');
            $table->json('data')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        DB::table('core_models')->insertOrIgnore([
            'id' => CoreModel::notificationModelId,
            'slug' => 'notification',
            'name' => 'Notificaciones',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
        DB::table('core_models')->where('id', CoreModel::notificationModelId)->delete();
    }
};
