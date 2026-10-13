<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * resources.slug queda en 80 caracteres en todos los entornos (en produccion estaba en 40 y no entraban
     * slugs como "extension-de-triceps-sobre-la-cabeza-image"). ResourceService recorta los slugs a ese largo.
     */
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->string('slug', 80)->change();
        });
    }

    /**
     * Reverse the migrations.
     * No se vuelve a 40: los slugs que ya usen hasta 80 caracteres no entrarian.
     */
    public function down(): void
    {
    }
};
