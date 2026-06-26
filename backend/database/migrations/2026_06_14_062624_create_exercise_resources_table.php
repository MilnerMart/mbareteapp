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
        Schema::create('exercise_resources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('kind');
            $table->string('url');
            $table->foreignId('exercise_id')->nullable()->references('id')->on('exercises')->nullOnDelete();
            $table->integer('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_resources');
    }
};
