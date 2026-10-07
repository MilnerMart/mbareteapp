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
        Schema::create('gym_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->references('id')->on('gym_entities');
            $table->foreignId('user_id')->references('id')->on('users');
            $table->timestamps();
            $table->unique(['gym_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_users');
    }
};
