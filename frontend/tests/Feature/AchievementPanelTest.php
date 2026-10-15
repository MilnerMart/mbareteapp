<?php

namespace Tests\Feature;

use App\Support\ActivityLabels;
use App\Support\NotificationLabels;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AchievementPanelTest extends TestCase
{
    private const authSession = ['auth_user' => ['id' => 1, 'permits' => []], 'auth_token' => 'token'];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(fn(Request $request) => Http::response(['success' => true, 'data' => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/user/1') ? [
            'id' => 1, 'name' => 'Ana', 'last_name' => 'Paz', 'age' => 25, 'height' => 165, 'weight' => 60,
            'favorites' => [], 'progress' => ['rank' => 'novice'],
            'achievements' => [
                ['slug' => 'kilos-15000', 'metric' => 'kilos', 'goal' => 15000, 'current' => 15000, 'isUnlocked' => true, 'unlockedAt' => '2026-10-13T10:00:00-03:00'],
                ['slug' => 'streak-30', 'metric' => 'bestStreak', 'goal' => 30, 'current' => 12, 'isUnlocked' => false, 'unlockedAt' => null],
            ],
        ] : []]));
    }

    public function test_profile_shows_the_trophy_with_unlocked_and_pending_achievements(): void
    {
        $this->withSession(self::authSession)->get('/profile/1')
            ->assertOk()
            ->assertSee('fa-trophy', false)
            ->assertSee('1/2')
            ->assertSeeInOrder(['Kilos levantados', 'Superhombre', '¡Ahora podes alzar el peso de un bondi!', 'Lo lograste el 13/10/2026',
                'Constancia', 'Maquina constante', '12 dias de 30 dias'])
            ->assertSee('width: 40%', false)
            ->assertDontSee('Modal.getOrCreateInstance', false);

        $this->withSession(self::authSession)->get('/profile/1?logros=1')->assertOk()->assertSee('Modal.getOrCreateInstance', false);
    }

    public function test_achievement_notice_and_activity_read_well(): void
    {
        $this->withSession(self::authSession);
        $notification = ['id' => 5, 'type' => 'achievement', 'refId' => 1, 'isRead' => false, 'createdAt' => '2026-10-13T10:00:00-03:00',
            'data' => ['achievement' => 'streak-30']];

        $item = NotificationLabels::buildItem($notification);

        $this->assertSame(['fa-solid fa-gears', '¡Nuevo logro! Maquina constante'], [$item['icon'], $item['text']]);
        $this->assertSame(route('user.profile', ['id' => 1, 'logros' => 1]), NotificationLabels::targetUrl($notification));
        $this->assertSame('Desbloqueaste el logro «Maquina constante»', ActivityLabels::buildItem([
            'action' => 'achievement', 'model' => 'user', 'entityId' => 1, 'data' => ['name' => 'Ana', 'achievement' => 'streak-30'],
        ], true)['text']);
    }

    public function test_female_users_get_their_own_kilos_achievements(): void
    {
        $this->withSession(self::authSession);
        $notification = ['id' => 6, 'type' => 'achievement', 'refId' => 1, 'isRead' => false, 'createdAt' => '2026-10-13T10:00:00-03:00',
            'data' => ['achievement' => 'kilos-3000', 'gender' => 'female']];

        $item = NotificationLabels::buildItem($notification);

        $this->assertSame(['fa-solid fa-truck-pickup', '¡Nuevo logro! Fuerza de leona'], [$item['icon'], $item['text']]);
        $this->assertSame('Desbloqueaste el logro «Supermujer»', ActivityLabels::buildItem([
            'action' => 'achievement', 'model' => 'user', 'entityId' => 1, 'data' => ['name' => 'Ana', 'achievement' => 'kilos-15000', 'gender' => 'female'],
        ], true)['text']);
        $this->assertSame('¡Nuevo logro! Fuerza de hipopotamo', NotificationLabels::buildItem(['data' => ['achievement' => 'kilos-3000', 'gender' => 'unspecified']] + $notification)['text']);
        $this->assertSame('¡Nuevo logro! Maquina constante', NotificationLabels::buildItem(['data' => ['achievement' => 'streak-30', 'gender' => 'female']] + $notification)['text']);
    }
}
