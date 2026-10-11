<?php

use App\Core\EntityStatus;
use App\Models\CoreConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const providerSlug = 'local-storage';

    private const actionList = [CoreConfig::actionStoreResources, CoreConfig::actionFetchResources];

    /**
     * Run the migrations.
     * Proveedor de almacenamiento local con su credencial, asignado para guardar y obtener recursos.
     * Asi las imagenes siguen en public/images como hasta ahora, pero pasando por ResourceService.
     * Es idempotente y no pisa una accion que ya tenga otra credencial asignada.
     */
    public function up(): void
    {
        $now = now();
        $providerId = DB::table('providers')->where('slug', self::providerSlug)->value('id');
        if (!$providerId) {
            $providerId = DB::table('providers')->insertGetId([
                'name' => 'Almacenamiento local',
                'slug' => self::providerSlug,
                'type' => 'local-storage',
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
                'name' => 'Public del backend',
                'credentials' => null,
                'status' => EntityStatus::statusIdActive,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::actionList as $action) {
            $coreConfig = DB::table('core_configs')->where('action', $action)->first();
            if (!$coreConfig) {
                DB::table('core_configs')->insert([
                    'action' => $action,
                    'provider_config_id' => $providerConfigId,
                    'status' => EntityStatus::statusIdActive,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } elseif (!$coreConfig->provider_config_id) {
                DB::table('core_configs')->where('id', $coreConfig->id)->update(['provider_config_id' => $providerConfigId, 'updated_at' => $now]);
            }
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
