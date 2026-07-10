<?php

namespace Tests\Feature;

use App\Services\ApiClientService;
use Mockery\MockInterface;
use Tests\TestCase;

class FrontendAuthMiddlewareTest extends TestCase
{
    public function test_catalog_pages_are_public(): void
    {
        $this->mock(ApiClientService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getMuscles')->once()->andReturn([]);
        });

        $this->get('/muscle')
            ->assertOk();
    }

    public function test_profile_pages_redirect_guests_to_login(): void
    {
        $this->get('/profile/1')
            ->assertRedirect(route('user.login'));
    }
}
