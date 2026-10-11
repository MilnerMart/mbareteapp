<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * El tipo del proveedor pasa a ser la clave del cliente que lo instancia (config/provider_clients.php).
     * Lo que puede hacer (mensajeria, recursos...) sale de las interfaces de ese cliente, asi que el canal ya no va.
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropIndex(['type', 'status']);
            $table->dropColumn('channel');
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->string('type', 50)->change();
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropIndex(['type']);
        });

        DB::table('providers')->whereRaw("type NOT REGEXP '^[0-9]+$'")->update(['type' => '0']);

        Schema::table('providers', function (Blueprint $table) {
            $table->integer('type')->change();
            $table->string('channel', 30)->nullable()->after('type');
            $table->index(['type', 'status']);
        });
    }
};
