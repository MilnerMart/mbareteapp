<?php

use App\Core\EntityStatus;
use App\Models\CoreConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const providerSlug = 'wger';

    /**
     * Run the migrations.
     * wger (wger.de, github.com/wger-project/wger): ejercicios con imagenes y videos bajo licencias Creative Commons, sin api key.
     * El cliente solo usa los recursos con licencias que permiten uso comercial (CC0, CC-BY, CC-BY-SA) y devuelve la atribucion
     * que pide cada una. Hay hasta 3 espacios de "Recursos publicos": se asigna al primero libre.
     * Es idempotente.
     */
    public function up(): void
    {
        $now = now();
        $providerId = DB::table('providers')->where('slug', self::providerSlug)->value('id');
        if (!$providerId) {
            $providerId = DB::table('providers')->insertGetId([
                'name' => 'wger',
                'slug' => self::providerSlug,
                'type' => 'wger',
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

        $coreConfigMap = DB::table('core_configs')->whereIn('action', CoreConfig::publicResourceActionList)->get()->keyBy('action');
        if ($coreConfigMap->contains('provider_config_id', $providerConfigId)) {
            return;
        }
        foreach (CoreConfig::publicResourceActionList as $action) {
            $coreConfig = $coreConfigMap[$action] ?? null;
            if (!$coreConfig) {
                DB::table('core_configs')->insert([
                    'action' => $action,
                    'provider_config_id' => $providerConfigId,
                    'status' => EntityStatus::statusIdActive,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                return;
            }
            if (!$coreConfig->provider_config_id) {
                DB::table('core_configs')->where('id', $coreConfig->id)->update(['provider_config_id' => $providerConfigId, 'updated_at' => $now]);
                return;
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
