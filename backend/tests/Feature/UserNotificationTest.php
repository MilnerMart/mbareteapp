<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class UserNotificationTest extends TestCase
{
    use InteractsWithMuscleApi;

    public function test_trainer_request_notifies_admin_and_resolution_notifies_requester(): void
    {
        $register = $this->register('coach@example.com', 'trainer-role')->assertCreated();
        $ticketId = $register->json('data.tickets.0.id');
        $coachToken = $register->json('data.token');
        $adminToken = $this->login(static::adminEmail);

        $this->actingWithToken($adminToken)->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonPath('data.unreadCount', 1)
            ->assertJsonPath('data.items.0.type', 'ticket-received')
            ->assertJsonPath('data.items.0.refId', $ticketId)
            ->assertJsonPath('data.items.0.isRead', false)
            ->assertJsonPath('data.items.0.data.ticketType', 'trainer-request')
            ->assertJsonPath('data.items.0.data.requesterName', 'Test User');
        $this->actingWithToken($coachToken)->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonPath('data.unreadCount', 0);

        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$ticketId.'/reject', ['note' => 'Falta experiencia'])->assertOk();
        $this->actingWithToken($coachToken)->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonPath('data.unreadCount', 1)
            ->assertJsonPath('data.items.0.type', 'ticket-resolved')
            ->assertJsonPath('data.items.0.data.ticketState', 'rejected');

        $this->actingWithToken($coachToken)->postJson('/api/v1/tickets/'.$ticketId.'/resubmit', ['note' => 'Tengo certificado'])->assertOk();
        $this->actingWithToken($adminToken)->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonPath('data.unreadCount', 2)
            ->assertJsonPath('data.items.0.type', 'ticket-resubmitted');
    }

    public function test_gym_join_notifies_only_the_gym_owner(): void
    {
        $owner = $this->register('owner@example.com', 'trainee-role');
        DB::table('gym_entities')->insert([
            'name' => 'Coach Gym', 'slug' => 'coach-gym', 'owner_id' => $owner->json('data.user.id'), 'status' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->register('student@example.com', 'trainee-role', 'coach-gym')->assertCreated();

        $this->actingWithToken($owner->json('data.token'))->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonPath('data.unreadCount', 1)
            ->assertJsonPath('data.items.0.data.ticketType', 'gym-join')
            ->assertJsonPath('data.items.0.data.gymName', 'Coach Gym');
        $this->actingWithToken($this->login(static::adminEmail))->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonPath('data.unreadCount', 0);
    }

    public function test_assigned_routine_notifies_student_once_and_can_be_marked_read(): void
    {
        $student = $this->register('student@example.com', 'trainee-role');
        $studentId = $student->json('data.user.id');
        $studentToken = $student->json('data.token');
        $adminToken = $this->login(static::adminEmail);
        $routineId = $this->actingWithToken($adminToken)->postJson('/api/v1/routines', ['name' => 'Fuerza', 'frequency' => 2, 'rest_minutes' => 1])
            ->assertCreated()->json('data.id');

        $this->actingWithToken($adminToken)->postJson('/api/v1/routines/'.$routineId.'/assign', ['user_id' => $studentId])->assertCreated();
        $this->actingWithToken($adminToken)->postJson('/api/v1/routines/'.$routineId.'/assign', ['user_id' => $studentId])->assertCreated();

        $inbox = $this->actingWithToken($studentToken)->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonPath('data.unreadCount', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.type', 'routine-assigned')
            ->assertJsonPath('data.items.0.refId', $routineId)
            ->assertJsonPath('data.items.0.data.routineName', 'Fuerza');

        $otherToken = $this->register('other@example.com', 'trainee-role')->json('data.token');
        $this->actingWithToken($otherToken)->putJson('/api/v1/me/notifications/read', ['ids' => [$inbox->json('data.items.0.id')]])
            ->assertOk()->assertJsonPath('data.unreadCount', 0);
        $this->actingWithToken($studentToken)->getJson('/api/v1/me/notifications')->assertJsonPath('data.unreadCount', 1);

        $this->actingWithToken($studentToken)->putJson('/api/v1/me/notifications/read', ['ids' => [$inbox->json('data.items.0.id')]])
            ->assertOk()
            ->assertJsonPath('data.unreadCount', 0)
            ->assertJsonPath('data.items.0.isRead', true);
    }

    public function test_pending_profile_fields_go_away_once_completed(): void
    {
        $student = $this->register('student@example.com', 'trainee-role');
        $token = $student->json('data.token');

        $this->actingWithToken($token)->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonPath('data.pendingProfileFields', ['gender', 'goal']);

        $this->actingWithToken($token)->putJson('/api/v1/user/'.$student->json('data.user.id').'/profile', [
            'name' => 'Test', 'last_name' => 'User', 'age' => 30, 'height' => 175, 'weight' => 80,
            'goal' => 'strength', 'gender' => 'unspecified',
        ])->assertOk();
        $this->actingWithToken($token)->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonPath('data.pendingProfileFields', []);
    }

    public function test_only_admin_reads_user_detail_including_private_favorite_routine(): void
    {
        $student = $this->register('student@example.com', 'trainee-role');
        $studentId = $student->json('data.user.id');
        $studentToken = $student->json('data.token');
        $exerciseId = DB::table('exercises')->value('id');
        $routineId = $this->actingWithToken($studentToken)->postJson('/api/v1/routines', ['name' => 'Mi rutina', 'frequency' => 2, 'rest_minutes' => 1])
            ->assertCreated()->json('data.id');
        $this->actingWithToken($studentToken)->postJson('/api/v1/routines/'.$routineId.'/exercises', ['exercise_id' => $exerciseId])->assertSuccessful();
        $this->actingWithToken($studentToken)->putJson('/api/v1/routines/'.$routineId.'/done', ['exercises' => [['exercise_id' => $exerciseId]]])->assertOk();

        $this->actingWithToken($this->register('other@example.com', 'trainee-role')->json('data.token'))
            ->getJson('/api/v1/user/'.$studentId.'/detail')->assertForbidden();

        $this->actingWithToken($this->login(static::adminEmail))->getJson('/api/v1/user/'.$studentId.'/detail')->assertOk()
            ->assertJsonPath('data.email', 'student@example.com')
            ->assertJsonPath('data.roles', ['Alumno'])
            ->assertJsonPath('data.pending_profile_fields', ['gender', 'goal'])
            ->assertJsonPath('data.favorites.routine.name', 'Mi rutina')
            ->assertJsonPath('data.progress.trainingDays', 1)
            ->assertJsonCount(1, 'data.gyms');
    }
}
