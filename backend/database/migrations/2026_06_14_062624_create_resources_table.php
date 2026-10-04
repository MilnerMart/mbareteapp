<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->integer('kind');
        $table->string('url');
        $table->foreignId('owner_id')->nullable();
        $table->integer('model_id')->nullable();
        $table->foreign('model_id')->references('id')->on('core_models');
        $table->integer('status');
        $table->timestamps();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};