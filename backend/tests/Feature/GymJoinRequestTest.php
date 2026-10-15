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

    public function test_trainer_can_create_up_to_two_gyms_and_admin_has_no_limit(): void
    {
        $register = $this->register('coach@example.com', 'trainer-role');
        $adminToken = $this->login(static::adminEmail);
        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$register->json('data.tickets.0.id').'/approve')->assertOk();
        $coachId = $register->json('data.user.id');
        $coachToken = $register->json('data.token');

        $this->actingWithToken($coachToken)->postJson('/api/v1/entities/gym', ['name' => 'Coach Gym Mañana', 'slug' => 'coach-gym-am'])->assertCreated();
        $this->actingWithToken($coachToken)->postJson('/api/v1/entities/gym', ['name' => 'Coach Gym Tarde', 'slug' => 'coach-gym-pm'])->assertCreated();
        $this->actingWithToken($coachToken)->postJson('/api/v1/entities/gym', ['name' => 'Coach Gym Noche', 'slug' => 'coach-gym-night'])->assertStatus(400)
            ->assertJsonFragment(['message' => 'Cada entrenador puede tener hasta 2 gimnasios']);

        $this->actingWithToken($adminToken)->postJson('/api/v1/entities/gym', ['name' => 'Admin Gym 1', 'slug' => 'admin-gym-1'])->assertCreated();
        $adminGymId = $this->actingWithToken($adminToken)->postJson('/api/v1/entities/gym', ['name' => 'Admin Gym 2', 'slug' => 'admin-gym-2'])
            ->assertCreated()->json('data.id');
        $this->actingWithToken($adminToken)->postJson('/api/v1/entities/gym', ['name' => 'Admin Gym 3', 'slug' => 'admin-gym-3'])->assertCreated();
        $this->actingWithToken($adminToken)->postJson('/api/v1/entities/gym', ['name' => 'Para el coach', 'slug' => 'para-el-coach', 'owner_id' => $coachId])
            ->assertStatus(400);
        $this->actingWithToken($adminToken)->putJson('/api/v1/entities/gym/'.$adminGymId, ['name' => 'Admin Gym 2', 'slug' => 'admin-gym-2', 'owner_id' => $coachId])
            ->assertStatus(400);
        $this->assertSame(2, DB::table('gym_entities')->where('owner_id', $coachId)->count());
    }

    public function test_a_gym_takes_up_to_twenty_students_but_the_base_gym_has_no_limit(): void
    {
        $owner = $this->register('owner@example.com', 'trainee-role');
        $gymId = $this->insertGym('Gym Lleno', 'gym-lleno', $owner->json('data.user.id'));
        $student = $this->register('student@example.com', 'trainee-role');
        $ticketId = $this->actingWithToken($student->json('data.token'))->postJson('/api/v1/me/gyms/join', ['gym_code' => 'gym-lleno'])
            ->assertCreated()->json('data.id');

        $baseCount = DB::table('gym_users')->where('gym_id', DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->value('id'))->count();
        foreach (range(1, 20) as $index) {
            $memberId = $this->register('alumno'.$index.'@example.com', 'trainee-role')->json('data.user.id');
            DB::table('gym_users')->insert(['gym_id' => $gymId, 'user_id' => $memberId, 'created_at' => now(), 'updated_at' => now()]);
        }
        $baseCount += 20;

        // la solicitud que ya estaba pendiente no puede pasar el cupo, y una nueva ni se crea
        $this->actingWithToken($owner->json('data.token'))->postJson('/api/v1/tickets/'.$ticketId.'/approve')->assertStatus(400)
            ->assertJsonFragment(['message' => 'Gym Lleno ya tiene 20 alumnos, el maximo por gimnasio']);
        $this->assertSame(20, DB::table('gym_users')->where('gym_id', $gymId)->count());
        $this->assertSame('pending', $this->actingWithToken($student->json('data.token'))->getJson('/api/v1/me/tickets')->json('data.0.state'));
        $lateToken = $this->register('late@example.com', 'trainee-role')->json('data.token');
        $this->actingWithToken($lateToken)->postJson('/api/v1/me/gyms/join', ['gym_code' => 'gym-lleno'])->assertStatus(400)
            ->assertJsonFragment(['message' => 'Gym Lleno ya tiene 20 alumnos, el maximo por gimnasio']);
        $this->assertSame($baseCount + 1, DB::table('gym_users')->where('gym_id', DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->value('id'))->count());
    }

    public function test_roles_permits_and_resources_are_admin_only(): void
    {
        $traineeToken = $this->register('student@example.com', 'trainee-role')->json('data.token');
        $roleId = DB::table('roles')->where('slug', 'trainee-role')->value('id');

        $this->actingWithToken($traineeToken)->getJson('/api/v1/roles')->assertForbidden();
        $this->actingWithToken($traineeToken)->postJson('/api/v1/roles', ['name' => 'Hacker', 'slug' => 'hacker-role'])->assertForbidden();
        $this->actingWithToken($traineeToken)->getJson('/api/v1/permits')->assertForbidden();
        $this->actingWithToken($traineeToken)->postJson('/api/v1/permits', ['name' => 'Todo el poder', 'slug' => 'todo-el-poder', 'roleId' => $roleId])->assertForbidden();
        $this->actingWithToken($traineeToken)->getJson('/api/v1/resources')->assertForbidden();
        $this->actingWithToken($traineeToken)->postJson('/api/v1/resources', ['name' => 'Imagen', 'slug' => 'imagen-ajena', 'kind' => 1, 'url' => 'x.png', 'status' => 100, 'model_id' => 10, 'owner_id' => 1])->assertForbidden();
        $this->actingWithToken($traineeToken)->getJson('/api/v1/resources/1')->assertForbidden();
        $this->assertFalse(DB::table('roles')->where('slug', 'hacker-role')->exists());
        $this->assertFalse(DB::table('permits')->where('slug', 'todo-el-poder')->exists());

        // el permiso de configuracion no tiene rol: listar los permisos ya no se rompe
        $adminToken = $this->login(static::adminEmail);
        $permitList = $this->actingWithToken($adminToken)->getJson('/api/v1/permits')->assertOk()->json('data');
        $this->assertNull(collect($permitList)->firstWhere('slug', 'allow-config')['roleId']);
        $this->actingWithToken($adminToken)->getJson('/api/v1/roles')->assertOk();
        $this->actingWithToken($adminToken)->getJson('/api/v1/resources')->assertOk();
    }

    private function insertGym(string $name, string $slug, int $ownerId): int
    {
        return DB::table('gym_entities')->insertGetId([
            'name' => $name, 'slug' => $slug, 'owner_id' => $ownerId, 'status' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
