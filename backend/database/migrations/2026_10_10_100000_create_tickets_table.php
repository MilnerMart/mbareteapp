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
     * Solicitudes que un usuario genera y que el admin o el dueño del gimnasio aprueban o rechazan
     * (alta como entrenador, ingreso a un gimnasio con su codigo).
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->integer('type');
            $table->foreignId('requester_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->references('id')->on('gym_entities')->nullOnDelete();
            $table->integer('state');
            $table->foreignId('resolved_by')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->integer('status');
            $table->json('data')->nullable();
            $table->timestamps();
            $table->index(['type', 'state']);
        });

        DB::table('core_models')->insertOrIgnore([
            'id' => CoreModel::ticketModelId,
            'slug' => 'ticket',
            'name' => 'Solicitudes',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
        DB::table('core_models')->where('id', CoreModel::ticketModelId)->delete();
    }
};
