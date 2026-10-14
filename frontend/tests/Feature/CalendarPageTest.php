<?php

namespace Tests\Feature;

use App\Support\NotificationLabels;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarPageTest extends TestCase
{
    private const authSession = ['auth_user' => ['id' => 1, 'permits' => []], 'auth_token' => 'token'];

    private array $requestList = [];

    private array $noteResponse = ['success' => true, 'data' => ['id' => 4, 'noteOn' => '2026-10-13']];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $this->requestList[] = [$request->method(), $request->url(), $request->data()];
            return match (true) {
                str_contains($path, '/me/calendar/notes') => Http::response($this->noteResponse, ($this->noteResponse['success'] ?? false) ? 200 : 400),
                str_ends_with($path, '/me/calendar') => Http::response(['success' => true, 'data' => [
                    'month' => '2026-10',
                    'actions' => [
                        ['id' => 1, 'action' => 'created', 'model' => 'routine', 'entityId' => 20, 'createdAt' => '2026-10-13T08:10:00-03:00', 'data' => ['name' => 'Fuerza']],
                        ['id' => 2, 'action' => 'rank-up', 'model' => 'user', 'entityId' => 1, 'createdAt' => '2026-10-13T19:00:00-03:00', 'data' => ['name' => 'Ana', 'rank' => 'beginner']],
                    ],
                    'trainings' => [
                        ['day' => '2026-10-13', 'routineId' => 20, 'routineName' => 'Fuerza', 'doneCount' => 4, 'totalCount' => 4, 'isCompleted' => true],
                        ['day' => '2026-10-14', 'routineId' => 21, 'routineName' => 'Cardio', 'doneCount' => 1, 'totalCount' => 3, 'isCompleted' => false],
                    ],
                    'notes' => [
                        ['id' => 4, 'noteOn' => '2026-10-13', 'title' => 'Medir cintura', 'body' => 'En ayunas', 'remindDays' => [2, 0], 'notifiedDays' => []],
                    ],
                ]]),
                default => Http::response(['success' => true, 'data' => []]),
            };
        });
    }

    public function test_month_grid_and_selected_day_show_training_actions_and_notes(): void
    {
        $this->withSession(self::authSession)->get('/calendar?month=2026-10&day=2026-10-13')
            ->assertOk()
            ->assertSee('Octubre 2026')
            ->assertSee('Martes 13 de octubre')
            ->assertSee('is-completed', false)
            ->assertSeeInOrder(['Anotaciones', 'Medir cintura', 'En ayunas', 'Te avisamos 2 dias antes y el mismo dia',
                'Entrenamiento', 'Completaste la rutina «Fuerza»',
                'Actividad', 'Creaste la rutina «Fuerza»', '08:10', 'Subiste al rango Principiante'])
            ->assertSee(route('calendar.index', ['month' => '2026-09', 'day' => '2026-09-01']))
            ->assertSee(route('calendar.notes.destroy', 4), false);
        $this->assertStringEndsWith('/me/calendar?month=2026-10', $this->requestList[array_key_last(array_filter(
            $this->requestList, fn(array $call) => str_contains($call[1], '/me/calendar')))][1]);

        $this->withSession(self::authSession)->get('/calendar?month=2026-10&day=2026-10-14')
            ->assertOk()
            ->assertSee('Entrenaste la rutina «Cardio»: 1 de 3 ejercicios');
    }

    public function test_edit_link_prefills_the_form_and_bad_dates_fall_back_to_the_current_month(): void
    {
        $this->withSession(self::authSession)->get('/calendar?month=2026-10&day=2026-10-13&edit=4')
            ->assertOk()
            ->assertSee('Editar anotacion · Martes 13 de octubre')
            ->assertSee(route('calendar.notes.update', 4), false)
            ->assertDontSee('name="note_on"', false)
            ->assertDontSee('type="date"', false);
        $this->withSession(self::authSession)->get('/calendar?month=2026-10&day=2026-10-20')
            ->assertSee('Nueva anotacion · Martes 20 de octubre')
            ->assertSee('<input type="hidden" name="note_on" value="2026-10-20">', false);
        $html = $this->withSession(self::authSession)->get('/calendar?month=2026-10&day=2026-10-13&edit=4')->getContent();
        $this->assertMatchesRegularExpression('/value="2" id="remind_days_2"\s+checked/', $html);
        $this->assertMatchesRegularExpression('/value="0" id="remind_days_0"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="1" id="remind_days_1"\s+checked/', $html);

        $this->withSession(self::authSession)->get('/calendar?month=basura&day=2026-99-99')->assertOk();
    }

    public function test_new_note_with_reminder_is_sent_and_returns_to_its_day(): void
    {
        $this->withSession(self::authSession)->withoutMiddleware(VerifyCsrfToken::class)
            ->post('/calendar/notes', ['note_on' => '2026-10-20', 'title' => 'Turno nutricionista', 'body' => '', 'remind_days' => ['2', '1', '0']])
            ->assertRedirect(route('calendar.index', ['month' => '2026-10', 'day' => '2026-10-20']))
            ->assertSessionHas('calendar_saved', 'Anotacion guardada. Te avisamos 2 dias antes, el dia anterior y el mismo dia en la campanita.');
        $this->assertContains(['POST', config('services.backend.url').'/me/calendar/notes',
            ['note_on' => '2026-10-20', 'title' => 'Turno nutricionista', 'body' => null, 'remind_days' => ['2', '1', '0']]], $this->requestList);

        $this->withSession(self::authSession)->withoutMiddleware(VerifyCsrfToken::class)
            ->post('/calendar/notes', ['note_on' => '2026-10-21', 'title' => 'Solo nota'])
            ->assertSessionHas('calendar_saved', 'Anotacion guardada.');
        $this->assertContains(['POST', config('services.backend.url').'/me/calendar/notes',
            ['note_on' => '2026-10-21', 'title' => 'Solo nota', 'body' => null, 'remind_days' => []]], $this->requestList);
    }

    public function test_editing_a_note_keeps_its_day(): void
    {
        $this->withSession(self::authSession)->withoutMiddleware(VerifyCsrfToken::class)
            ->put('/calendar/notes/4', ['note_on' => '2026-10-25', 'title' => 'Medir cintura', 'remind_days' => ['1']])
            ->assertRedirect(route('calendar.index', ['month' => '2026-10', 'day' => '2026-10-13']));
        $putPayload = collect($this->requestList)->first(fn(array $call) => $call[0] === 'PUT')[2];
        $this->assertEquals(['title' => 'Medir cintura', 'body' => null, 'remind_days' => ['1']], $putPayload);
    }

    public function test_backend_rejection_keeps_what_the_user_wrote(): void
    {
        $this->noteResponse = ['success' => false, 'error' => ['message' => 'No se puede poner un recordatorio en un dia que ya paso']];

        $this->withSession(self::authSession)->withoutMiddleware(VerifyCsrfToken::class)
            ->from('/calendar?month=2026-10&day=2026-10-01')
            ->post('/calendar/notes', ['note_on' => '2026-10-01', 'title' => 'Tarde', 'remind_days' => ['0']])
            ->assertRedirect('/calendar?month=2026-10&day=2026-10-01');
        $this->get('/calendar?month=2026-10&day=2026-10-01')->assertOk()
            ->assertSee('No se puede poner un recordatorio en un dia que ya paso')
            ->assertSee('value="Tarde"', false);
    }

    public function test_reminder_notification_reads_well_and_opens_its_day(): void
    {
        $notification = ['id' => 9, 'type' => 'reminder', 'refId' => 4, 'isRead' => false, 'createdAt' => '2026-10-20T09:00:00-03:00',
            'data' => ['title' => 'Turno nutricionista', 'noteOn' => '2026-10-20', 'daysLeft' => 1]];

        $item = NotificationLabels::buildItem($notification);

        $this->assertSame(['Recordatorio: Turno nutricionista', 'Es mañana · 20/10/2026'], [$item['text'], $item['detail']]);
        $this->assertSame(route('calendar.index', ['month' => '2026-10', 'day' => '2026-10-20']), NotificationLabels::targetUrl($notification));
    }
}
