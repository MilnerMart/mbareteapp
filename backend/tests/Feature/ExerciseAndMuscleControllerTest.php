<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ExerciseResourceModel;
use App\Models\Muscle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseAndMuscleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_muscle_index_returns_real_rest_days(): void
    {
        Muscle::factory()->create([
            'recommended_rest_days' => 4,
        ]);

        $this->getJson('/api/v1/muscle')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.recommended_rest_days', 4);
    }

    public function test_muscle_write_routes_require_authentication(): void
    {
        $this->postJson('/api/v1/muscle', [
            'name' => 'Pectorales',
            'slug' => 'pectorales',
            'description' => 'Grupo muscular del pecho',
            'recommended_rest_days' => 2,
            'image_url' => 'images/leoncioPecs.png',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_update_and_delete_muscle(): void
    {
        $token = User::factory()->create()->createToken('test-token')->plainTextToken;

        $create = $this->withToken($token)->postJson('/api/v1/muscle', [
            'name' => 'Pectorales',
            'slug' => 'pectorales',
            'description' => 'Grupo muscular del pecho',
            'recommended_rest_days' => 2,
            'image_url' => 'images/leoncioPecs.png',
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Pectorales');

        $muscleId = $create->json('data.id');

        $this->withToken($token)->putJson('/api/v1/muscle/'.$muscleId, [
            'name' => 'Pectoral Mayor',
            'slug' => 'pectoral-mayor',
            'description' => 'Grupo muscular actualizado',
            'recommended_rest_days' => 3,
            'image_url' => 'images/leoncioPecs.png',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Pectoral Mayor')
            ->assertJsonPath('data.recommended_rest_days', 3);

        $this->withToken($token)
            ->deleteJson('/api/v1/muscle/'.$muscleId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('muscles', ['id' => $muscleId]);
    }

    public function test_authenticated_user_can_create_update_delete_exercise_and_attach_resource(): void
    {
        $token = User::factory()->create()->createToken('test-token')->plainTextToken;
        $muscle = Muscle::factory()->create();

        $create = $this->withToken($token)->postJson('/api/v1/exercise', [
            'name' => 'Curl de biceps',
            'slug' => 'curl-biceps',
            'muscle_id' => $muscle->id,
            'description' => 'Ejercicio de biceps con mancuerna',
            'recommended_rest_time' => 2,
            'image' => 'images/leoncioCurl.png',
            'video' => null,
            'gif' => null,
        ]);

        $create->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Curl de biceps');

        $exerciseId = $create->json('data.id');

        $this->withToken($token)->putJson('/api/v1/exercise/'.$exerciseId, [
            'name' => 'Curl de biceps alterno',
            'slug' => 'curl-biceps-alterno',
            'muscle_id' => $muscle->id,
            'description' => 'Ejercicio actualizado con mancuerna',
            'recommended_rest_time' => 3,
            'image' => 'images/leoncioCurl.png',
            'video' => null,
            'gif' => null,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Curl de biceps alterno')
            ->assertJsonPath('data.recommended_rest_time', 3);

        $this->withToken($token)->postJson('/api/v1/exercise/'.$exerciseId.'/resource', [
            'name' => 'Imagen tecnica',
            'kind' => ExerciseResourceModel::kindImg,
            'url' => 'images/leoncioCurl.png',
            'status' => 1,
        ])->assertCreated()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/exercise/'.$exerciseId.'/resource')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Imagen tecnica');

        $this->withToken($token)
            ->deleteJson('/api/v1/exercise/'.$exerciseId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('exercises', ['id' => $exerciseId]);
    }

    public function test_resource_kind_validation_rejects_unknown_values(): void
    {
        $token = User::factory()->create()->createToken('test-token')->plainTextToken;
        $exercise = Exercise::factory()->for(Muscle::factory())->create();

        $this->withToken($token)->postJson('/api/v1/exercise/'.$exercise->id.'/resource', [
            'name' => 'Archivo raro',
            'kind' => 99,
            'url' => 'images/leoncioCurl.png',
            'status' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['kind']);
    }
}
