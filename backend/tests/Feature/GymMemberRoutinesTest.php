<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class GymMemberRoutinesTest extends TestCase
{
    use InteractsWithMuscleApi;

    public function test_gym_owner_only_sees_routines_they_created_or_assigned_to_a_student(): void
    {
        [$coachAToken, $coachAId] = $this->approvedTrainer('coach-a@example.com');
        [$coachBToken, $coachBId] = $this->approvedTrainer('coach-b@example.com');
        $gymAId = $this->createGym('Gym A', $coachAId);
        $gymBId = $this->createGym('Gym B', $coachBId);
        $studentId = $this->register('student@example.com', 'trainee-role')->json('data.user.id');
        foreach ([$gymAId, $gymBId] as $gymId) {
            DB::table('gym_users')->insert(['gym_id' => $gymId, 'user_id' => $studentId, 'created_at' => now(), 'updated_at' => now()]);
        }

        $routineA = $this->createAndAssignRoutine($coachAToken, 'Rutina del coach A', $studentId);
        $routineB = $this->createAndAssignRoutine($coachBToken, 'Rutina del coach B', $studentId);
        $adminToken = $this->login(static::adminEmail);
        $routineAdmin = $this->createAndAssignRoutine($adminToken, 'Rutina premio', $studentId);

        $this->assertSame([$routineA], $this->studentRoutineIdList($coachAToken, $gymAId, $studentId));
        $this->assertSame([$routineB], $this->studentRoutineIdList($coachBToken, $gymBId, $studentId));
        $adminToken = $this->login(static::adminEmail);
        $this->assertEqualsCanonicalizing([$routineA, $routineB, $routineAdmin], $this->studentRoutineIdList($adminToken, $gymAId, $studentId));
    }

    private function approvedTrainer(string $email): array
    {
        $register = $this->register($email, 'trainer-role')->assertCreated();
        $this->actingWithToken($this->login(static::adminEmail))
            ->postJson('/api/v1/tickets/'.$register->json('data.tickets.0.id').'/approve')->assertOk();
        return [$this->login($email), $register->json('data.user.id')];
    }

    private function createGym(string $name, int $ownerId): int
    {
        return DB::table('gym_entities')->insertGetId([
            'name' => $name, 'slug' => str($name)->slug(), 'owner_id' => $ownerId, 'status' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createAndAssignRoutine(string $token, string $name, int $studentId): int
    {
        $routineId = $this->actingWithToken($token)->postJson('/api/v1/routines', ['name' => $name, 'frequency' => 2, 'rest_minutes' => 1])
            ->assertCreated()->json('data.id');
        $this->actingWithToken($token)->postJson('/api/v1/routines/'.$routineId.'/assign', ['user_id' => $studentId])->assertCreated();
        return $routineId;
    }

    private function studentRoutineIdList(string $token, int $gymId, int $studentId): array
    {
        $student = collect($this->actingWithToken($token)->getJson('/api/v1/entities/gym/'.$gymId.'/users')->assertOk()->json('data'))
            ->firstWhere('id', $studentId);
        return array_column($student['routines'], 'id');
    }
}
