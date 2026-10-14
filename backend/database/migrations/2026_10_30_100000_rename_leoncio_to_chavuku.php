<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const gymSlugMap = ['leoncion-gym' => 'chavuku-gym'];

    private const gymNameMap = ['Leoncio Gym' => 'Chavuku Gym'];

    /**
     * Run the migrations.
     * La mascota pasa de Leoncio a Chavuku: el gimnasio base (codigo chavuku-gym), las imagenes propias del front
     * (images/leoncioX.png -> images/chavukuX.png) y los slugs que la nombraban. Un nombre que el admin ya cambio no se toca.
     */
    public function up(): void
    {
        // "leoncion" (con n) era un error de tipeo del codigo original: va primero para no dejar "chavukun"
        $this->renameAll(self::gymSlugMap, self::gymNameMap, ['leoncion', 'Leoncion', 'leoncio', 'Leoncio'], ['chavuku', 'Chavuku', 'chavuku', 'Chavuku']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->renameAll(array_flip(self::gymSlugMap), array_flip(self::gymNameMap), ['chavuku', 'Chavuku'], ['leoncio', 'Leoncio']);
    }

    private function renameAll(array $gymSlugMap, array $gymNameMap, array $from, array $to): void
    {
        foreach ($gymSlugMap as $oldSlug => $newSlug) {
            DB::table('gym_entities')->where('slug', $oldSlug)->update(['slug' => $newSlug]);
        }
        foreach ($gymNameMap as $oldName => $newName) {
            DB::table('gym_entities')->where('name', $oldName)->update(['name' => $newName]);
        }

        $imageFrom = array_map(fn(string $text) => 'images/'.$text, $from);
        $imageTo = array_map(fn(string $text) => 'images/'.$text, $to);
        $this->replaceInColumn('resources', 'url', $imageFrom, $imageTo);
        $this->replaceInColumn('resources', 'slug', $from, $to);
        $this->replaceInColumn('resources', 'name', $from, $to);
        $this->replaceInColumn('muscles', 'slug', $from, $to);
        $this->replaceInColumn('exercises', 'slug', $from, $to);
        $this->replaceInColumn('users', 'profile_image_url', $imageFrom, $imageTo);
    }

    private function replaceInColumn(string $table, string $column, array $from, array $to): void
    {
        $query = DB::table($table)->where(fn($where) => array_map(fn(string $text) => $where->orWhere($column, 'like', '%'.$text.'%'), $from));
        foreach ($query->get(['id', $column]) as $row) {
            DB::table($table)->where('id', $row->id)->update([$column => str_replace($from, $to, $row->$column)]);
        }
    }
};
