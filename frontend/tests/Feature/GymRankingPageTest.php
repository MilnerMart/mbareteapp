<?php

namespace Tests\Feature;

use App\Support\ActivityLabels;
use App\Support\NotificationLabels;
use App\Support\RankingLabels;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GymRankingPageTest extends TestCase
{
    private const studentSession = ['auth_user' => ['id' => 1, 'permits' => ['belongs-to-gym']], 'auth_token' => 'token'];

    private const trainerSession = ['auth_user' => ['id' => 9, 'permits' => ['create-gym-entity']], 'auth_token' => 'token'];

    private array $memberGym = ['id' => 2, 'name' => 'Coach Gym', 'isBase' => false, 'isPublic' => true, 'isRankingPublic' => true, 'publicStudents' => []];

    private array $requestList = [];

    private ?string $currentStatus = 'active';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $this->requestList[] = [$request->method(), $path, $request->data()];
            return match (true) {
                str_ends_with($path, '/entities/gym/2/ranking') && $request->method() === 'PUT' => Http::response(['success' => true, 'data' => ['isRankingPublic' => false]]),
                str_contains($path, '/entities/gym/2/seasons') => Http::response(['success' => true, 'data' => ['number' => 3, 'status' => 'active']]),
                str_ends_with($path, '/entities/gym/2/ranking') => Http::response(['success' => true, 'data' => $this->ranking()]),
                str_ends_with($path, '/entities/gym/2') => Http::response(['success' => true, 'data' => ['id' => 2, 'name' => 'Coach Gym', 'slug' => 'coach-gym', 'alumnsCount' => 3]]),
                str_ends_with($path, '/me/gyms') => Http::response(['success' => true, 'data' => [$this->memberGym]]),
                default => Http::response(['success' => true, 'data' => []]),
            };
        });
    }

    public function test_student_sees_the_season_podium_their_stats_and_the_recap_of_the_last_season(): void
    {
        $this->withSession(self::studentSession)->get('/my-gym?member=2&temporada=1')->assertOk()
            ->assertSeeInOrder(['Temporada 2', 'Del 10/10 al 08/11/2026', 'Quedan 25 dias', 'Premios de la temporada 1'])
            ->assertSeeInOrder(['Ben Paz', 'Subcampeon', '120 de fuerza', 'Ana Ruiz', 'Campeona', '340 de fuerza', '+30 hoy'])
            ->assertSeeInOrder(['Tu temporada', '#1 de 3', '340 de fuerza', '+30 hoy', 'Racha actual', '4 dias', 'Mejor racha', '5 dias', 'Rutinas completas', '10 de 12 (83%)'])
            ->assertSeeInOrder(['Tabla de posiciones', 'Ana Ruiz', 'Racha de 4 dias · 12.000 kg', '340', '+30 hoy', 'Ben Paz', 'Sin racha · 900 kg', '120', 'fuerza'])
            ->assertDontSee('Dias entrenados')
            ->assertDontSee(' pts')
            ->assertSee('Caro Gomez')
            ->assertSee('(tu)', false)
            ->assertSeeInOrder(['Premios', 'Lo mejor de los 30 dias de la temporada', 'Fuerza sobrehumana', 'Ana Ruiz', '12.000 kg levantados', 'Gran remontada', 'Ben Paz', '+40 de fuerza en la segunda mitad'])
            ->assertSee('Tu temporada 1')
            ->assertSee('seasonRecapModal')
            ->assertSee("getElementById('seasonRecapModal')).show()", false)
            ->assertDontSee('Mostrar el ranking a mis alumnos')
            ->assertDontSee('Guardar fechas');

        $this->assertContains(['GET', '/api/v1/entities/gym/2/ranking', ['season' => 1]], $this->requestList);
    }

    public function test_the_recap_goes_step_by_step_from_the_celebration_to_the_next_season(): void
    {
        $page = $this->withSession(self::studentSession)->get('/my-gym?member=2&temporada=1')->assertOk();
        // el festejo se ve primero y los demas pasos esperan ocultos
        $this->assertMatchesRegularExpression('/data-season-panel="festejo">/', $page->getContent());
        $this->assertSame(4, preg_match_all('/data-season-panel="(inicio|podio|premios|resumen)"\s+hidden/', $page->getContent()));
        $page
            ->assertSeeInOrder(['data-season-panel="festejo"', '¡Eres la Campeona!', 'Terminaste primera entre 3 alumnos con 340 de fuerza.',
                'dias entrenados', '12', 'mejor racha', '5 dias', 'kg levantados', '12.000', 'Ademas ganaste', 'Fuerza sobrehumana', 'Ver como fue la temporada'], false)
            ->assertSeeInOrder(['data-season-panel="inicio"', '¡Termino la temporada 1!', 'Del 10/09 al 09/10/2026 · 30 dias',
                'Asi se peleo la cima', 'La cima cambio de manos 1 vez', 'Ana', 'Ben', 'Dia 14: pasa al frente',
                'alumnos compitieron', '3', 'entrenamientos', '16', 'Ver el podio', 'Ir directo a mi resumen'], false)
            ->assertSeeInOrder(['data-season-panel="podio"', 'Tabla final', '3 alumnos', 'Caro Gomez', 'Ver los premios'], false)
            ->assertSeeInOrder(['data-season-panel="premios"', '¡Es tuyo!', 'Fuerza sobrehumana', 'Gran remontada', 'Ver como me fue'], false)
            ->assertSeeInOrder(['data-season-panel="resumen"', 'Tu temporada 1', 'Ganaste un premio', 'Fuerza sobrehumana', 'Tus numeros',
                'Temporada 2 · En curso', 'Ya estas sumando fuerza: cada entrenamiento cuenta.', 'Volver al ranking'], false)
            ->assertSee('Dia 14 · 23/09', false)
            ->assertSee('clip-path="url(#seasonRaceAvatar0)"', false)
            ->assertDontSee('Te faltaron');
    }

    public function test_a_student_off_the_podium_sees_how_far_it_was_and_how_they_did_against_the_last_season(): void
    {
        $user = fn(int $id) => ['id' => $id, 'name' => 'Alumno'.$id, 'last_name' => 'Test', 'profile_image_url' => null, 'gender' => null];
        $standingList = array_map(fn(int $position) => ['userId' => $position, 'position' => $position, 'points' => 1200 - 50 * $position, 'trainingDays' => 10,
            'bestStreak' => 3, 'kilos' => 1000, 'minutes' => 300, 'sessions' => 10, 'completedSessions' => 8, 'completionPercent' => 80, 'improvement' => 0,
            'isPodium' => $position <= 3, 'user' => $user($position)], range(1, 11));
        $season = ['number' => 3, 'startsOn' => '2026-09-15', 'endsOn' => '2026-10-14', 'status' => 'ended', 'days' => 30, 'daysLeft' => 0, 'daysToStart' => 0,
            'standings' => $standingList, 'awards' => [], 'race' => [],
            'viewer' => ['standing' => $standingList[9], 'awardSlugs' => [], 'reason' => null, 'previous' => ['number' => 2, 'position' => 12]]];
        $ranking = ['gym' => ['id' => 2, 'name' => 'Coach Gym', 'isRankingPublic' => true], 'canManage' => false, 'current' => null, 'recap' => $season,
            'openRecap' => true, 'closedSeasonNumbers' => [3, 2]];

        $recap = RankingLabels::buildPanel($ranking, fn(int $number) => '/temporada/'.$number)['recap'];

        $this->assertNull($recap['celebration']);
        $this->assertNull($recap['race']);
        $this->assertSame(['Te faltaron 350 de fuerza para subir al podio.', 'Podio: 1.050', 60.9, 91.3],
            [$recap['viewer']['gap']['text'], $recap['viewer']['gap']['markerLabel'], $recap['viewer']['gap']['fillPercent'], $recap['viewer']['gap']['markerPercent']]);
        $this->assertSame(['Subiste 2 puestos: en la temporada 2 fuiste #12', 'is-up'], [$recap['viewer']['previous']['text'], $recap['viewer']['previous']['toneClass']]);
        $this->assertSame([4, 5, 6, 7, 8, 9, 10, 11], array_column($recap['tableList'], 'position'));
        $this->assertSame([false, false, false, false, false, false, false, true], array_column($recap['tableList'], 'isCollapsed'));
        $this->assertSame('Ver los 11 alumnos', $recap['moreRowsLabel']);
        $this->assertSame(['El entrenador todavia no inicio una nueva temporada.', 'Ver como me fue'], [$recap['nextSeason']['text'], $recap['summaryButtonLabel']]);
    }

    public function test_hidden_ranking_is_not_requested_for_the_student(): void
    {
        $this->memberGym['isRankingPublic'] = false;

        $this->withSession(self::studentSession)->get('/my-gym?member=2')->assertOk()->assertDontSee('Ranking del gimnasio');

        $this->assertEmpty(array_filter($this->requestList, fn(array $request) => str_ends_with($request[1], '/ranking')));
    }

    public function test_trainer_sees_the_ranking_and_can_hide_it(): void
    {
        $this->withSession(self::trainerSession)->get('/gym/2')->assertOk()
            ->assertSee('Mostrar el ranking a mis alumnos')
            ->assertSee('Eres el entrenador: tus alumnos compiten y tu sigues el ranking.')
            ->assertSeeInOrder(['Fechas de la temporada 2', 'La temporada ya empezo: solo puedes cambiar la fecha de fin.'])
            ->assertSeeInOrder(['data-preset-days="7" data-preset-months="0">1 semana', 'data-preset-days="14" data-preset-months="0">2 semanas',
                'data-preset-days="0" data-preset-months="1">1 mes'], false)
            ->assertSee('data-confirm-word="confirmar"', false)
            ->assertSee('el ranking cambiara para tus alumnos')
            ->assertSee('name="starts_on" class="form-control" value="2026-10-10"', false)
            ->assertSee('required data-preset-start disabled', false)
            ->assertSee(route('gym.season.update', 2), false)
            ->assertDontSee("getElementById('seasonRecapModal')).show()", false);

        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession(self::trainerSession)
            ->from('/gym/2')->put('/gym/2/ranking', ['is_public' => 0])
            ->assertRedirect('/gym/2')->assertSessionHas('gym_saved', 'El ranking quedo oculto para tus alumnos.');
        $this->assertContains(['PUT', '/api/v1/entities/gym/2/ranking', ['is_public' => false]], $this->requestList);
    }

    public function test_without_a_season_the_trainer_starts_one_and_students_wait(): void
    {
        $this->currentStatus = null;

        $this->withSession(self::studentSession)->get('/my-gym?member=2')->assertOk()
            ->assertSee('Sin temporada en curso')
            ->assertSee('El entrenador todavia no inicio una nueva temporada.')
            ->assertSee('Premios de la temporada 1')
            ->assertDontSee('Iniciar temporada');

        $this->withSession(self::trainerSession)->get('/gym/2')->assertOk()
            ->assertSeeInOrder(['Nueva temporada', 'Empieza', 'Termina', 'Iniciar temporada'])
            ->assertSee(route('gym.season.start', 2), false)
            ->assertSee('data-confirm-submit', false)
            ->assertDontSee('data-confirm-word', false);

        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession(self::trainerSession)->from('/gym/2')
            ->post('/gym/2/seasons', ['starts_on' => '2026-10-20', 'ends_on' => '2026-10-10'])
            ->assertRedirect('/gym/2')->assertSessionHasErrors('ends_on');
        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession(self::trainerSession)->from('/gym/2')
            ->post('/gym/2/seasons', ['starts_on' => '2026-10-20', 'ends_on' => '2026-11-18'])
            ->assertRedirect('/gym/2')->assertSessionHas('gym_saved', 'Temporada 3 iniciada.');
        $this->assertContains(['POST', '/api/v1/entities/gym/2/seasons', ['starts_on' => '2026-10-20', 'ends_on' => '2026-11-18']], $this->requestList);
    }

    public function test_scheduled_season_waits_and_its_dates_can_still_move(): void
    {
        $this->currentStatus = 'scheduled';

        $this->withSession(self::studentSession)->get('/my-gym?member=2')->assertOk()
            ->assertSee('Empieza en 4 dias')
            ->assertSee('La temporada empieza el 18/10/2026. Desde ese dia cada entrenamiento suma.')
            ->assertDontSee('Tabla de posiciones');

        $this->withSession(self::trainerSession)->get('/gym/2')->assertOk()
            ->assertSee('Todavia no empezo: puedes cambiar las dos fechas.')
            ->assertDontSee('data-preset-start disabled', false)
            ->assertDontSee('data-confirm-word', false);

        // con el inicio bloqueado solo viaja el fin
        $this->withoutMiddleware(VerifyCsrfToken::class)->withSession(self::trainerSession)->from('/gym/2')
            ->put('/gym/2/seasons/current', ['ends_on' => '2026-11-30'])
            ->assertRedirect('/gym/2')->assertSessionHas('gym_saved', 'Fechas de la temporada 3 actualizadas.');
        $this->assertContains(['PUT', '/api/v1/entities/gym/2/seasons/current', ['ends_on' => '2026-11-30']], $this->requestList);
    }

    public function test_season_closed_notice_reads_well_and_opens_the_recap(): void
    {
        $this->withSession(self::studentSession);
        $notification = ['id' => 7, 'type' => 'season-closed', 'refId' => 2, 'isRead' => false, 'createdAt' => '2026-10-14T10:00:00-03:00',
            'data' => ['gymName' => 'Coach Gym', 'season' => 1, 'position' => 2]];

        $item = NotificationLabels::buildItem($notification);

        $this->assertSame(['Termino la temporada 1 de Coach Gym', 'Quedaste en el puesto 2. Mira tus premios'], [$item['text'], $item['detail']]);
        $this->assertSame(route('gym.member', ['member' => 2, 'temporada' => 1]), NotificationLabels::targetUrl($notification));
        $this->assertSame(route('gym.show', [2, 'temporada' => 1]), NotificationLabels::targetUrl(['data' => ['season' => 1, 'isOwner' => true]] + $notification));
    }

    public function test_ranking_events_read_well_in_the_calendar_and_in_the_admin_log(): void
    {
        $result = ['action' => 'season-result', 'model' => 'gym', 'entityId' => 2,
            'data' => ['name' => 'Coach Gym', 'season' => 1, 'position' => 2, 'points' => 340, 'awards' => ['strength', 'streak']]];

        $ownItem = ActivityLabels::buildItem($result, true);
        $this->assertSame(['Terminaste en el puesto 2 la temporada 1 del gimnasio «Coach Gym»', '340 de fuerza · Premios: Fuerza sobrehumana, Racha imparable',
            'fa-solid fa-medal', true], [$ownItem['text'], $ownItem['note'], $ownItem['icon'], $ownItem['isHighlight']]);
        $this->assertSame('Termino en el puesto 2 la temporada 1 del gimnasio «Coach Gym»', ActivityLabels::buildItem($result)['text']);

        $this->assertSame('Termino la temporada 1 del gimnasio «Coach Gym» con 5 participantes', ActivityLabels::buildItem(['action' => 'season-result',
            'model' => 'gym', 'entityId' => 2, 'data' => ['name' => 'Coach Gym', 'season' => 1, 'isOwner' => true, 'participantCount' => 5]])['text']);
        $started = ['action' => 'season-started', 'model' => 'gym', 'entityId' => 2,
            'data' => ['name' => 'Coach Gym', 'season' => 2, 'startsOn' => '2026-10-14', 'endsOn' => '2026-10-27']];
        $this->assertSame('Iniciaste la temporada 2 del gimnasio «Coach Gym» (del 14/10 al 27/10/2026)', ActivityLabels::buildItem($started, true)['text']);
        $this->assertSame('Inicio la temporada 2 del gimnasio «Coach Gym» (del 14/10 al 27/10/2026)', ActivityLabels::buildItem($started)['text']);
        $this->assertSame('Cambio las fechas de la temporada 2 del gimnasio «Coach Gym» (del 14/10 al 27/10/2026)',
            ActivityLabels::buildItem(['action' => 'season-updated'] + $started)['text']);
    }

    private function ranking(): array
    {
        $ana = ['id' => 1, 'name' => 'Ana', 'last_name' => 'Ruiz', 'profile_image_url' => null, 'gender' => 'female'];
        $ben = ['id' => 2, 'name' => 'Ben', 'last_name' => 'Paz', 'profile_image_url' => null, 'gender' => 'male'];
        $caro = ['id' => 3, 'name' => 'Caro', 'last_name' => 'Gomez', 'profile_image_url' => null, 'gender' => null];
        $anaStanding = ['userId' => 1, 'position' => 1, 'points' => 340, 'trainingDays' => 12, 'bestStreak' => 5, 'currentStreak' => 4, 'todayPoints' => 30, 'kilos' => 12000, 'minutes' => 400,
            'sessions' => 12, 'completedSessions' => 10, 'completionPercent' => 83, 'improvement' => -20, 'isPodium' => true, 'user' => $ana];
        $standingList = [
            $anaStanding,
            ['userId' => 2, 'position' => 2, 'points' => 120, 'trainingDays' => 4, 'bestStreak' => 2, 'currentStreak' => 0, 'todayPoints' => 0, 'kilos' => 900, 'minutes' => 80,
                'sessions' => 4, 'completedSessions' => 1, 'completionPercent' => 25, 'improvement' => 40, 'isPodium' => true, 'user' => $ben],
            ['userId' => 3, 'position' => 3, 'points' => 0, 'trainingDays' => 0, 'bestStreak' => 0, 'kilos' => 0, 'minutes' => 0,
                'sessions' => 0, 'completedSessions' => 0, 'completionPercent' => 0, 'improvement' => 0, 'isPodium' => false, 'user' => $caro],
        ];
        $isTrainer = session('auth_user.id') === 9;
        $viewer = $isTrainer ? ['standing' => null, 'awardSlugs' => [], 'reason' => 'owner'] : ['standing' => $anaStanding, 'awardSlugs' => ['strength'], 'reason' => null];

        return [
            'gym' => ['id' => 2, 'name' => 'Coach Gym', 'isRankingPublic' => true],
            'canManage' => $isTrainer,
            'current' => match ($this->currentStatus) {
                'active' => ['number' => 2, 'startsOn' => '2026-10-10', 'endsOn' => '2026-11-08', 'status' => 'active', 'days' => 30, 'daysLeft' => 25,
                    'daysToStart' => 0, 'standings' => $standingList, 'awards' => [], 'viewer' => $viewer],
                'scheduled' => ['number' => 2, 'startsOn' => '2026-10-18', 'endsOn' => '2026-11-16', 'status' => 'scheduled', 'days' => 30, 'daysLeft' => 0,
                    'daysToStart' => 4, 'standings' => [], 'awards' => [], 'viewer' => ['standing' => null, 'awardSlugs' => [], 'reason' => null]],
                default => null,
            },
            'recap' => ['number' => 1, 'startsOn' => '2026-09-10', 'endsOn' => '2026-10-09', 'status' => 'ended', 'days' => 30, 'daysLeft' => 0, 'daysToStart' => 0,
                'standings' => $standingList, 'viewer' => $viewer + ['previous' => null], 'race' => [
                    // Ben arranca adelante y Ana lo pasa el dia 14
                    ['userId' => 1, 'points' => array_map(fn(int $day) => $day < 5 ? 0 : min(340, 14 * ($day - 4)), range(0, 29))],
                    ['userId' => 2, 'points' => array_map(fn(int $day) => min(120, 12 * ($day + 1)), range(0, 29))],
                ], 'awards' => [
                    ['slug' => 'strength', 'userId' => 1, 'value' => 12000, 'user' => $ana],
                    ['slug' => 'comeback', 'userId' => 2, 'value' => 40, 'user' => $ben],
                ]],
            'openRecap' => !$isTrainer,
            'closedSeasonNumbers' => [1],
        ];
    }
}
