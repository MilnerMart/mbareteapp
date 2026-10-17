<?php

namespace Tests\Feature;

use App\Models\Gym;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\InteractsWithMuscleApi;
use Tests\TestCase;

class UserDeactivationTest extends TestCase
{
    use InteractsWithMuscleApi;

    public function test_admin_deactivates_a_user_with_a_reason_that_stays_in_the_detail(): void
    {
        $student = $this->register('student@example.com', 'trainee-role');
        [$studentId, $studentToken] = [$student->json('data.user.id'), $student->json('data.token')];
        $baseGymId = DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->value('id');
        DB::table('gym_users')->where('gym_id', $baseGymId)->where('user_id', $studentId)->update(['is_public' => true]);
        $adminToken = $this->login(static::adminEmail);

        $this->actingWithToken($adminToken)->getJson('/api/v1/user/'.$studentId.'/detail')->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.deactivation', null)
            ->assertJsonPath('data.can_deactivate', true);

        $this->actingWithToken($adminToken)->postJson('/api/v1/user/'.$studentId.'/deactivate', ['reason' => '  Cuenta duplicada  '])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.deactivation.reason', 'Cuenta duplicada')
            ->assertJsonPath('data.deactivation.byName', 'Admin MuscleApp');

        $this->assertSame(1, (int)DB::table('users')->where('id', $studentId)->value('status'));
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $studentId)->count());
        $this->assertTrue(DB::table('entity_logs')->where('entity_id', $studentId)->where('action', 210)->exists());

        $this->actingWithToken($adminToken)->getJson('/api/v1/user/'.$studentId.'/detail')->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.can_deactivate', false)
            ->assertJsonPath('data.deactivation.reason', 'Cuenta duplicada');

        // sigue en el gimnasio para que el admin vea su ficha, pero no se muestra ni compite
        $this->actingWithToken($adminToken)->getJson('/api/v1/entities/gym/'.$baseGymId.'/users')->assertOk()
            ->assertJsonPath('data.0.is_active', false);
        $otherToken = $this->register('other@example.com', 'trainee-role')->json('data.token');
        $this->actingWithToken($otherToken)->getJson('/api/v1/me/gyms')->assertOk()->assertJsonCount(0, 'data.0.publicStudents');
        $this->actingWithToken($studentToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => 'student@example.com', 'password' => static::password])->assertForbidden();
        $this->actingWithToken($adminToken)->postJson('/api/v1/user/'.$studentId.'/deactivate', ['reason' => 'Otra vez'])->assertStatus(400);
    }

    public function test_see_user_detail_only_opens_the_detail(): void
    {
        $viewer = $this->register('viewer@example.com', 'trainee-role');
        [$viewerId, $viewerToken] = [$viewer->json('data.user.id'), $viewer->json('data.token')];
        $studentId = $this->register('student@example.com', 'trainee-role')->json('data.user.id');
        $this->giveRolePermits($viewerId, ['see-user-detail']);

        $this->actingWithToken($viewerToken)->getJson('/api/v1/user/'.$studentId.'/detail')->assertOk()
            ->assertJsonPath('data.can_deactivate', false);
        $this->actingWithToken($viewerToken)->postJson('/api/v1/user/'.$studentId.'/deactivate', ['reason' => 'Cuenta duplicada'])
            ->assertForbidden();
        $this->assertSame(100, (int)DB::table('users')->where('id', $studentId)->value('status'));
    }

    public function test_see_user_detail_and_deactivate_permits_together_deactivate_but_not_an_admin(): void
    {
        $moderator = $this->register('moderator@example.com', 'trainee-role');
        [$moderatorId, $moderatorToken] = [$moderator->json('data.user.id'), $moderator->json('data.token')];
        $studentId = $this->register('student@example.com', 'trainee-role')->json('data.user.id');
        $adminId = DB::table('users')->where('email', static::adminEmail)->value('id');
        $roleId = $this->giveRolePermits($moderatorId, ['deactivate-user']);
        $this->assertSame(2, DB::table('permits')->whereIn('slug', ['see-user-detail', 'deactivate-user'])->whereNull('role_id')->count());

        // solo con el permiso de baja no alcanza: tambien tiene que poder ver la ficha
        $this->actingWithToken($moderatorToken)->getJson('/api/v1/user/'.$studentId.'/detail')->assertForbidden();
        $this->actingWithToken($moderatorToken)->postJson('/api/v1/user/'.$studentId.'/deactivate', ['reason' => 'Cuenta duplicada'])
            ->assertForbidden();
        DB::table('roles')->where('id', $roleId)->update(['data' => json_encode(['rolePermits' => ['see-user-detail', 'deactivate-user']])]);

        $this->actingWithToken($moderatorToken)->getJson('/api/v1/user/'.$studentId.'/detail')->assertOk()
            ->assertJsonPath('data.can_deactivate', true);
        $this->actingWithToken($moderatorToken)->getJson('/api/v1/user/'.$adminId.'/detail')->assertOk()
            ->assertJsonPath('data.can_deactivate', false);
        $this->actingWithToken($moderatorToken)->postJson('/api/v1/user/'.$adminId.'/deactivate', ['reason' => 'Quiero ser admin'])
            ->assertForbidden();
        $this->actingWithToken($moderatorToken)->getJson('/api/v1/user/'.$studentId.'/logs')->assertForbidden();

        $this->actingWithToken($moderatorToken)->postJson('/api/v1/user/'.$studentId.'/deactivate', ['reason' => 'Cuenta duplicada'])
            ->assertOk()->assertJsonPath('data.deactivation.byName', 'Test User');
        $this->assertSame(1, (int)DB::table('users')->where('id', $studentId)->value('status'));
        $this->assertSame(100, (int)DB::table('users')->where('id', $adminId)->value('status'));
    }

    public function test_deactivation_needs_a_reason_the_admin_and_another_user(): void
    {
        $student = $this->register('student@example.com', 'trainee-role');
        [$studentId, $studentToken] = [$student->json('data.user.id'), $student->json('data.token')];
        $otherId = $this->register('other@example.com', 'trainee-role')->json('data.user.id');
        $adminToken = $this->login(static::adminEmail);
        $adminId = DB::table('users')->where('email', static::adminEmail)->value('id');

        $this->actingWithToken($adminToken)->postJson('/api/v1/user/'.$studentId.'/deactivate', ['reason' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->actingWithToken($adminToken)->postJson('/api/v1/user/'.$studentId.'/deactivate', ['reason' => 'mal'])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->actingWithToken($adminToken)->postJson('/api/v1/user/'.$adminId.'/deactivate', ['reason' => 'Me doy de baja'])
            ->assertStatus(400);
        $this->actingWithToken($studentToken)->postJson('/api/v1/user/'.$otherId.'/deactivate', ['reason' => 'No me cae bien'])
            ->assertForbidden();
        $this->assertSame(0, DB::table('users')->where('status', 1)->count());
    }

    private function giveRolePermits(int $userId, array $permitSlugList): int
    {
        $roleId = DB::table('roles')->insertGetId(['name' => 'Moderador', 'slug' => 'moderator-role-'.$userId, 'status' => 100,
            'data' => json_encode(['rolePermits' => $permitSlugList]), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_roles')->insert(['user_id' => $userId, 'role_id' => $roleId]);
        return $roleId;
    }
}
