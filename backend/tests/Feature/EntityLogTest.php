<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class EntityLogTest extends TestCase
{
    use InteractsWithMuscleApi;

    private array $uploadedImageList = [];

    protected function tearDown(): void
    {
        foreach ($this->uploadedImageList as $imageUrl) {
            File::delete(public_path(parse_url($imageUrl, PHP_URL_PATH)));
        }
        parent::tearDown();
    }

    public function test_routine_actions_are_logged_and_only_admin_reads_them(): void
    {
        $student = $this->register('student@example.com', 'trainee-role');
        $studentId = $student->json('data.user.id');
        $token = $student->json('data.token');
        $exercise = DB::table('exercises')->first(['id', 'name']);

        $routineId = $this->actingWithToken($token)->postJson('/api/v1/routines', ['name' => 'Fuerza', 'frequency' => 2, 'rest_minutes' => 1])
            ->assertCreated()->json('data.id');
        $this->actingWithToken($token)->postJson('/api/v1/routines/'.$routineId.'/exercises', ['exercise_id' => $exercise->id])->assertSuccessful();
        $this->actingWithToken($token)->putJson('/api/v1/routines/'.$routineId, ['name' => 'Fuerza total', 'frequency' => 3, 'rest_minutes' => 2])->assertOk();
        $this->actingWithToken($token)->deleteJson('/api/v1/routines/'.$routineId.'/exercises/'.$exercise->id)->assertOk();
        $this->actingWithToken($token)->deleteJson('/api/v1/routines/'.$routineId)->assertOk();

        $this->actingWithToken($token)->getJson('/api/v1/user/'.$studentId.'/logs')->assertForbidden();

        $logs = $this->actingWithToken($this->login(static::adminEmail))->getJson('/api/v1/user/'.$studentId.'/logs')->assertOk()
            ->assertJsonPath('data.user.id', $studentId)
            ->assertJsonPath('data.hasMore', false)
            ->assertJsonPath('data.items.0.model', 'routine')
            ->assertJsonPath('data.items.0.entityId', $routineId)
            ->assertJsonPath('data.items.1.data.exerciseName', $exercise->name)
            ->assertJsonPath('data.items.2.data.name', 'Fuerza total');
        $this->assertSame(['deleted', 'exercise-removed', 'updated', 'exercise-added', 'created'], array_column($logs->json('data.items'), 'action'));
    }

    public function test_tickets_profile_changes_and_first_rank_up_are_logged_but_not_daily_marks(): void
    {
        $trainer = $this->register('coach@example.com', 'trainer-role')->assertCreated();
        [$trainerId, $trainerToken] = [$trainer->json('data.user.id'), $trainer->json('data.token')];
        $ticketId = $trainer->json('data.tickets.0.id');
        $adminId = DB::table('users')->where('email', static::adminEmail)->value('id');
        $adminToken = $this->login(static::adminEmail);
        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$ticketId.'/reject', ['note' => 'Falta certificado'])->assertOk();
        $this->actingWithToken($trainerToken)->postJson('/api/v1/tickets/'.$ticketId.'/resubmit', ['note' => 'Ya lo subi'])->assertOk();
        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$ticketId.'/approve')->assertOk();

        $this->actingWithToken($adminToken)->getJson('/api/v1/user/'.$adminId.'/logs?model=ticket')->assertOk()
            ->assertJsonPath('data.items.0.action', 'approved')
            ->assertJsonPath('data.items.0.data.ticketType', 'trainer-request')
            ->assertJsonPath('data.items.0.data.requesterName', 'Test User')
            ->assertJsonPath('data.items.1.action', 'rejected')
            ->assertJsonPath('data.items.1.data.note', 'Falta certificado');

        $this->actingWithToken($trainerToken)->putJson('/api/v1/user/'.$trainerId.'/profile', [
            'name' => 'Test', 'last_name' => 'User', 'age' => 30, 'height' => 175, 'weight' => 82,
        ])->assertOk();

        $exerciseId = DB::table('exercises')->value('id');
        $routineId = $this->actingWithToken($trainerToken)->postJson('/api/v1/routines', ['name' => 'Cardio', 'frequency' => 3, 'rest_minutes' => 1])
            ->assertCreated()->json('data.id');
        $this->actingWithToken($trainerToken)->postJson('/api/v1/routines/'.$routineId.'/exercises', ['exercise_id' => $exerciseId, 'duration_minutes' => 40])
            ->assertCreated();
        // cada dia suma como mucho 60 puntos: hacen falta los dos dias de antes y hoy para llegar a 150
        foreach (['2026-01-01' => 130, '2026-01-02' => 60] as $doneOn => $minutes) {
            DB::table('routine_exercise_logs')->insert([
                'user_id' => $trainerId, 'routine_id' => $routineId, 'exercise_id' => $exerciseId, 'done_on' => $doneOn,
                'duration_minutes' => $minutes, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach ([[$exerciseId], [], [$exerciseId]] as $doneList) {
            $this->actingWithToken($trainerToken)->putJson('/api/v1/routines/'.$routineId.'/done', [
                'exercises' => array_map(fn(int $id) => ['exercise_id' => $id], $doneList),
            ])->assertOk();
        }

        // los logros que se desbloquean al entrenar tambien quedan en el registro; aca se miran aparte
        $items = $this->actingWithToken($adminToken)->getJson('/api/v1/user/'.$trainerId.'/logs')->assertOk()->json('data.items');
        $achievementItems = array_values(array_filter($items, fn(array $item) => $item['action'] === 'achievement'));
        $items = array_values(array_filter($items, fn(array $item) => $item['action'] !== 'achievement'));
        $this->assertNotEmpty($achievementItems);
        $this->assertSame(['user', 'beginner', 'novice'], [$items[0]['model'], $items[0]['data']['rank'], $items[0]['data']['previousRank']]);
        $this->assertSame(['rank-up', 'exercise-added', 'created', 'updated', 'resubmitted', 'created'], array_column($items, 'action'));
        $this->assertSame(['weight'], $items[3]['data']['fields']);
        $this->assertSame('ticket', $items[5]['model']);
    }

    public function test_admin_logs_include_assignments_and_catalog_and_can_be_filtered(): void
    {
        $studentId = $this->register('student@example.com', 'trainee-role')->json('data.user.id');
        $admin = DB::table('users')->where('email', static::adminEmail)->value('id');
        $adminToken = $this->login(static::adminEmail);
        $routineId = $this->actingWithToken($adminToken)->postJson('/api/v1/routines', ['name' => 'Premio', 'frequency' => 2, 'rest_minutes' => 1])
            ->assertCreated()->json('data.id');
        $this->actingWithToken($adminToken)->postJson('/api/v1/routines/'.$routineId.'/assign', ['user_id' => $studentId])->assertCreated();
        $this->actingWithToken($adminToken)->deleteJson('/api/v1/routines/'.$routineId.'/assign/'.$studentId)->assertOk();
        $muscle = $this->actingWithToken($adminToken)->post('/api/v1/muscle', [
            'name' => 'Pectoral', 'description' => 'Descripcion del musculo', 'recommended_rest_days' => 2, 'is_public' => 1,
            'image' => UploadedFile::fake()->image('muscle.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->uploadedImageList[] = $muscle->json('data.image_url');

        $this->actingWithToken($adminToken)->getJson('/api/v1/user/'.$admin.'/logs')->assertOk()
            ->assertJsonPath('data.items.0.model', 'muscle')
            ->assertJsonPath('data.items.0.action', 'created')
            ->assertJsonPath('data.items.0.data.name', 'Pectoral')
            ->assertJsonPath('data.items.1.action', 'unassigned')
            ->assertJsonPath('data.items.2.action', 'assigned')
            ->assertJsonPath('data.items.2.data.targetUserName', 'Test User');

        $this->actingWithToken($adminToken)->getJson('/api/v1/user/'.$admin.'/logs?model=muscle')->assertOk()
            ->assertJsonCount(1, 'data.items');
        $this->actingWithToken($adminToken)->getJson('/api/v1/user/'.$admin.'/logs?model=nope')->assertStatus(400);
    }
}
