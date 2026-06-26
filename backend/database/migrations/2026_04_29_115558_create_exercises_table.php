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
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->foreignId('muscle_id')->nullable()->references('id')->on('muscles')->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->integer('recommended_rest_time')->default(1);
            $table->string('image', 200)->nullable();
            $table->string('video', 200)->nullable();
            $table->string('gif', 200)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
