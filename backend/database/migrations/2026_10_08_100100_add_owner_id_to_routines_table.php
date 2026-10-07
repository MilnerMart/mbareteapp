<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * owner_id se agrego a la migracion original de routines despues de que ya habia corrido,
     * en esas bases la columna no existe.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('routines', 'owner_id')) {
            Schema::table('routines', function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('frequency');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // la columna pertenece a la migracion original de routines
    }
};
