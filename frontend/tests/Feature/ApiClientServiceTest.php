<?php

namespace Tests\Feature;

use App\Services\ApiClientService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiClientServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.backend.url' => 'https://backend.test/api/v1']);
    }

    public function test_catalog_getters_call_public_backend_endpoints(): void
    {
        Http::fake([
            'https://backend.test/api/v1/muscle' => Http::response(['data' => [['id' => 1]]]),
            'https://backend.test/api/v1/exercise' => Http::response(['data' => [['id' => 2]]]),
            'https://backend.test/api/v1/exercise/group/3' => Http::response(['data' => [['id' => 4]]]),
            'https://backend.test/api/v1/exercise/5/resource' => Http::response(['data' => [['id' => 6]]]),
        ]);

        $client = app(ApiClientService::class);

        $this->assertSame([['id' => 1]], $client->getMuscles());
        $this->assertSame([['id' => 2]], $client->getExercises());
        $this->assertSame([['id' => 4]], $client->getExerciseGroup(3));
        $this->assertSame([['id' => 6]], $client->getExerciseResources(5));

        Http::assertSentCount(4);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://backend.test/api/v1/muscle');
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://backend.test/api/v1/exercise');
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://backend.test/api/v1/exercise/group/3');
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === 'https://backend.test/api/v1/exercise/5/resource');
    }

    public function test_authentication_methods_use_backend_auth_endpoints(): void
    {
        Http::fake([
            'https://backend.test/api/v1/auth/login' => Http::response(['success' => true]),
            'https://backend.test/api/v1/auth/register' => Http::response(['success' => true]),
        ]);

        $client = app(ApiClientService::class);

        $this->assertTrue($client->login(['email' => 'test@example.com', 'password' => 'secret'])['success']);
        $this->assertTrue($client->register(['name' => 'Test'])['success']);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://backend.test/api/v1/auth/login');
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://backend.test/api/v1/auth/register');
    }

    public function test_authenticated_methods_send_session_bearer_token(): void
    {
        $this->withSession(['auth_token' => 'token-123']);

        Http::fake([
            'https://backend.test/api/v1/auth/me' => Http::response(['data' => ['id' => 9]]),
            'https://backend.test/api/v1/user/9' => Http::response(['data' => ['id' => 9]]),
            'https://backend.test/api/v1/auth/logout' => Http::response(['success' => true]),
        ]);

        $client = app(ApiClientService::class);

        $this->assertSame(['id' => 9], $client->me());
        $this->assertSame(['id' => 9], $client->getUser(9));
        $this->assertTrue($client->logout()['success']);

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer token-123'));
    }

    public function test_profile_image_upload_uses_profile_image_endpoint(): void
    {
        $this->withSession(['auth_token' => 'token-123']);

        Http::fake([
            'https://backend.test/api/v1/user/9/profile-image' => Http::response(['success' => true]),
        ]);

        $client = app(ApiClientService::class);

        $response = $client->updateProfileImage(9, UploadedFile::fake()->image('avatar.jpg'));

        $this->assertTrue($response['success']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://backend.test/api/v1/user/9/profile-image'
            && $request->hasHeader('Authorization', 'Bearer token-123'));
    }
}
