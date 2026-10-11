<?php

use App\Core\EntityStatus;
use App\Models\CoreConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const providerSlug = 'free-exercise-db';

    /**
     * Run the migrations.
     * Free Exercise DB (github.com/yuhonas/free-exercise-db): ejercicios e imagenes de dominio publico, sin api key.
     * Se crea con su credencial y se asigna a "Recursos publicos" si esa accion no tiene otra.
     * Es idempotente.
     */
    public function up(): void
    {
        $now = now();
        $providerId = DB::table('providers')->where('slug', self::providerSlug)->value('id');
        if (!$providerId) {
            $providerId = DB::table('providers')->insertGetId([
                'name' => 'Free Exercise DB',
                'slug' => self::providerSlug,
                'type' => 'free-exercise-db',
                'base_url' => null,
                'status' => EntityStatus::statusIdActive,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $providerConfigId = DB::table('provider_configs')->where('provider_id', $providerId)
            ->where('status', '!=', EntityStatus::statusIdDeleted)->value('id');
        if (!$providerConfigId) {
            $providerConfigId = DB::table('provider_configs')->insertGetId([
                'provider_id' => $providerId,
                'name' => 'Publica',
                'credentials' => null,
                'status' => EntityStatus::statusIdActive,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $coreConfig = DB::table('core_configs')->where('action', CoreConfig::actionFetchPublicResources)->first();
        if (!$coreConfig) {
            DB::table('core_configs')->insert([
                'action' => CoreConfig::actionFetchPublicResources,
                'provider_config_id' => $providerConfigId,
                'status' => EntityStatus::statusIdActive,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } elseif (!$coreConfig->provider_config_id) {
            DB::table('core_configs')->where('id', $coreConfig->id)->update(['provider_config_id' => $providerConfigId, 'updated_at' => $now]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $providerId = DB::table('providers')->where('slug', self::providerSlug)->value('id');
        if (!$providerId) {
            return;
        }
        $providerConfigIdList = DB::table('provider_configs')->where('provider_id', $providerId)->pluck('id');
        DB::table('core_configs')->whereIn('provider_config_id', $providerConfigIdList)->update(['provider_config_id' => null]);
        DB::table('providers')->where('id', $providerId)->delete();
    }
};
