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
     * El correo pasa a ser un canal de los proveedores de mensajeria (email, sms, push, whatsapp).
     * core_configs guarda que credencial usa la app para cada accion (enviar correos, guardar recursos...).
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->string('channel', 30)->nullable()->after('type');
        });

        // los que se hayan cargado con el tipo correo (20) pasan a mensajeria por email
        DB::table('providers')->where('type', 20)->update(['type' => 30, 'channel' => 'email']);

        Schema::create('core_configs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 50)->unique();
            $table->foreignId('provider_config_id')->nullable()->references('id')->on('provider_configs')->nullOnDelete();
            $table->integer('status');
            $table->json('data')->nullable();
            $table->timestamps();
        });

        DB::table('core_models')->insertOrIgnore([
            'id' => CoreModel::coreConfigModelId,
            'slug' => 'core-config',
            'name' => 'Configuracion de la app',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('core_configs');
        DB::table('core_models')->where('id', CoreModel::coreConfigModelId)->delete();
        DB::table('providers')->where('type', 30)->where('channel', 'email')->update(['type' => 20]);
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
