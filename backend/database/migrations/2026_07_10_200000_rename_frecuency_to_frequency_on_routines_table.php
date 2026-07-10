<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('routines')
            && Schema::hasColumn('routines', 'frecuency')
            && !Schema::hasColumn('routines', 'frequency')
        ) {
            Schema::table('routines', function (Blueprint $table) {
                $table->renameColumn('frecuency', 'frequency');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('routines')
            && Schema::hasColumn('routines', 'frequency')
            && !Schema::hasColumn('routines', 'frecuency')
        ) {
            Schema::table('routines', function (Blueprint $table) {
                $table->renameColumn('frequency', 'frecuency');
            });
        }
    }
};
