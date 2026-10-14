<?php

namespace Tests\Feature;

use App\Models\Gym;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class GymJoinRequestTest extends TestCase
{
    use InteractsWithMuscleApi;

    public function test_student_asks_to_join_a_gym_with_its_code_and_owner_accepts(): void
    {
        $owner = $this->register('owner@example.com', 'trainee-role');
        $gymId = DB::table('gym_entities')->insertGetId([
            'name' => 'Coach Gym', 'slug' => 'coach-gym', 'owner_id' => $owner->json('data.user.id'), 'status' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $student = $this->register('student@example.com', 'trainee-role');
        [$studentId, $studentToken] = [$student->json('data.user.id'), $student->json('data.token')];

        $this->actingWithToken($studentToken)->postJson('/api/v1/me/gyms/join', ['gym_code' => 'nope'])->assertStatus(400);
        $this->actingWithToken($studentToken)->postJson('/api/v1/me/gyms/join', ['gym_code' => Gym::baseGymSlug])->assertStatus(400);
        $this->actingWithToken($owner->json('data.token'))->postJson('/api/v1/me/gyms/join', ['gym_code' => 'coach-gym'])->assertStatus(400);

        $ticketId = $this->actingWithToken($studentToken)->postJson('/api/v1/me/gyms/join', ['gym_code' => ' Coach-Gym '])
            ->assertCreated()
            ->assertJsonPath('data.type', 'gym-join')
            ->assertJsonPath('data.state', 'pending')
            ->assertJsonPath('data.refs.gym.name', 'Coach Gym')
            ->json('data.id');
        $this->actingWithToken($studentToken)->postJson('/api/v1/me/gyms/join', ['gym_code' => 'coach-gym'])->assertStatus(400);
        $this->assertFalse(DB::table('gym_users')->where('gym_id', $gymId)->where('user_id', $studentId)->exists());

        $ownerToken = $owner->json('data.token');
        $this->actingWithToken($ownerToken)->getJson('/api/v1/me/notifications')->assertOk()
            ->assertJsonPath('data.items.0.type', 'ticket-received')
            ->assertJsonPath('data.items.0.refId', $ticketId);
        $this->actingWithToken($ownerToken)->postJson('/api/v1/tickets/'.$ticketId.'/approve')->assertOk();
        $this->assertTrue(DB::table('gym_users')->where('gym_id', $gymId)->where('user_id', $studentId)->exists());
        $this->actingWithToken($studentToken)->postJson('/api/v1/me/gyms/join', ['gym_code' => 'coach-gym'])->assertStatus(400);
    }

    public function test_student_can_only_wait_for_or_belong_to_one_gym_besides_the_base(): void
    {
        $ownerA = $this->register('owner-a@example.com', 'trainee-role');
        $ownerB = $this->register('owner-b@example.com', 'trainee-role');
        $this->insertGym('Gym A', 'gym-a', $ownerA->json('data.user.id'));
        $this->insertGym('Gym B', 'gym-b', $ownerB->json('data.user.id'));
        $studentToken = $this->register('student@example.com', 'trainee-role')->json('data.token');

        $ticketId = $this->actingWithToken($studentToken)->postJson('/api/v1/me/gyms/join', ['gym_code' => 'gym-a'])->assertCreated()->json('data.id');
        $this->actingWithToken($studentToken)->postJson('/api/v1/me/gyms/join', ['gym_code' => 'gym-b'])->assertStatus(400)
            ->assertJsonFragment(['message' => 'Ya tienes la solicitud '.sprintf('TK-%06d', $ticketId).' esperando respuesta del entrenador']);

        $this->actingWithToken($ownerA->json('data.token'))->postJson('/api/v1/tickets/'.$ticketId.'/approve')->assertOk();
        $this->actingWithToken($studentToken)->postJson('/api/v1/me/gyms/join', ['gym_code' => 'gym-b'])->assertStatus(400)
            ->assertJsonFragment(['message' => 'Ya perteneces a un gimnasio. Por ahora solo puedes estar en uno']);
    }

    public function test_trainer_can_create_only_one_gym_and_admin_has_no_limit(): void
    {
        $register = $this->register('coach@example.com', 'trainer-role');
        $adminToken = $this->login(static::adminEmail);
        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$register->json('data.tickets.0.id').'/approve')->assertOk();
        $coachId = $register->json('data.user.id');
        $coachToken = $register->json('data.token');

        $this->actingWithToken($coachToken)->postJson('/api/v1/entities/gym', ['name' => 'Coach Gym', 'slug' => 'coach-gym'])->assertCreated();
        $this->actingWithToken($coachToken)->postJson('/api/v1/entities/gym', ['name' => 'Coach Gym 2', 'slug' => 'coach-gym-2'])->assertStatus(400)
            ->assertJsonFragment(['message' => 'Por ahora cada entrenador puede tener un solo gimnasio']);

        $this->actingWithToken($adminToken)->postJson('/api/v1/entities/gym', ['name' => 'Admin Gym 1', 'slug' => 'admin-gym-1'])->assertCreated();
        $adminGymId = $this->actingWithToken($adminToken)->postJson('/api/v1/entities/gym', ['name' => 'Admin Gym 2', 'slug' => 'admin-gym-2'])
            ->assertCreated()->json('data.id');
        $this->actingWithToken($adminToken)->postJson('/api/v1/entities/gym', ['name' => 'Para el coach', 'slug' => 'para-el-coach', 'owner_id' => $coachId])
            ->assertStatus(400);
        $this->actingWithToken($adminToken)->putJson('/api/v1/entities/gym/'.$adminGymId, ['name' => 'Admin Gym 2', 'slug' => 'admin-gym-2', 'owner_id' => $coachId])
            ->assertStatus(400);
        $this->assertSame(1, DB::table('gym_entities')->where('owner_id', $coachId)->count());
    }

    private function insertGym(string $name, string $slug, int $ownerId): int
    {
        return DB::table('gym_entities')->insertGetId([
            'name' => $name, 'slug' => $slug, 'owner_id' => $ownerId, 'status' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
