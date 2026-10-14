<?php

use App\Core\CoreModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Anotaciones del calendario del usuario. remind_days dice con cuantos dias de anticipacion avisar (2, 1 y/o 0 = el mismo dia);
     * notified_days guarda los que ya se avisaron (o se saltearon por ya haber pasado) para no repetirlos.
     */
    public function up(): void
    {
        Schema::create('calendar_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->date('note_on');
            $table->string('title', 120);
            $table->text('body')->nullable();
            $table->json('remind_days')->nullable();
            $table->json('notified_days')->nullable();
            $table->integer('status');
            $table->timestamps();
            $table->index(['user_id', 'note_on']);
        });

        DB::table('core_models')->insertOrIgnore([
            'id' => CoreModel::calendarNoteModelId,
            'slug' => 'calendar-note',
            'name' => 'Anotaciones del calendario',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_notes');
        DB::table('core_models')->where('id', CoreModel::calendarNoteModelId)->delete();
    }
};
