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
        Schema::table('routines', function (Blueprint $table) {
            $table->text('data')->nullable()->change();
        });

        Schema::create('routine_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->references('id')->on('routines')->cascadeOnDelete();
            $table->foreignId('exercise_id')->references('id')->on('exercises')->cascadeOnDelete();
            $table->integer('sets')->nullable();
            $table->integer('reps')->nullable();
            $table->timestamps();
            $table->unique(['routine_id', 'exercise_id']);
        });

        Schema::create('user_routines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('routine_id')->references('id')->on('routines')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'routine_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_routines');
        Schema::dropIfExists('routine_exercises');
        Schema::table('routines', function (Blueprint $table) {
            $table->text('data')->nullable(false)->change();
        });
    }
};
