<?php

use App\Core\EntityStatus;
use App\Models\Permit;
use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const tables = ['muscles', 'exercises'];

    /**
     * Run the migrations.
     * Los entrenadores proponen musculos y ejercicios: quedan pendientes hasta que el admin aprueba su ticket.
     * Pueden ser publicos o privados (solo los ve quien los creo). Lo que ya existia queda publico y aprobado.
     */
    public function up(): void
    {
        foreach (self::tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('id')->references('id')->on('users')->nullOnDelete();
                $table->boolean('is_public')->default(true)->after('owner_id');
                $table->integer('review_state')->default(Ticket::stateIdApproved)->after('is_public');
            });
        }

        if (!DB::table('permits')->where('slug', Permit::createCatalogEntityPermitSlug)->exists()) {
            DB::table('permits')->insert([
                'name' => 'Proponer musculos y ejercicios',
                'slug' => Permit::createCatalogEntityPermitSlug,
                'role_id' => DB::table('roles')->where('slug', 'trainer-role')->value('id'),
                'status' => EntityStatus::statusIdActive,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $trainerRole = DB::table('roles')->where('slug', 'trainer-role')->first(['id', 'data']);
        if ($trainerRole) {
            $data = json_decode($trainerRole->data ?? '', true) ?: [];
            $rolePermits = $data['rolePermits'] ?? [];
            if (!in_array(Permit::createCatalogEntityPermitSlug, $rolePermits, true)) {
                $data['rolePermits'] = [...$rolePermits, Permit::createCatalogEntityPermitSlug];
                DB::table('roles')->where('id', $trainerRole->id)->update(['data' => json_encode($data), 'updated_at' => now()]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['owner_id']);
                $table->dropColumn(['owner_id', 'is_public', 'review_state']);
            });
        }

        $trainerRole = DB::table('roles')->where('slug', 'trainer-role')->first(['id', 'data']);
        if ($trainerRole) {
            $data = json_decode($trainerRole->data ?? '', true) ?: [];
            $data['rolePermits'] = array_values(array_diff($data['rolePermits'] ?? [], [Permit::createCatalogEntityPermitSlug]));
            DB::table('roles')->where('id', $trainerRole->id)->update(['data' => json_encode($data)]);
        }
        DB::table('permits')->where('slug', Permit::createCatalogEntityPermitSlug)->delete();
    }
};
