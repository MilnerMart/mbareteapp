<?php

use App\Core\CoreModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * resources.model_id referencia core_models, sin esta fila no se pueden guardar imagenes de gimnasios.
     */
    public function up(): void
    {
        DB::table('core_models')->insertOrIgnore([
            'id' => CoreModel::gymEntityModelId,
            'slug' => 'gym',
            'name' => 'Gimnasio',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('core_models')->where('id', CoreModel::gymEntityModelId)->delete();
    }
};
