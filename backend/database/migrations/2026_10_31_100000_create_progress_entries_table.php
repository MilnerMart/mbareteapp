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
     * Lo que el usuario registra de su progreso: peso, medidas del cuerpo y records por ejercicio (kilos o repeticiones maximas).
     * metric dice que es (App\Models\ProgressEntry::metricMap) y exercise_id solo se usa en los records.
     */
    public function up(): void
    {
        Schema::create('progress_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->date('entry_on');
            $table->string('metric', 30);
            $table->foreignId('exercise_id')->nullable()->references('id')->on('exercises')->nullOnDelete();
            $table->decimal('value', 8, 2);
            $table->string('note', 255)->nullable();
            $table->integer('status');
            $table->timestamps();
            $table->index(['user_id', 'entry_on']);
            $table->index(['user_id', 'metric']);
        });

        DB::table('core_models')->insertOrIgnore([
            'id' => CoreModel::progressEntryModelId,
            'slug' => 'progress-entry',
            'name' => 'Registros de progreso',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_entries');
        DB::table('core_models')->where('id', CoreModel::progressEntryModelId)->delete();
    }
};
