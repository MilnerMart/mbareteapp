<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gym_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 100);
            $table->foreignId('owner_id')->nullable();
            $table->integer('alumns_count')->nullable();
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_entities');
    }
};
