<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class CatalogTicketTest extends TestCase
{
    use InteractsWithMuscleApi;

    // imagenes que los tests suben a public/ y se borran al terminar
    private array $uploadedImageList = [];

    protected function tearDown(): void
    {
        foreach ($this->uploadedImageList as $imageUrl) {
            File::delete(public_path(parse_url($imageUrl, PHP_URL_PATH)));
        }
        parent::tearDown();
    }

    public function test_trainer_private_exercise_is_listed_only_for_trainer_after_admin_approval(): void
    {
        // cada login del admin invalida su token anterior: se loguea despues de aprobar al entrenador
        [$trainerToken] = $this->registerTrainer('coach@example.com');
        $adminToken = $this->login(static::adminEmail);
        $muscleId = $this->createMuscle($adminToken, true)->assertCreated()
            ->assertJsonPath('data.reviewState', 'approved')->assertJsonPath('data.ticket', null)->json('data.id');
        $studentToken = $this->register('student@example.com', 'trainee-role')->json('data.token');

        $created = $this->createExercise($trainerToken, $muscleId, false)->assertCreated()
            ->assertJsonPath('data.reviewState', 'pending')->assertJsonPath('data.isPublic', false)
            ->assertJsonPath('data.ticket.type', 'exercise-create');
        $exerciseId = $created->json('data.id');
        $ticketId = $created->json('data.ticket.id');

        $this->actingWithToken($trainerToken)->getJson('/api/v1/exercise/group/'.$muscleId)->assertOk()->assertJsonCount(0, 'data');
        $this->actingWithToken($adminToken)->getJson('/api/v1/tickets')->assertOk()
            ->assertJsonPath('data.0.id', $ticketId)
            ->assertJsonPath('data.0.refs.entity.name', 'Press privado')
            ->assertJsonPath('data.0.refs.entity.description', 'Descripcion del ejercicio');
        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$ticketId.'/approve')->assertOk();

        $this->actingWithToken($trainerToken)->getJson('/api/v1/exercise/group/'.$muscleId)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $exerciseId);
        $this->actingWithToken($studentToken)->getJson('/api/v1/exercise/group/'.$muscleId)->assertOk()->assertJsonCount(0, 'data');
        $this->actingWithToken($studentToken)->getJson('/api/v1/exercise/'.$exerciseId)->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/api/v1/exercise/'.$exerciseId)->assertNotFound();

        // el entrenador lo usa en su rutina y el alumno asignado lo ve con su descripcion e imagen
        $routineId = $this->actingWithToken($trainerToken)->postJson('/api/v1/routines', [
            'name' => 'Rutina pecho', 'frequency' => 3, 'rest_minutes' => 1,
        ])->assertCreated()->json('data.id');
        $this->actingWithToken($trainerToken)->postJson('/api/v1/routines/'.$routineId.'/exercises', ['exercise_id' => $exerciseId])
            ->assertCreated();
        $this->actingWithToken($trainerToken)->getJson('/api/v1/routines/'.$routineId)->assertOk()
            ->assertJsonPath('data.exercises.0.description', 'Descripcion del ejercicio')
            ->assertJsonCount(1, 'data.exercises.0.resources');
        $studentRoutineId = $this->actingWithToken($studentToken)->postJson('/api/v1/routines', [
            'name' => 'Mi rutina', 'frequency' => 2, 'rest_minutes' => 1,
        ])->json('data.id');
        $this->actingWithToken($studentToken)->postJson('/api/v1/routines/'.$studentRoutineId.'/exercises', ['exercise_id' => $exerciseId])
            ->assertStatus(400);
    }

    public function test_rejected_public_muscle_can_be_resubmitted_and_then_listed_for_everyone(): void
    {
        [$trainerToken] = $this->registerTrainer('coach2@example.com');
        $adminToken = $this->login(static::adminEmail);

        $created = $this->createMuscle($trainerToken, true)->assertCreated()->assertJsonPath('data.ticket.type', 'muscle-create');
        $muscleId = $created->json('data.id');
        $ticketId = $created->json('data.ticket.id');
        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/api/v1/muscle')->assertOk()->assertJsonMissing(['id' => $muscleId]);
        $this->actingWithToken($trainerToken)->getJson('/api/v1/muscle/'.$muscleId)->assertOk()->assertJsonPath('data.reviewState', 'pending');

        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$ticketId.'/reject', ['note' => 'Falta la foto correcta'])->assertOk();
        $this->actingWithToken($trainerToken)->getJson('/api/v1/muscle/'.$muscleId)->assertJsonPath('data.reviewState', 'rejected');
        $this->actingWithToken($trainerToken)->postJson('/api/v1/tickets/'.$ticketId.'/resubmit', ['note' => 'Ya la corregi'])->assertOk();
        $this->actingWithToken($trainerToken)->getJson('/api/v1/muscle/'.$muscleId)->assertJsonPath('data.reviewState', 'pending');
        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$ticketId.'/approve')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withoutToken()->getJson('/api/v1/muscle')->assertOk()->assertJsonFragment(['id' => $muscleId, 'isPublic' => true]);
    }

    public function test_trainee_cannot_propose_catalog_entities(): void
    {
        $studentToken = $this->register('student2@example.com', 'trainee-role')->json('data.token');
        $this->createMuscle($studentToken, true)->assertForbidden();
    }

    /**
     * Registra un entrenador y aprueba su alta: [token, userId]
     */
    private function registerTrainer(string $email): array
    {
        $register = $this->register($email, 'trainer-role');
        $this->actingWithToken($this->login(static::adminEmail))
            ->postJson('/api/v1/tickets/'.$register->json('data.tickets.0.id').'/approve')->assertOk();
        return [$register->json('data.token'), $register->json('data.user.id')];
    }

    private function createMuscle(string $token, bool $isPublic)
    {
        $response = $this->actingWithToken($token)->post('/api/v1/muscle', [
            'name' => 'Pectoral',
            'description' => 'Descripcion del musculo',
            'recommended_rest_days' => 2,
            'is_public' => $isPublic ? 1 : 0,
            'image' => UploadedFile::fake()->image('muscle.jpg'),
        ], ['Accept' => 'application/json']);
        $this->rememberImage($response->json('data.image_url'));
        return $response;
    }

    private function createExercise(string $token, int $muscleId, bool $isPublic)
    {
        $response = $this->actingWithToken($token)->post('/api/v1/exercise', [
            'name' => 'Press privado',
            'muscle_id' => $muscleId,
            'description' => 'Descripcion del ejercicio',
            'recommended_rest_time' => 60,
            'is_public' => $isPublic ? 1 : 0,
            'image' => UploadedFile::fake()->image('exercise.jpg'),
        ], ['Accept' => 'application/json']);
        $this->rememberImage($response->json('data.image_url'));
        return $response;
    }

    private function rememberImage(?string $imageUrl): void
    {
        if($imageUrl){
            $this->uploadedImageList[] = $imageUrl;
        }
    }
}
