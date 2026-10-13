<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class MuscleKindTest extends TestCase
{
    use InteractsWithMuscleApi {
        setUp as setUpMuscleApi;
    }

    private array $uploadedImageList = [];

    protected function setUp(): void
    {
        $this->setUpMuscleApi();
        Http::preventStrayRequests();
        Http::fake([
            'raw.githubusercontent.com/*' => Http::response([
                ['id' => 'Running_Treadmill', 'name' => 'Running, Treadmill', 'images' => ['Running_Treadmill/0.jpg', 'Running_Treadmill/1.jpg']],
            ]),
            'wger.de/*' => Http::response(['next' => null, 'results' => []]),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->uploadedImageList as $imageUrl) {
            File::delete(public_path(parse_url($imageUrl, PHP_URL_PATH)));
        }
        parent::tearDown();
    }

    public function test_cardio_is_seeded_as_activity_with_exercises(): void
    {
        $groupList = $this->getJson('/api/v1/muscle')->assertOk()->json('data');
        $cardio = collect($groupList)->firstWhere('slug', 'cardio');

        $this->assertSame('activity', $cardio['kind']);
        $this->assertSame('https://raw.githubusercontent.com/yuhonas/free-exercise-db/main/exercises/Running_Treadmill/0.jpg', $cardio['image_url']);

        $exerciseList = $this->getJson('/api/v1/exercise/group/'.$cardio['id'])->assertOk()->json('data');
        $this->assertCount(7, $exerciseList);
        $this->assertContains('Correr en cinta', array_column($exerciseList, 'name'));
        $this->assertContains('Saltar la cuerda', array_column($exerciseList, 'name'));
    }

    public function test_admin_creates_activity_and_changes_its_kind(): void
    {
        $token = $this->login(static::adminEmail);

        $created = $this->createGroup($token, 'Boxeo', 'activity')->assertCreated()->assertJsonPath('data.kind', 'activity');
        $groupId = $created->json('data.id');

        $this->actingWithToken($token)->putJson('/api/v1/muscle/'.$groupId, [
            'name' => 'Boxeo',
            'description' => 'Golpes, guardia y desplazamientos',
            'recommended_rest_days' => 1,
            'kind' => 'muscle',
        ])->assertOk()->assertJsonPath('data.kind', 'muscle');

        $this->createGroup($token, 'Espalda')->assertCreated()->assertJsonPath('data.kind', 'muscle');
        $this->createGroup($token, 'Natacion', 'otro')->assertUnprocessable();
    }

    public function test_routine_activity_exercise_keeps_minutes_and_notes(): void
    {
        $token = $this->login(static::adminEmail);
        $cardioId = collect($this->getJson('/api/v1/muscle')->json('data'))->firstWhere('slug', 'cardio')['id'];
        $exerciseId = collect($this->getJson('/api/v1/exercise/group/'.$cardioId)->json('data'))->firstWhere('name', 'Correr en cinta')['id'];
        $this->getJson('/api/v1/exercise/'.$exerciseId)->assertOk()->assertJsonPath('data.muscleKind', 'activity');

        $routineId = $this->actingWithToken($token)->postJson('/api/v1/routines', [
            'name' => 'Rutina con cardio', 'frequency' => 3, 'rest_minutes' => 1,
        ])->assertCreated()->json('data.id');

        $this->actingWithToken($token)->postJson('/api/v1/routines/'.$routineId.'/exercises', [
            'exercise_id' => $exerciseId,
            'duration_minutes' => 10,
            'notes' => '  Al terminar la rutina, ritmo suave  ',
        ])->assertCreated()
            ->assertJsonPath('data.0.duration_minutes', 10)
            ->assertJsonPath('data.0.muscleKind', 'activity')
            ->assertJsonPath('data.0.sets', null)
            ->assertJsonPath('data.0.notes', 'Al terminar la rutina, ritmo suave');

        $this->actingWithToken($token)->postJson('/api/v1/routines/'.$routineId.'/exercises', [
            'exercise_id' => $exerciseId,
            'sets' => 2,
            'reps' => 10,
            'notes' => 'Pasadas de 10 metros',
        ])->assertCreated()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.duration_minutes', null)
            ->assertJsonPath('data.0.sets', 2)
            ->assertJsonPath('data.0.notes', 'Pasadas de 10 metros');

        $this->actingWithToken($token)->postJson('/api/v1/routines/'.$routineId.'/exercises', [
            'exercise_id' => $exerciseId,
            'duration_minutes' => 601,
        ])->assertUnprocessable();
    }

    private function createGroup(string $token, string $name, ?string $kind = null)
    {
        $response = $this->actingWithToken($token)->post('/api/v1/muscle', array_filter([
            'name' => $name,
            'description' => 'Descripcion del grupo',
            'recommended_rest_days' => 1,
            'kind' => $kind,
            'image' => UploadedFile::fake()->image('group.jpg'),
        ]), ['Accept' => 'application/json']);
        $this->uploadedImageList[] = $response->json('data.image_url');
        return $response;
    }
}
