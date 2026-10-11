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
     * Proveedores externos (recursos, correo, mensajeria) y las credenciales para conectarse a ellos.
     * Un proveedor puede tener varias configuraciones (ej. una cuenta de prueba y otra de produccion).
     */
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->integer('type');
            $table->string('base_url', 255)->nullable();
            $table->integer('status');
            $table->json('data')->nullable();
            $table->timestamps();
            $table->index(['type', 'status']);
        });

        Schema::create('provider_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->string('name', 100);
            // json cifrado con la APP_KEY, nunca se guarda en claro
            $table->text('credentials')->nullable();
            $table->integer('status');
            $table->json('data')->nullable();
            $table->timestamps();
        });

        DB::table('core_models')->insertOrIgnore([
            ['id' => CoreModel::providerModelId, 'slug' => 'provider', 'name' => 'Proveedores', 'created_at' => now(), 'updated_at' => now()],
            ['id' => CoreModel::providerConfigModelId, 'slug' => 'provider-config', 'name' => 'Configuraciones de proveedores', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_configs');
        Schema::dropIfExists('providers');
        DB::table('core_models')->whereIn('id', [CoreModel::providerModelId, CoreModel::providerConfigModelId])->delete();
    }
};
