<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    private const adminEmail = 'admin@example.com', password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();
        // los modelos usan la conexion muscleDb (mysql): en el test comparte el sqlite en memoria
        config(['database.connections.muscleDb' => config('database.connections.sqlite')]);
        DB::purge('muscleDb');
        DB::connection('muscleDb')->setPdo(DB::connection()->getPdo());
        putenv('ADMIN_EMAIL='.self::adminEmail);
        putenv('ADMIN_PASSWORD='.self::password);
        Artisan::call('migrate', ['--force' => true]);
    }

    public function test_trainer_signup_registers_trainee_and_admin_approves_ticket(): void
    {
        $register = $this->register('coach@example.com', 'trainer-role');
        $register->assertCreated()
            ->assertJsonPath('data.tickets.0.type', 'trainer-request')
            ->assertJsonPath('data.tickets.0.state', 'pending');
        $this->assertSame(['trainee-role'], $register->json('data.user.roles'));
        $ticketId = $register->json('data.tickets.0.id');
        $coachToken = $register->json('data.token');

        $this->actingWithToken($coachToken)->getJson('/api/v1/me/tickets')
            ->assertOk()->assertJsonPath('data.0.number', sprintf('TK-%06d', $ticketId));
        $this->actingWithToken($coachToken)->postJson('/api/v1/tickets/'.$ticketId.'/approve')->assertForbidden();

        $adminToken = $this->login(self::adminEmail);
        $this->actingWithToken($adminToken)->getJson('/api/v1/tickets')
            ->assertOk()->assertJsonPath('data.0.id', $ticketId);
        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$ticketId.'/approve')
            ->assertOk()->assertJsonPath('data.state', 'approved');
        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$ticketId.'/reject')->assertStatus(400);

        $this->actingWithToken($coachToken)->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonFragment(['create-gym-entity']);
    }

    public function test_gym_code_signup_waits_for_owner_approval(): void
    {
        $owner = User::where('email', self::adminEmail)->firstOrFail();
        $trainer = $this->register('owner@example.com', 'trainee-role');
        $trainerId = $trainer->json('data.user.id');
        DB::table('gym_entities')->insert([
            'name' => 'Coach Gym', 'slug' => 'coach-gym', 'owner_id' => $trainerId, 'status' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $gymId = DB::table('gym_entities')->where('slug', 'coach-gym')->value('id');

        $student = $this->register('student@example.com', 'trainee-role', 'coach-gym');
        $student->assertCreated()->assertJsonPath('data.tickets.0.type', 'gym-join')
            ->assertJsonPath('data.tickets.0.refs.gym.name', 'Coach Gym');
        $studentId = $student->json('data.user.id');
        $ticketId = $student->json('data.tickets.0.id');
        $this->assertFalse(DB::table('gym_users')->where('gym_id', $gymId)->where('user_id', $studentId)->exists());
        $baseGymId = DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->value('id');
        $this->assertTrue(DB::table('gym_users')->where('gym_id', $baseGymId)->where('user_id', $studentId)->exists());

        // otro alumno no ve la bandeja del dueño ni puede pedir todas
        $studentToken = $student->json('data.token');
        $this->actingWithToken($studentToken)->getJson('/api/v1/tickets')->assertOk()->assertJsonCount(0, 'data');
        $this->actingWithToken($studentToken)->getJson('/api/v1/tickets?scope=all')->assertForbidden();

        $ownerToken = $trainer->json('data.token');
        $this->actingWithToken($ownerToken)->getJson('/api/v1/tickets')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ticketId);
        $this->actingWithToken($ownerToken)->postJson('/api/v1/tickets/'.$ticketId.'/approve', ['note' => 'Bienvenido'])
            ->assertOk()->assertJsonPath('data.resolutionNote', 'Bienvenido');
        $this->assertTrue(DB::table('gym_users')->where('gym_id', $gymId)->where('user_id', $studentId)->exists());

        $adminToken = $this->login($owner->email);
        $this->actingWithToken($adminToken)->getJson('/api/v1/tickets?scope=all&state=all')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_without_gyms_sees_trainer_requests_as_own_and_resolves_any_ticket(): void
    {
        $secondAdmin = $this->register('admin2@example.com', 'trainee-role');
        $secondAdminId = $secondAdmin->json('data.user.id');
        DB::table('user_roles')->insert([
            'user_id' => $secondAdminId,
            'role_id' => DB::table('roles')->where('slug', 'admin-role')->value('id'),
        ]);
        $ownerId = $this->register('owner2@example.com', 'trainee-role')->json('data.user.id');
        DB::table('gym_entities')->insert([
            'name' => 'Other Gym', 'slug' => 'other-gym', 'owner_id' => $ownerId, 'status' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $coachTicketId = $this->register('coach2@example.com', 'trainer-role')->json('data.tickets.0.id');
        $joinTicketId = $this->register('student2@example.com', 'trainee-role', 'other-gym')->json('data.tickets.0.id');

        $adminToken = $secondAdmin->json('data.token');
        $this->actingWithToken($adminToken)->getJson('/api/v1/tickets')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $coachTicketId);
        $this->actingWithToken($adminToken)->getJson('/api/v1/tickets?scope=all')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$joinTicketId.'/approve')
            ->assertOk()->assertJsonPath('data.state', 'approved');
    }

    public function test_requester_can_resubmit_rejected_ticket_once(): void
    {
        $coach = $this->register('coach3@example.com', 'trainer-role');
        $ticketId = $coach->json('data.tickets.0.id');
        $coachToken = $coach->json('data.token');
        $adminToken = $this->login(self::adminEmail);

        $this->actingWithToken($coachToken)->postJson('/api/v1/tickets/'.$ticketId.'/resubmit', ['note' => 'Tengo certificado'])
            ->assertStatus(400);
        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$ticketId.'/reject', ['note' => 'Falta certificado'])
            ->assertOk();

        $this->actingWithToken($coachToken)->getJson('/api/v1/tickets/'.$ticketId)
            ->assertOk()->assertJsonPath('data.resolutionNote', 'Falta certificado')->assertJsonPath('data.canResubmit', true);
        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$ticketId.'/resubmit', ['note' => 'No soy yo'])
            ->assertForbidden();
        $this->actingWithToken($coachToken)->postJson('/api/v1/tickets/'.$ticketId.'/resubmit', ['note' => ''])
            ->assertUnprocessable();
        $this->actingWithToken($coachToken)->postJson('/api/v1/tickets/'.$ticketId.'/resubmit', ['note' => 'Ya lo adjunte'])
            ->assertOk()
            ->assertJsonPath('data.state', 'pending')
            ->assertJsonPath('data.number', sprintf('TK-%06d', $ticketId))
            ->assertJsonPath('data.requesterNote', 'Ya lo adjunte')
            ->assertJsonPath('data.canResubmit', false)
            ->assertJsonPath('data.history.0.action', 'rejected')
            ->assertJsonPath('data.history.0.note', 'Falta certificado')
            ->assertJsonPath('data.history.1.action', 'resubmitted');

        $this->actingWithToken($adminToken)->getJson('/api/v1/tickets')
            ->assertOk()->assertJsonPath('data.0.id', $ticketId);
        $this->actingWithToken($adminToken)->postJson('/api/v1/tickets/'.$ticketId.'/reject')->assertOk();
        $this->actingWithToken($coachToken)->postJson('/api/v1/tickets/'.$ticketId.'/resubmit', ['note' => 'Otra vez'])
            ->assertStatus(400);
    }

    public function test_approved_trainer_still_sees_and_hides_from_base_gym(): void
    {
        $coach = $this->register('coach4@example.com', 'trainer-role');
        $coachToken = $coach->json('data.token');
        $this->actingWithToken($this->login(self::adminEmail))
            ->postJson('/api/v1/tickets/'.$coach->json('data.tickets.0.id').'/approve')->assertOk();

        $me = $this->actingWithToken($coachToken)->getJson('/api/v1/auth/me')->assertOk();
        $this->assertContains('belongs-to-gym', $me->json('data.permits'));
        $this->assertContains('create-gym-entity', $me->json('data.permits'));

        $baseGymId = DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->value('id');
        $this->actingWithToken($coachToken)->getJson('/api/v1/me/gyms')
            ->assertOk()->assertJsonPath('data.0.id', $baseGymId)->assertJsonPath('data.0.isPublic', false)
            ->assertJsonMissingPath('data.0.alumnsCount');
        $this->actingWithToken($this->login(self::adminEmail))->getJson('/api/v1/me/gyms')
            ->assertOk()->assertJsonPath('data.0.id', $baseGymId)->assertJsonPath('data.0.alumnsCount', 2);
        $this->actingWithToken($coachToken)->putJson('/api/v1/me/gyms/'.$baseGymId.'/visibility', ['is_public' => true])
            ->assertOk()->assertJsonPath('data.isPublic', true);
        $this->actingWithToken($coachToken)->putJson('/api/v1/me/gyms/'.$baseGymId.'/visibility', ['is_public' => false])
            ->assertOk()->assertJsonPath('data.isPublic', false);
    }

    public function test_signup_without_code_joins_base_gym_without_ticket(): void
    {
        $register = $this->register('plain@example.com', 'trainee-role');
        $register->assertCreated()->assertJsonCount(0, 'data.tickets');
        $baseGymId = DB::table('gym_entities')->where('slug', Gym::baseGymSlug)->value('id');
        $this->assertTrue(DB::table('gym_users')->where('gym_id', $baseGymId)
            ->where('user_id', $register->json('data.user.id'))->exists());
    }

    private function register(string $email, string $roleSlug, ?string $gymCode = null)
    {
        return $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => self::password,
            'password_confirmation' => self::password,
            'age' => 30,
            'height' => 175,
            'weight' => 80,
            'role_id' => DB::table('roles')->where('slug', $roleSlug)->value('id'),
            'gym_code' => $gymCode,
        ]);
    }

    /**
     * El guard de sanctum recuerda al usuario entre requests del mismo test: se olvida al cambiar de token.
     */
    private function actingWithToken(string $token): self
    {
        $this->app['auth']->forgetGuards();
        return $this->withToken($token);
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => self::password])
            ->json('data.token');
    }
}
